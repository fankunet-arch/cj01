<?php

declare(strict_types=1);

namespace Cj\Repository;

use Cj\Dedup\SimHash;
use Cj\Normalizer\ContactNormalizer;
use Cj\Support\Db;
use PDO;

/**
 * zhaopin 主库访问（真实结构：招聘表 zhaopin_posts）。
 *
 * 三级去重（只读）：
 *  - 电话：比对 zhaopin_posts.phone_norm（主库已有列并入 idx_dedup，无需新增）。
 *  - 指纹：比对 zhaopin_posts.simhash（新增列，见 db/02_zhaopin_main_ddl_patch.sql）。
 * 导入/清理（仅 Importer / Purger 调用）：写 zhaopin_posts，origin='crawler' 双保险。
 *
 * 与采集库字段的对应关系（真实主库无独立 title/company/salary/district/publish_date）：
 *  - title+company+salary_raw+description → 合并进 content(varchar 1000)
 *  - category(字符串) → category_id(外键，按名称查 zhaopin_categories，兜底 default_category_id)
 *  - city/district(字符串) → region_id(外键，按名称查 zhaopin_regions，兜底 default_region_id)
 *  - contact_phone/phone_norm/contact_wechat/contact_name → phone/phone_norm/wechat/contact_name
 *  - content_hash：按主站约定 SHA-256(content) 生成（如主站另有归一化规则，改 contentHash()）
 */
final class MainRepository
{
    private string $mode;
    private array $cfg;
    private array $importCfg;
    private ?PDO $db = null;

    /** name→id 映射缓存（导入时按名称解析 region/category） */
    private ?array $regionMap = null;
    private ?array $categoryMap = null;

    public function __construct()
    {
        $this->cfg = cj_config('main') ?? ['mode' => 'off'];
        $this->mode = $this->cfg['mode'] ?? 'off';
        $this->importCfg = $this->cfg['import'] ?? [];
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

    /** 表名来自配置，白名单化防注入。 */
    private function table(string $key, string $default): string
    {
        $t = $this->cfg['db'][$key] ?? $default;
        return preg_replace('/[^A-Za-z0-9_]/', '', (string) $t);
    }

    private function postsTable(): string
    {
        return $this->table('posts_table', 'zhaopin_posts');
    }

    // ---------- 三级去重（只读） ----------

    /**
     * 采集到的原始电话是否命中主库；命中返回主库记录 id，否则 null。
     * 主库 zhaopin_posts.phone_norm 由 zp_phone_norm 生成（保留数字+加号、截断20），
     * 故此处用同一算法（ContactNormalizer::phoneMain）归一化后按 phone_norm 等值比对，
     * 才能命中主库既有记录并走 idx_dedup 索引。
     */
    public function findByPhone(?string $rawPhone): ?int
    {
        $norm = ContactNormalizer::phoneMain($rawPhone);
        if (strlen($norm) < 9) {   // 主站发布也要求 phone_norm ≥ 9，短号视为无效不比对
            return null;
        }
        if ($this->mode === 'api') {
            $r = $this->apiCheck(['phone_norm' => $norm]);
            return ($r['exists'] ?? false) ? (int) ($r['id'] ?? 0) : null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT id FROM ' . $this->postsTable() . ' WHERE phone_norm = :k LIMIT 1'
        );
        $stmt->execute([':k' => $norm]);
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
            "SELECT id, LPAD(HEX(simhash),16,'0') AS h FROM " . $this->postsTable() .
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

    /**
     * 采集记录写入 zhaopin_posts，origin='crawler'，返回主库新 id。
     * $job 为 cj_jobs_clean 一行（含 title/company/salary_raw/description/city/district/
     * category/contact_phone/contact_wechat/contact_name）。
     *
     * 与主站发布逻辑（app/handlers/publish.php）对齐：
     *  - phone_norm = zp_phone_norm(phone)（保留数字+加号、截断20）
     *  - content_hash = zp_content_hash(content)（去空白+小写后 SHA-256）
     *  - simhash 由最终 content 计算（与 backfill/主站语义一致）
     *  - created_at/bumped_at 存 UTC（主站库内统一 UTC，见 zp_now）
     *  - type=1(招聘) / poster_type=1(游客) / status=1(在线)，均可配置
     */
    public function insertJob(array $job): int
    {
        $pdo = $this->pdo();

        $content    = $this->buildContent($job);
        $phone      = (string) ($job['contact_phone'] ?? '');
        $phoneNorm  = ContactNormalizer::phoneMain($phone);   // 主库兼容归一化
        $simhash    = SimHash::compute($content);             // 与 backfill 同源：对最终 content 取指纹
        $regionId   = $this->resolveRegionId($job['city'] ?? null, $job['district'] ?? null);
        $categoryId = $this->resolveCategoryId($job['category'] ?? null);
        $nowUtc     = gmdate('Y-m-d H:i:s');                  // == 主站 zp_now()

        $sql = 'INSERT INTO ' . $this->postsTable() . '
                (public_code, type, content, content_hash, contact_name, phone, phone_norm,
                 wechat, region_id, category_id, poster_type, user_id, simhash, status,
                 origin, created_at, bumped_at)
                VALUES
                (:public_code, :type, :content, :content_hash, :contact_name, :phone, :phone_norm,
                 :wechat, :region_id, :category_id, :poster_type, NULL, :simhash, :status,
                 \'crawler\', :created_at, :bumped_at)';

        $stmt = $pdo->prepare($sql);
        $params = [
            ':type'         => (int) ($this->importCfg['type'] ?? 1),
            ':content'      => $content,
            ':content_hash' => $this->contentHash($content),
            ':contact_name' => mb_substr((string) ($job['contact_name'] ?? ''), 0, 50),
            ':phone'        => mb_substr($phone, 0, 30),
            ':phone_norm'   => $phoneNorm,
            ':wechat'       => !empty($job['contact_wechat']) ? mb_substr((string) $job['contact_wechat'], 0, 60) : null,
            ':region_id'    => $regionId,
            ':category_id'  => $categoryId,
            ':poster_type'  => (int) ($this->importCfg['poster_type'] ?? 1),
            ':simhash'      => SimHash::toDb($simhash),
            ':status'       => (int) ($this->importCfg['status'] ?? 1),
            ':created_at'   => $nowUtc,
            ':bumped_at'    => $nowUtc,
        ];

        // public_code 唯一，冲突则重试
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $stmt->execute([':public_code' => $this->generatePublicCode()] + $params);
                return (int) $pdo->lastInsertId();
            } catch (\PDOException $e) {
                if ($attempt < 4 && str_contains($e->getMessage(), 'uk_public_code')) {
                    continue;
                }
                throw $e;
            }
        }
        throw new \RuntimeException('public_code 连续冲突，导入中止');
    }

    /** 合并采集字段为主库单一 content(varchar 1000)。 */
    private function buildContent(array $job): string
    {
        $parts = [];
        if (!empty($job['title'])) {
            $parts[] = trim((string) $job['title']);
        }
        if (!empty($job['company'])) {
            $parts[] = '【' . trim((string) $job['company']) . '】';
        }
        if (!empty($job['salary_raw'])) {
            $parts[] = '薪资：' . trim((string) $job['salary_raw']);
        }
        if (!empty($job['description'])) {
            $parts[] = trim((string) $job['description']);
        }
        $content = trim(implode("\n", $parts));
        if ($content === '') {
            $content = '（采集导入，无正文）';
        }
        return mb_substr($content, 0, 1000);
    }

    /**
     * content_hash：主站精确去重键。
     * ⚠ 必须与主站 zp_content_hash() 一致：去掉所有空白、转小写后 SHA-256，
     * 否则导入记录不会与主站相同内容的用户发帖精确碰撞（idx_dedup: phone_norm+content_hash）。
     */
    private function contentHash(string $content): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', '', $content) ?? ''));
    }

