<?php
/**
 * 复核通过后导入主库（CLI，人工触发，不进 cron）。
 * 导入保持人工把关，不做全自动（文档 §4.5）。
 *
 * 用法：
 *   php app/bin/import.php --dry-run          # 预览将导入的记录
 *   php app/bin/import.php --limit=100        # 执行导入（默认 200 条/批）
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require dirname(__DIR__) . '/bootstrap.php';

use Cj\Import\Importer;

$options = getopt('', ['limit::', 'dry-run']);
$limit = max(1, (int) ($options['limit'] ?? 200));
$dryRun = isset($options['dry-run']);

try {
    $result = (new Importer())->run($limit, $dryRun);
    printf(
        "%s批次 %s：%d 条%s\n",
        $dryRun ? '[dry-run] ' : '',
        $result['batch'],
        $result['imported'],
        $dryRun ? '（未写库）' : ' 已导入，账本已写 cj_import_map'
    );
} catch (Throwable $e) {
    fwrite(STDERR, '导入失败：' . $e->getMessage() . "\n");
    exit(1);
}
