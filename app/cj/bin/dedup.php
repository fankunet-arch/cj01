<?php
/**
 * 独立去重批处理（CLI，可选）：对存量 cj_jobs_clean 重跑二级/三级去重。
 * 适用场景：调整阈值后重判、主库比对（P4）接通后回扫存量。
 *
 * 用法：
 *   php app/bin/dedup.php            # 重判全部 unique/review 记录
 *   php app/bin/dedup.php --limit=100
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require dirname(__DIR__) . '/bootstrap.php';

use Cj\Dedup\DedupEngine;
use Cj\Dedup\SimHash;
use Cj\Repository\CrawlerRepository;
use Cj\Support\Db;
use Cj\Support\Logger;

$options = getopt('', ['limit::']);
$limit = max(1, (int) ($options['limit'] ?? 5000));

$db = Db::crawler();
$repo = new CrawlerRepository($db);
$engine = new DedupEngine($repo);

$rows = $db->query(
    "SELECT id, contact_key, phone_norm, LPAD(HEX(simhash),16,'0') AS h, title, publish_date
     FROM cj_jobs_clean
     WHERE dedup_status IN ('unique','review') AND imported_at IS NULL
     ORDER BY id ASC LIMIT " . $limit
)->fetchAll();

$changed = 0;
foreach ($rows as $row) {
    $verdict = $engine->judge([
        'self_id'      => (int) $row['id'],   // 排除与自身比对
        'contact_key'  => $row['contact_key'],
        'phone_norm'   => $row['phone_norm'],
        'simhash'      => $row['h'] !== null ? SimHash::fromHex($row['h']) : 0,
        'title'        => $row['title'],
        'publish_date' => $row['publish_date'],
    ]);
    $engine->flushLogs((int) $row['id']);

    if ($verdict['status'] !== 'unique') {
        $db->prepare(
            'UPDATE cj_jobs_clean SET dedup_status = :s, confidence = :c,
                    import_ready = IF(:s2 = \'unique\', import_ready, 0)
             WHERE id = :id'
        )->execute([
            ':s' => $verdict['status'], ':c' => $verdict['confidence'],
            ':s2' => $verdict['status'], ':id' => $row['id'],
        ]);
        if ($verdict['status'] === 'review') {
            $repo->queueReview((int) $row['id'], $verdict['review_candidate'], (string) $verdict['review_reason']);
        }
        $changed++;
    }
}

Logger::info('dedup', '批处理完成：扫描 ' . count($rows) . " 条，状态变更 $changed 条");
