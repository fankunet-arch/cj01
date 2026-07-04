<?php

declare(strict_types=1);

namespace Cj\Repository;

use Cj\Dedup\SimHash;
use Cj\Support\Db;
use PDO;

/**
 * 采集库（crawler_db）读写。所有表统一 cj_ 前缀。
 */
final class CrawlerRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Db::crawler();
    }

    // ---------- cj_raw_pages ----------

    public function saveRawPage(string $site, string $url, ?string $html, ?int $httpStatus): void
    {
        $sql = 'INSERT INTO cj_raw_pages (source_site, source_url, raw_html, http_status, fetched_at)
                VALUES (:site, :url, :html, :status, NOW())
                ON DUPLICATE KEY UPDATE raw_html = VALUES(raw_html),
                                        http_status = VALUES(http_status),
                                        fetched_at = VALUES(fetched_at)';
        $this->db->prepare($sql)->execute([
            ':site' => $site, ':url' => $url, ':html' => $html, ':status' => $httpStatus,
        ]);
    }

    // ---------- cj_jobs_clean ----------

    /** 一级去重：source_url 是否已存在（同站重复采集，文档 §3.2）。 */
    public function urlExists(string $url): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM cj_jobs_clean WHERE source_url = :url LIMIT 1');
        $stmt->execute([':url' => $url]);
        return (bool) $stmt->fetchColumn();
    }

    /** 二级去重信号 A：contact_key 命中的既有记录。 */
    public function findByContactKey(string $contactKey): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, publish_date FROM cj_jobs_clean WHERE contact_key = :k'
        );
        $stmt->execute([':k' => $contactKey]);
        return $stmt->fetchAll();
    }

    /**
     * 二级去重信号 B：全部既有指纹 [id => simhash(int 位模式)]。
     * 冷启动数据量有限，直接全量比对；量大时可换分段索引（pigeonhole）。
     */
    public function allSimhashes(): array
    {
        $rows = $this->db->query(
            "SELECT id, LPAD(HEX(simhash),16,'0') AS h FROM cj_jobs_clean WHERE simhash IS NOT NULL"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = SimHash::fromHex($r['h']);
        }
        return $out;
    }

    /** 清洗后记录入库，返回新 id。 */
    public function insertCleanJob(array $job): int
    {
        $sql = 'INSERT INTO cj_jobs_clean
                (source_site, source_url, title, company, category, city, district,
                 salary_raw, description, contact_phone, contact_wechat, contact_name,
                 phone_norm, wechat_norm, contact_key, simhash, publish_date,
                 collected_at, purge_after, dedup_status, confidence, import_ready)
                VALUES
                (:source_site, :source_url, :title, :company, :category, :city, :district,
                 :salary_raw, :description, :contact_phone, :contact_wechat, :contact_name,
                 :phone_norm, :wechat_norm, :contact_key, :simhash, :publish_date,
                 NOW(), :purge_after, :dedup_status, :confidence, :import_ready)';
        $this->db->prepare($sql)->execute([
            ':source_site'    => $job['source_site'],
            ':source_url'     => $job['source_url'],
            ':title'          => $job['title'] ?? null,
            ':company'        => $job['company'] ?? null,
            ':category'       => $job['category'] ?? null,
            ':city'           => $job['city'] ?? null,
            ':district'       => $job['district'] ?? null,
            ':salary_raw'     => $job['salary_raw'] ?? null,
            ':description'    => $job['description'] ?? null,
            ':contact_phone'  => $job['contact_phone'] ?? null,
            ':contact_wechat' => $job['contact_wechat'] ?? null,
            ':contact_name'   => $job['contact_name'] ?? null,
            ':phone_norm'     => $job['phone_norm'] ?? null,
            ':wechat_norm'    => $job['wechat_norm'] ?? null,
            ':contact_key'    => $job['contact_key'] ?? null,
            ':simhash'        => isset($job['simhash']) ? SimHash::toDb((int) $job['simhash']) : null,
            ':publish_date'   => $job['publish_date'] ?? null,
            ':purge_after'    => $job['purge_after'] ?? null,
            ':dedup_status'   => $job['dedup_status'] ?? 'unique',
            ':confidence'     => $job['confidence'] ?? 'high',
            ':import_ready'   => (int) ($job['import_ready'] ?? 0),
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ---------- cj_dedup_log / cj_review_queue ----------

    public function logDedup(int $jobId, string $against, ?int $matchedId, string $signal, ?int $hamming, string $decision): void
    {
        // signal 是 MySQL 保留字，须加反引号
        $sql = 'INSERT INTO cj_dedup_log (job_id, matched_against, matched_id, `signal`, hamming_dist, decision, created_at)
                VALUES (:job, :against, :matched, :signal, :hamming, :decision, NOW())';
        $this->db->prepare($sql)->execute([
            ':job' => $jobId, ':against' => $against, ':matched' => $matchedId,
            ':signal' => $signal, ':hamming' => $hamming, ':decision' => $decision,
        ]);
    }

    public function queueReview(int $jobId, ?int $candidateId, string $reason): void
    {
        $sql = 'INSERT INTO cj_review_queue (job_id, candidate_id, reason, created_at)
                VALUES (:job, :candidate, :reason, NOW())';
        $this->db->prepare($sql)->execute([
            ':job' => $jobId, ':candidate' => $candidateId, ':reason' => $reason,
        ]);
    }

    public function pendingReviews(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT q.id AS queue_id, q.reason, q.created_at,
                    j.id AS job_id, j.source_site, j.source_url, j.title, j.city,
                    j.contact_key, j.publish_date,
                    c.id AS cand_id, c.source_site AS cand_site, c.title AS cand_title,
                    c.source_url AS cand_url
             FROM cj_review_queue q
             JOIN cj_jobs_clean j ON j.id = q.job_id
             LEFT JOIN cj_jobs_clean c ON c.id = q.candidate_id
             WHERE q.resolved = 0
             ORDER BY q.id ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function resolveReview(int $queueId, string $resolution): void
    {
        $this->db->prepare(
            'UPDATE cj_review_queue SET resolved = 1, resolution = :r WHERE id = :id'
        )->execute([':r' => $resolution, ':id' => $queueId]);

        // 复核结论落到业务表：keep=可导入；discard=按跨站重复丢弃；merge=保留但不导入
        $stmt = $this->db->prepare('SELECT job_id FROM cj_review_queue WHERE id = :id');
        $stmt->execute([':id' => $queueId]);
        $jobId = (int) $stmt->fetchColumn();
        if ($jobId > 0) {
            if ($resolution === 'keep') {
                $this->db->prepare(
                    "UPDATE cj_jobs_clean SET dedup_status='unique', import_ready=1 WHERE id=:id"
                )->execute([':id' => $jobId]);
            } elseif ($resolution === 'discard') {
                $this->db->prepare(
                    "UPDATE cj_jobs_clean SET dedup_status='dup_cross', import_ready=0 WHERE id=:id"
                )->execute([':id' => $jobId]);
            } else {   // merge
                $this->db->prepare(
                    "UPDATE cj_jobs_clean SET dedup_status='dup_cross', import_ready=0 WHERE id=:id"
                )->execute([':id' => $jobId]);
            }
        }
    }

    // ---------- cj_crawl_runs ----------

    public function startRun(string $site): int
    {
        $this->db->prepare(
            "INSERT INTO cj_crawl_runs (source_site, started_at, status) VALUES (:site, NOW(), 'running')"
        )->execute([':site' => $site]);
        return (int) $this->db->lastInsertId();
    }

    public function finishRun(int $runId, string $status, int $pages, int $new, int $dup, int $errors, ?string $note = null): void
    {
        $this->db->prepare(
            'UPDATE cj_crawl_runs
             SET finished_at = NOW(), status = :status, pages_fetched = :pages,
                 new_jobs = :new, dup_jobs = :dup, errors = :errors, note = :note
             WHERE id = :id'
        )->execute([
            ':status' => $status, ':pages' => $pages, ':new' => $new,
            ':dup' => $dup, ':errors' => $errors, ':note' => $note, ':id' => $runId,
        ]);
    }

    public function recentRuns(int $limit = 30): array
    {
        return $this->db->query(
            'SELECT * FROM cj_crawl_runs ORDER BY id DESC LIMIT ' . (int) $limit
        )->fetchAll();
    }

    // ---------- 导入 / cj_import_map ----------

    /** 待导入数量（import_ready=1 且未导入）。 */
    public function pendingImportCount(): int
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM cj_jobs_clean WHERE import_ready = 1 AND imported_at IS NULL'
        )->fetchColumn();
    }

    public function importReadyJobs(int $limit = 200): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM cj_jobs_clean
             WHERE import_ready = 1 AND imported_at IS NULL
             ORDER BY id ASC LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function recordImport(int $crawlerJobId, int $mainJobId, string $batch): void
    {
        $this->db->prepare(
            'INSERT INTO cj_import_map (crawler_job_id, main_job_id, import_batch, imported_at)
             VALUES (:cid, :mid, :batch, NOW())'
        )->execute([':cid' => $crawlerJobId, ':mid' => $mainJobId, ':batch' => $batch]);

        $this->db->prepare(
            'UPDATE cj_jobs_clean SET imported_at = NOW() WHERE id = :id'
        )->execute([':id' => $crawlerJobId]);
    }

    /** 未清理的导入映射（可按批次过滤）——清理主库的账本。 */
    public function unpurgedImportMap(?string $batch = null): array
    {
        $sql = 'SELECT * FROM cj_import_map WHERE purged = 0';
        $params = [];
        if ($batch !== null) {
            $sql .= ' AND import_batch = :batch';
            $params[':batch'] = $batch;
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY id ASC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function markPurged(int $mapId): void
    {
        $this->db->prepare(
            'UPDATE cj_import_map SET purged = 1, purged_at = NOW() WHERE id = :id'
        )->execute([':id' => $mapId]);
    }

    /** 是否仍有未清理的主库导入记录（清理顺序保护，文档 §8）。 */
    public function hasUnpurgedImports(): bool
    {
        return (bool) $this->db->query(
            'SELECT 1 FROM cj_import_map WHERE purged = 0 LIMIT 1'
        )->fetchColumn();
    }

    // ---------- 清理 ----------

    /** 清空全部采集数据（TRUNCATE 所有 cj_ 表）。 */
    public function truncateAll(): void
    {
        foreach (['cj_dedup_log', 'cj_review_queue', 'cj_import_map', 'cj_jobs_clean', 'cj_raw_pages', 'cj_crawl_runs'] as $table) {
            $this->db->exec('TRUNCATE TABLE ' . $table);
        }
    }

    /** 按 purge_after 到期清理（仅未导入或已在主库清理完成的记录）。 */
    public function purgeExpired(): int
    {
        $stmt = $this->db->prepare(
            'DELETE j FROM cj_jobs_clean j
             LEFT JOIN cj_import_map m ON m.crawler_job_id = j.id AND m.purged = 0
             WHERE j.purge_after IS NOT NULL AND j.purge_after < CURDATE() AND m.id IS NULL'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** 按站清理。 */
    public function purgeSite(string $site): int
    {
        $stmt = $this->db->prepare(
            'DELETE j FROM cj_jobs_clean j
             LEFT JOIN cj_import_map m ON m.crawler_job_id = j.id AND m.purged = 0
             WHERE j.source_site = :site AND m.id IS NULL'
        );
        $stmt->execute([':site' => $site]);
        $n = $stmt->rowCount();
        $this->db->prepare('DELETE FROM cj_raw_pages WHERE source_site = :site')
                 ->execute([':site' => $site]);
        return $n;
    }
}
