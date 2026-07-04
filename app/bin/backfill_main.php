<?php
/**
 * 一次性脚本：回填 zhaopin 主库存量招聘记录的 contact_key / simhash
 * （文档 §11 改动一的配套；执行前主库需已跑 db/02_zhaopin_main_ddl_patch.sql）。
 * 需要 main.mode=db 且账号对招聘表有 UPDATE 权限。
 *
 * 用法：
 *   php app/bin/backfill_main.php --dry-run
 *   php app/bin/backfill_main.php --batch-size=500
 *
 * 主库字段名假设：phone/wechat/title/company/description，与实际不符时在下方 SQL 调整。
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require dirname(__DIR__) . '/bootstrap.php';

use Cj\Dedup\SimHash;
use Cj\Normalizer\ContactNormalizer;
use Cj\Support\Db;

$options = getopt('', ['dry-run', 'batch-size::']);
$dryRun = isset($options['dry-run']);
$batchSize = max(50, (int) ($options['batch-size'] ?? 500));

$mainCfg = cj_config('main');
if (($mainCfg['mode'] ?? 'off') !== 'db') {
    fwrite(STDERR, "回填需 main.mode=db（config.php → main）\n");
    exit(1);
}
$table = preg_replace('/[^A-Za-z0-9_]/', '', $mainCfg['db']['jobs_table'] ?? 'jobs');

$pdo = Db::main();
$lastId = 0;
$updated = 0;

while (true) {
    $stmt = $pdo->prepare(
        "SELECT id, phone, wechat, title, company, description
         FROM $table
         WHERE id > :last AND (contact_key IS NULL OR simhash IS NULL)
         ORDER BY id ASC LIMIT $batchSize"
    );
    $stmt->execute([':last' => $lastId]);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        break;
    }

    foreach ($rows as $row) {
        $lastId = (int) $row['id'];
        $phoneNorm = ContactNormalizer::phone($row['phone'] ?? null);
        $wechatNorm = ContactNormalizer::wechat($row['wechat'] ?? null);
        $contactKey = ContactNormalizer::contactKey($phoneNorm, $wechatNorm);
        $simhash = SimHash::ofJobText($row['title'] ?? null, $row['company'] ?? null, $row['description'] ?? null);

        if ($dryRun) {
            printf("[dry-run] #%d contact_key=%s simhash=%s\n", $lastId, $contactKey ?? '(null)', SimHash::toDb($simhash));
            continue;
        }
        $pdo->prepare(
            "UPDATE $table SET contact_key = :ck, simhash = :sh WHERE id = :id"
        )->execute([':ck' => $contactKey, ':sh' => SimHash::toDb($simhash), ':id' => $lastId]);
        $updated++;
    }
}

echo $dryRun ? "预览完成。\n" : "回填完成：$updated 条。\n";
