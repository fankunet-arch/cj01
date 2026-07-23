<?php

declare(strict_types=1);

namespace Cj\Dedup;

use Cj\Repository\CrawlerRepository;
use Cj\Repository\MainRepository;

/**
 * 三级去重引擎（文档 §3，本程序技术核心）。
 *
 *   一级：采集库内 URL 去重（同站重复采集）
 *   二级：跨站内容去重（contact_key / SimHash）
 *   三级：与 zhaopin 主库去重（防止导入已有岗位）
 *
 * 判定结果通过 DedupResult 返回，由调用方决定入库姿态并写 cj_dedup_log。
 */
final class DedupEngine
{
    private CrawlerRepository $crawler;
    private MainRepository $main;
    private array $thresholds;

    /** [种类, matched_against, matched_id, signal, hamming, decision] 的日志缓冲 */
    private array $pendingLogs = [];

    public function __construct(?CrawlerRepository $crawler = null, ?MainRepository $main = null)
    {
        $this->crawler = $crawler ?? new CrawlerRepository();
        $this->main = $main ?? new MainRepository();
        $this->thresholds = cj_config('dedup') ?? [
            'hamming_dup' => 3, 'hamming_review' => 8, 'hamming_dup_no_contact' => 2,
        ];
    }

    /** 一级：source_url 已存在 → 丢弃（调用方在抓详情页之前就应先查，省请求）。 */
    public function isKnownUrl(string $url): bool
    {
        return $this->crawler->urlExists($url);
    }

    /**
     * 二级 + 三级判定。
     * $job 需含：contact_key(?string, 采集库内去重)、phone_norm(?string, 主库去重)、
     *           simhash(int)、title、publish_date；
     * 重判存量记录时传 self_id 以排除与自身比对（bin/dedup.php）。
     * 返回：
     *   ['status' => 'unique'|'dup_cross'|'exists_in_main'|'review',
     *    'confidence' => 'high'|'low',
     *    'matches' => [ [against, matched_id, signal, hamming, decision], ... ]]
     */
    public function judge(array $job): array
    {
        $this->pendingLogs = [];
        $hasContact = !empty($job['contact_key']);
        $simhash = (int) ($job['simhash'] ?? 0);
        $selfId = isset($job['self_id']) ? (int) $job['self_id'] : null;

        // 联系方式缺失时的降级阈值（文档 §3.4：宁可漏判进复核，不误判合并）
        $dupThreshold = $hasContact
            ? (int) $this->thresholds['hamming_dup']
            : (int) $this->thresholds['hamming_dup_no_contact'];
        $reviewThreshold = (int) $this->thresholds['hamming_review'];

        // ---- 二级：跨站，信号 A contact_key ----
        if ($hasContact) {
            $hits = array_values(array_filter(
                $this->crawler->findByContactKey($job['contact_key']),
                static fn(array $h): bool => $selfId === null || (int) $h['id'] !== $selfId
            ));
            if ($hits !== []) {
                $hit = $hits[0];
                $this->pendingLogs[] = ['crawler', (int) $hit['id'], 'contact_key', null, 'dup'];
                return $this->result('dup_cross', 'high');
            }
        }

        // ---- 二级：跨站，信号 B SimHash ----
        if ($simhash !== 0) {
            $candidates = $this->crawler->allSimhashes();
            if ($selfId !== null) {
                unset($candidates[$selfId]);
            }
            [$bestId, $bestDist] = $this->closest($candidates, $simhash);
            if ($bestId !== null) {
                if ($bestDist <= $dupThreshold) {
                    $this->pendingLogs[] = ['crawler', $bestId, 'simhash', $bestDist, 'dup'];
                    return $this->result('dup_cross', $hasContact ? 'high' : 'low');
                }
                if ($bestDist <= $reviewThreshold) {
                    $this->pendingLogs[] = ['crawler', $bestId, 'simhash', $bestDist, 'review'];
                    return $this->result('review', 'low', $bestId,
                        sprintf('simhash 汉明距离=%d（%d–%d 区间），需人工复核', $bestDist, $dupThreshold + 1, $reviewThreshold));
                }
            }
        }

        // ---- 三级：与 zhaopin 主库 ----
        // 主库真实结构用 phone_norm 做电话去重键（已并入 idx_dedup），无 contact_key。
        if ($this->main->enabled()) {
            $phoneNorm = (string) ($job['phone_norm'] ?? '');
            if ($phoneNorm !== '') {
                $mainId = $this->main->findByPhoneNorm($phoneNorm);
                if ($mainId !== null) {
                    // cj_dedup_log.signal 枚举仍记为 contact_key（phone_norm 即联系方式键）
                    $this->pendingLogs[] = ['main', $mainId, 'contact_key', null, 'dup'];
                    return $this->result('exists_in_main', 'high');
                }
            }
            if ($simhash !== 0) {
                // 方案 A：接口内比对；方案 B：拉指纹本地比对
                $apiHit = $this->main->simhashCheck($simhash);
                if ($apiHit !== null) {
                    $this->pendingLogs[] = ['main', $apiHit['matched_id'], 'simhash', $apiHit['hamming'], 'dup'];
                    return $this->result('exists_in_main', 'high');
                }
                [$bestId, $bestDist] = $this->closest($this->main->allSimhashes(), $simhash);
                if ($bestId !== null && $bestDist <= $dupThreshold) {
                    $this->pendingLogs[] = ['main', $bestId, 'simhash', $bestDist, 'dup'];
                    return $this->result('exists_in_main', $hasContact ? 'high' : 'low');
                }
                if ($bestId !== null && $bestDist <= $reviewThreshold) {
                    $this->pendingLogs[] = ['main', $bestId, 'simhash', $bestDist, 'review'];
                    return $this->result('review', 'low', null,
                        sprintf('与主库记录 simhash 汉明距离=%d，需人工复核', $bestDist));
                }
            }
        }

        $this->pendingLogs[] = ['crawler', null, 'simhash', null, 'unique'];
        return $this->result('unique', $hasContact ? 'high' : 'low');
    }

    /** 判定后调用：把缓冲的判定过程写入 cj_dedup_log（需已知新记录 id）。 */
    public function flushLogs(int $jobId): void
    {
        foreach ($this->pendingLogs as [$against, $matchedId, $signal, $hamming, $decision]) {
            $this->crawler->logDedup($jobId, $against, $matchedId, $signal, $hamming, $decision);
        }
        $this->pendingLogs = [];
    }

    /** 在候选指纹集中找汉明距离最小者，返回 [id|null, dist]。 */
    private function closest(array $candidates, int $simhash): array
    {
        $bestId = null;
        $bestDist = PHP_INT_MAX;
        foreach ($candidates as $id => $candidate) {
            $d = SimHash::hammingDistance($candidate, $simhash);
            if ($d < $bestDist) {
                $bestDist = $d;
                $bestId = (int) $id;
            }
        }
        return [$bestId, $bestId === null ? PHP_INT_MAX : $bestDist];
    }

    private function result(string $status, string $confidence, ?int $reviewCandidate = null, ?string $reviewReason = null): array
    {
        return [
            'status'           => $status,
            'confidence'       => $confidence,
            'review_candidate' => $reviewCandidate,
            'review_reason'    => $reviewReason,
        ];
    }
}
