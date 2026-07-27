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
    private ?string $lastTitle = null;

    public function __construct(array $siteConfig)
    {
        $this->site = $siteConfig;
        $this->repo = new CrawlerRepository();
        $this->dedup = new DedupEngine($this->repo);
        $this->fetcher = new Fetcher($siteConfig['site'], $siteConfig['rate_limit'] ?? [], $siteConfig['http'] ?? []);
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

    /**
     * 同步采集（虚拟主机 / 无 cron 用）：在当前请求内直接跑一小批，返回诊断结果。
     * 不依赖 exec/后台进程；有条数上限与时间预算，避免 Web 请求超时。
     */
    public function runSync(int $maxItems = 10, int $maxSeconds = 25): array
    {
        $siteId = $this->site['site'];
        $r = ['site' => $siteId, 'pages' => 0, 'links' => 0, 'new' => 0, 'dup' => 0,
              'errors' => 0, 'list_status' => null, 'note' => null, 'error' => null, 'samples' => []];

        if (($this->site['render'] ?? 'php') !== 'php') {
            $r['error'] = '该站为 headless(JS) 渲染，PHP 端不支持';
            return $r;
        }

        // 同步模式用短间隔，避免请求超时（生产大批量仍走 CLI/cron 的礼貌间隔）
        $this->fetcher = new Fetcher($siteId . '-sync', ['min_delay' => 1, 'max_delay' => 2], $this->site['http'] ?? []);
        @set_time_limit($maxSeconds + 20);
        $deadline = microtime(true) + $maxSeconds;

        // 预热：先访问站点首页以获取 cookie 并形成合理 Referer（对 cookie 门/反爬有用）
        if (!empty($this->site['warmup_url'])) {
            $this->fetcher->get((string) $this->site['warmup_url'], $this->site['charset'] ?? null);
        }

        $runId = $this->repo->startRun($siteId);
        try {
            $maxPages = (int) (cj_config('crawl')['max_pages_per_run'] ?? 10);
            for ($page = 1; $page <= $maxPages; $page++) {
                $listUrl = sprintf($this->site['list_url'], $page);
                $res = $this->fetcher->get($listUrl, $this->site['charset'] ?? null);
                $r['pages']++;
                if ($page === 1) {
                    $r['list_status'] = $res['status'];
                }
                if ($res['status'] !== 200 || $res['body'] === null) {
                    $r['errors']++;
                    $sv = $this->fetcher->serverHeader();
                    $r['note'] = "列表页抓取失败 HTTP {$res['status']}（服务器无法访问该站/被反爬拦截"
                        . ($sv !== null ? "；响应头 {$sv}" : '') . '）';
                    break;
                }
                $urls = $this->parser->parseListPage($res['body'], $listUrl);
                if ($page === 1) {
                    $r['links'] = count($urls);
                }
                if ($urls === []) {
                    $r['note'] = '列表页解析到 0 个详情链接（选择器不匹配或已到末页）';
                    break;
                }
                foreach ($urls as $url) {
                    if (microtime(true) >= $deadline) {
                        $r['note'] = '已达时间上限，未采完（可再次点击继续）';
                        break 2;
                    }
                    if ($r['new'] >= $maxItems) {
                        $r['note'] = "已达本次上限 {$maxItems} 条（可再次点击继续采下一批）";
                        break 2;
                    }
                    if ($this->dedup->isKnownUrl($url)) {
                        $r['dup']++;
                        continue;
                    }
                    $result = $this->crawlDetail($url);
                    if ($result === 'new') {
                        $r['new']++;
                        if (count($r['samples']) < 5 && $this->lastTitle !== null) {
                            $r['samples'][] = $this->lastTitle;
                        }
                    } elseif ($result === 'dup') {
                        $r['dup']++;
                    } else {
                        $r['errors']++;
                    }
                }
            }
            $status = ($r['errors'] > 0 && $r['new'] === 0) ? 'failed' : 'ok';
            $this->repo->finishRun($runId, $status, $r['pages'], $r['new'], $r['dup'], $r['errors'], $r['note']);
        } catch (\Throwable $e) {
            $r['error'] = $e->getMessage();
            $this->repo->finishRun($runId, 'failed', $r['pages'], $r['new'], $r['dup'], $r['errors'] + 1, mb_substr($e->getMessage(), 0, 480));
        }
        return $r;
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
        $this->lastTitle = $raw['title'];

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
