<?php

declare(strict_types=1);

namespace Cj\Repository;

use Cj\Dedup\SimHash;
use Cj\Support\Db;
use PDO;

/**
 * zhaopin 主库访问（文档 §4.4）。
 * - 三级去重：只读比对（方案 A 接口 / 方案 B 只读直连，由 main.mode 决定）。
 * - 导入/清理：仅 Importer / Purger 调用写方法，且带 origin='crawler' 双保险。
 * 主库招聘表需已执行 db/02_zhaopin_main_ddl_patch.sql。
 */
final class MainRepository
{
    private string $mode;
    private array $cfg;
    private ?PDO $db = null;

    public function __construct()
    {
        $this->cfg = cj_config('main') ?? ['mode' => 'off'];
        $this->mode = $this->cfg['mode'] ?? 'off';
    }

    public function enabled(): bool
    {
        return $this->mode !== 'off';
    }

    private function pdo(): PDO
    {
        if ($this->db === null) {
            $this->db = Db::main();
        }
        return $this->db;
    }

    private function jobsTable(): string
    {
        $t = $this->cfg['db']['jobs_table'] ?? 'jobs';
        return preg_replace('/[^A-Za-z0-9_]/', '', $t);   // 表名来自配置，防注入
    }

    // ---------- 三级去重（只读） ----------

    /** contact_key 是否命中主库；命中返回主库记录 id，否则 null。 */
    public function findByContactKey(string $contactKey): ?int
    {
        if ($this->mode === 'api') {
            $r = $this->apiCheck(['contact_key' => $contactKey]);
            return $r['exists'] ?? false ? (int) ($r['id'] ?? 0) : null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT id FROM ' . $this->jobsTable() . ' WHERE contact_key = :k LIMIT 1'
        );
        $stmt->execute([':k' => $contactKey]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /**
     * 主库全部指纹 [id => simhash(int 位模式)]，用于汉明距离比对。
     * 冷启动主库量小可全量拉取；量大时应改为主站侧接口内比对（方案 A）。
     */
    public function allSimhashes(): array
    {
        if ($this->mode === 'api') {
            return [];   // 方案 A 下 simhash 比对在主站接口内完成（simhashCheck()）
        }
        $rows = $this->pdo()->query(
            "SELECT id, LPAD(HEX(simhash),16,'0') AS h FROM " . $this->jobsTable() .
            ' WHERE simhash IS NOT NULL'
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = SimHash::fromHex($r['h']);
        }
        return $out;
    }

    /** 方案 A：simhash 交给主站接口比对，返回 ['matched_id'=>?, 'hamming'=>?]。 */
    public function simhashCheck(int $simhash): ?array
    {
        if ($this->mode !== 'api') {
            return null;
        }
        $r = $this->apiCheck(['simhash' => SimHash::toDb($simhash)]);
        if (($r['exists'] ?? false) === true) {
            return ['matched_id' => (int) ($r['id'] ?? 0), 'hamming' => (int) ($r['hamming'] ?? 0)];
        }
        return null;
    }

    private function apiCheck(array $payload): array
    {
        $api = $this->cfg['api'] ?? [];
        $ch = curl_init($api['url'] ?? '');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . ($api['token'] ?? ''),
            ],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return is_string($body) ? (json_decode($body, true) ?: []) : [];
    }

    // ---------- 导入（仅 Importer 调用，人工确认后执行） ----------

    /** 采集记录写入主库招聘表，origin='crawler'，返回主库新 id。 */
    public function insertJob(array $job): int
    {
        $pdo = $this->pdo();
        $sql = 'INSERT INTO ' . $this->jobsTable() . '
                (title, company, category, city, district, salary_raw, description,
                 contact_phone, contact_wechat, contact_name,
                 contact_key, simhash, publish_date, origin, created_at)
                VALUES
                (:title, :company, :category, :city, :district, :salary_raw, :description,
                 :contact_phone, :contact_wechat, :contact_name,
                 :contact_key, :simhash, :publish_date, \'crawler\', NOW())';
        $pdo->prepare($sql)->execute([
            ':title'          => $job['title'],
            ':company'        => $job['company'],
            ':category'       => $job['category'],
            ':city'           => $job['city'],
            ':district'       => $job['district'],
            ':salary_raw'     => $job['salary_raw'],
            ':description'    => $job['description'],
            ':contact_phone'  => $job['contact_phone'],
            ':contact_wechat' => $job['contact_wechat'],
            ':contact_name'   => $job['contact_name'],
            ':contact_key'    => $job['contact_key'],
            ':simhash'        => $job['simhash'],   // 已是无符号十进制字符串
            ':publish_date'   => $job['publish_date'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    // ---------- 清理（仅 Purger 调用） ----------

    /**
     * 删除主库对应记录。origin='crawler' 作双保险，防止误删自有 UGC（文档 §8）。
     * 返回是否确实删除。
     */
    public function deleteCrawlerJob(int $mainJobId): bool
    {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM ' . $this->jobsTable() . " WHERE id = :id AND origin = 'crawler'"
        );
        $stmt->execute([':id' => $mainJobId]);
        return $stmt->rowCount() > 0;
    }
}
