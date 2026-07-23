<?php
/**
 * 数据清理（CLI，文档 §8 “用完即删”）。
 *
 * 用法：
 *   php app/bin/purge.php --mode=main                     # 依 cj_import_map 精准清理主库全部导入
 *   php app/bin/purge.php --mode=main --batch=20260801-x  # 按导入批次清理主库
 *   php app/bin/purge.php --mode=main --dry-run           # 预览
 *   php app/bin/purge.php --mode=expired                  # 按 purge_after 到期清理采集库
 *   php app/bin/purge.php --mode=site --site=oulang       # 按站清理采集库
 *   php app/bin/purge.php --mode=all                      # 清空全部采集数据（需先清完主库）
 *
 * 顺序约束（§8）：先 --mode=main 处理完主库，再 --mode=all 删采集库。
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require dirname(__DIR__) . '/bootstrap.php';

use Cj\Purge\Purger;

$options = getopt('', ['mode:', 'batch::', 'site::', 'dry-run', 'force']);
$mode = $options['mode'] ?? '';
$purger = new Purger();

try {
    switch ($mode) {
        case 'main':
            $r = $purger->purgeMain($options['batch'] ?? null, isset($options['dry-run']));
            printf("主库清理：deleted=%d skipped=%d / 账本 %d 条\n", $r['deleted'], $r['skipped'], $r['total']);
            break;

        case 'expired':
            printf("到期清理：%d 条\n", $purger->purgeExpired());
            break;

        case 'site':
            $site = (string) ($options['site'] ?? '');
            if ($site === '') {
                fwrite(STDERR, "--mode=site 需要 --site=<站点标识>\n");
                exit(1);
            }
            printf("[%s] 按站清理：%d 条\n", $site, $purger->purgeSite($site));
            break;

        case 'all':
            $purger->purgeAll(isset($options['force']));
            echo "采集库已清空。下一步：摘除 cron、归档下线采集模块。\n";
            break;

        default:
            fwrite(STDERR, "用法：php purge.php --mode=main|expired|site|all [--batch=] [--site=] [--dry-run] [--force]\n");
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, '清理中止：' . $e->getMessage() . "\n");
    exit(1);
}