    /** 城市/区域名称 → region_id；查不到用兜底配置。 */
    private function resolveRegionId(?string $city, ?string $district): int
    {
        if ($this->regionMap === null) {
            $this->regionMap = [];
            $table = $this->table('regions_table', 'zhaopin_regions');
            foreach ($this->pdo()->query("SELECT id, name FROM $table")->fetchAll() as $r) {
                $this->regionMap[$this->mapKey($r['name'])] = (int) $r['id'];
            }
        }
        foreach ([$district, $city] as $name) {   // 优先更细的区域
            if ($name !== null && $name !== '') {
                $id = $this->regionMap[$this->mapKey($name)] ?? null;
                if ($id !== null) {
                    return $id;
                }
            }
        }
        return (int) ($this->importCfg['default_region_id'] ?? 0);
    }

    /** 分类名称 → category_id；查不到用兜底配置。 */
    private function resolveCategoryId(?string $category): int
    {
        if ($this->categoryMap === null) {
            $this->categoryMap = [];
            $table = $this->table('categories_table', 'zhaopin_categories');
            foreach ($this->pdo()->query("SELECT id, name FROM $table")->fetchAll() as $r) {
                $this->categoryMap[$this->mapKey($r['name'])] = (int) $r['id'];
            }
        }
        if ($category !== null && $category !== '') {
            $id = $this->categoryMap[$this->mapKey($category)] ?? null;
            if ($id !== null) {
                return $id;
            }
        }
        return (int) ($this->importCfg['default_category_id'] ?? 0);
    }

    private function mapKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * 生成 10 位 public_code（不可枚举，去易混字符 0O1lI）。
     * 字母表与主站 zp_public_code() 一致。
     */
    private function generatePublicCode(): string
    {
        $alphabet = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $code = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < 10; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        return $code;
    }

    // ---------- 清理（仅 Purger 调用） ----------

    /**
     * 删除主库对应记录。origin='crawler' 作双保险，防止误删自有 UGC（文档 §8）。
     * 返回是否确实删除。
     */
    public function deleteCrawlerJob(int $mainJobId): bool
    {
        $stmt = $this->pdo()->prepare(
            'DELETE FROM ' . $this->postsTable() . " WHERE id = :id AND origin = 'crawler'"
        );
        $stmt->execute([':id' => $mainJobId]);
        return $stmt->rowCount() > 0;
    }
}
