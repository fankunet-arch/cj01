<?php
/**
 * 一次性脚本：回填 zhaopin 主库 zhaopin_posts 存量记录的 simhash
 * （文档 §11 改动一的配套；执行前主库需已跑 db/02_zhaopin_main_ddl_patch.sql）。
 *
 * 真实主库已自带 phone_norm（NOT NULL，全量已填），故本脚本只回填 simhash。
 * simhash 由 content 计算，与采集器同一算法（Cj\Dedup\SimHash）。
 * 需要 main.mode=db 且账号对 zhaopin_posts 有 UPDATE 权限。
 *
 * 用法：
 *   php app/bin/backfill_main.php --dry-run
 *   php app/bin/backfill_main.php --batch-size=500
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require dirname(__DIR__) . '/bootstrap.php';

use Cj\Dedup\SimHash;
use Cj\Support\Db;

$options = getopt('', ['dry-run', 'batch-size::']);
$dryRun = isset($options['dry-run']);
$batchSize = max(50, (int) ($options['batch-size'] ?? 500));

$mainCfg = cj_config('main');
if (($mainCfg['mode'] ?? 'off') !== 'db') {
    fwrite(STDERR, "回填需 main.mode=db（config.php → main）\n");
    exit(1);
}
$table = preg_replace('/[^A-Za-z0-9_]/', '', $mainCfg['db']['posts_table'] ?? 'zhaopin_posts');

$pdo = Db::main();
$lastId = 0;
$updated = 0;

while (true) {
    $stmt = $pdo->prepare(
        "SELECT id, content
         FROM $table
         WHERE id > :last AND simhash IS NULL
         ORDER BY id ASC LIMIT $batchSize"
    );
    $stmt->execute([':last' => $lastId]);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        break;
    }

    foreach ($rows as $row) {
        $lastId = (int) $row['id'];
        $simhash = SimHash::compute((string) ($row['content'] ?? ''));

        if ($dryRun) {
            printf("[dry-run] #%d simhash=%s\n", $lastId, SimHash::toDb($simhash));
            continue;
        }
        $pdo->prepare("UPDATE $table SET simhash = :sh WHERE id = :id")
            ->execute([':sh' => SimHash::toDb($simhash), ':id' => $lastId]);
        $updated++;
    }
}

echo $dryRun ? "预览完成。\n" : "回填完成：$updated 条。\n";
