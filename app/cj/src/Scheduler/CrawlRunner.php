<?php

declare(strict_types=1);

namespace Cj\Scheduler;

use Cj\Dedup\DedupEngine;
use Cj\Dedup\SimHash;
use Cj\Fetcher\Fetcher;
use Cj\Normalizer\ContactNormalizer;
use Cj\Parser\SiteParser;
use Cj\Repository\CrawlerRepository;
use Cj\Support\Logger;

/**
 * 单站采集编排（文档 §4.5 数据流）：
 * 目标站 → Fetcher → Parser → Normalizer → Dedup(三级) → cj_jobs_clean
 * 含增量采集（连续 M 条已存在即停止翻页，§6.4）与改版容错告警（§6.5）。
 */
final class CrawlRunner
{
    private array $site;
    private CrawlerRepository $repo;
    private DedupEngine $dedup;
    private Fetcher $fetcher;
    private SiteParser $parser;
    private bool $lastTitleEmpty = false;

    public function __construct(array $siteConfig)
    {
        $this->site = $siteConfig;
        $this->repo = new CrawlerRepository();
        $this->dedup = new DedupEngine($this->repo);
        $this->fetcher = new Fetcher($siteConfig['site'], $siteConfig['rate_limit'] ?? []);
        $this->parser = new SiteParser($siteConfig);
    }

    public function run(): void
    {
        $siteId = $this->site['site'];
        if (($this->site['render'] ?? 'php') !== 'php') {
            Logger::info('crawl', "[$siteId] render=headless：该站走 Node 侧 headless 通道，PHP 端跳过（文档 §4.1）");
            return;
        }

        $crawlCfg = cj_config('crawl');
        $maxPages = (int) ($crawlCfg['max_pages_per_run'] ?? 10);
        $stopAfterKnown = (int) ($crawlCfg['stop_after_known'] ?? 5);

        $runId = $this->repo->startRun($siteId);
        $pages = 0;
        $new = 0;
        $dup = 0;
        $errors = 0;
        $titleEmpty = 0;
        $note = null;

        try {
            $consecutiveKnown = 0;
            for ($page = 1; $page <= $maxPages; $page++) {
                $listUrl = sprintf($this->site['list_url'], $page);
                $res = $this->fetcher->get($listUrl, $this->site['charset'] ?? null);
                $pages++;
                if ($res['status'] !== 200 || $res['body'] === null) {
                    $errors++;
                    Logger::error('crawl', "[$siteId] 列表页抓取失败 p{$page} status={$res['status']}");
                    break;
                }

                $detailUrls = $this->parser->parseListPage($res['body'], $listUrl);
                if ($detailUrls === []) {
                    Logger::info('crawl', "[$siteId] p{$page} 列表页无链接，停止翻页（可能到底或选择器失效）");
                    break;
                }

                foreach ($detailUrls as $url) {
                    // 一级去重：入库前先查 URL，已存在则跳过（也省一次详情页请求）
                    if ($this->dedup->isKnownUrl($url)) {
                        $dup++;
                        $consecutiveKnown++;
                        if ($consecutiveKnown >= $stopAfterKnown) {
                            Logger::info('crawl', "[$siteId] 连续 {$consecutiveKnown} 条已存在，增量采集停止（§6.4）");
                            break 2;
                        }
                        continue;
                    }
                    $consecutiveKnown = 0;

                    $result = $this->crawlDetail($url);
                    if ($result === 'new') {
                        $new++;
                    } elseif ($result === 'dup') {
                        $dup++;
                    } else {
                        $errors++;
                    }
                    // 改版容错统计：新采记录 title 空值率
                    if ($result === 'new' && $this->lastTitleEmpty) {
                        $titleEmpty++;
                    }

                    // 结构变更告警：title 空值率超阈值 → 暂停该站（§6.5）
                    $alertRatio = (float) ($crawlCfg['title_empty_alert'] ?? 0.5);
                    if ($new >= 10 && $titleEmpty / max(1, $new) > $alertRatio) {
                        $note = sprintf('疑似站点改版：title 空值率 %.0f%%，已暂停本站采集', 100 * $titleEmpty / $new);
                        Logger::error('crawl', "[$siteId] $note");
                        \Cj\Scheduler\Alert::send("[$siteId] 采集告警", $note);
                        break 2;
                    }
                }
            }

            $this->repo->finishRun($runId, $note === null ? 'ok' : 'failed', $pages, $new, $dup, $errors, $note);
            Logger::info('crawl', "[$siteId] 完成：pages=$pages new=$new dup=$dup errors=$errors");
        } catch (\Throwable $e) {
            $this->repo->finishRun($runId, 'failed', $pages, $new, $dup, $errors + 1, mb_substr($e->getMessage(), 0, 480));
            Logger::error('crawl', "[$siteId] 异常终止：" . $e->getMessage());
            throw $e;
        }
    }

    /** 抓取并处理单个详情页，返回 'new' | 'dup' | 'error'。 */
    private function crawlDetail(string $url): string
    {
        $siteId = $this->site['site'];
        $res = $this->fetcher->get($url, $this->site['charset'] ?? null);
        if ($res['status'] !== 200 || $res['body'] === null) {
            Logger::error('crawl', "[$siteId] 详情页失败 status={$res['status']} $url");
            return 'error';
        }

        // 原始 HTML 存档：解析出错可重跑，不必重抓（§5.1）
        $this->repo->saveRawPage($siteId, $url, $res['body'], $res['status']);

        $raw = $this->parser->parseDetailPage($res['body']);
        $this->lastTitleEmpty = ($raw['title'] === null);

        // 归一化（§3.3）
        $phoneNorm = ContactNormalizer::phone($raw['contact_phone']);
        $wechatNorm = ContactNormalizer::wechat($raw['contact_wechat']);
        $contactKey = ContactNormalizer::contactKey($phoneNorm, $wechatNorm);
        $simhash = SimHash::ofJobText($raw['title'], $raw['company'], $raw['description']);

        // 二级 + 三级去重（三级主库比对用原始电话，MainRepository 内按 zp_phone_norm 归一化）
        $verdict = $this->dedup->judge([
            'contact_key'   => $contactKey,
            'contact_phone' => $raw['contact_phone'],
            'simhash'       => $simhash,
            'title'         => $raw['title'],
            'publish_date'  => $raw['publish_date'],
        ]);

        $purgeDays = (int) (cj_config('crawl')['purge_after_days'] ?? 90);
        $jobId = $this->repo->insertCleanJob($raw + [
            'source_site'  => $siteId,
            'source_url'   => $url,
            'phone_norm'   => $phoneNorm,
            'wechat_norm'  => $wechatNorm,
            'contact_key'  => $contactKey,
            'simhash'      => $simhash,
            'purge_after'  => date('Y-m-d', strtotime("+{$purgeDays} days")),
            'dedup_status' => $verdict['status'],
            'confidence'   => $verdict['confidence'],
            // 唯一新记录 → 待导入；导入主库保持人工把关，此处只标记（§4.5）
            'import_ready' => $verdict['status'] === 'unique' && $verdict['confidence'] === 'high' ? 1 : 0,
        ]);

        $this->dedup->flushLogs($jobId);
        if ($verdict['status'] === 'review') {
            $this->repo->queueReview($jobId, $verdict['review_candidate'], (string) $verdict['review_reason']);
        }

        return in_array($verdict['status'], ['dup_cross', 'exists_in_main'], true) ? 'dup' : 'new';
    }
}
