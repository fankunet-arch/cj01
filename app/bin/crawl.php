<?php
/**
 * 采集入口（CLI，由 cron 调用，不经 Web）。
 *
 * 用法：
 *   php app/bin/crawl.php --site=oulang     # 单站
 *   php app/bin/crawl.php --all             # 全部启用站点
 *   php app/bin/crawl.php --all --force     # 跳过间隔闸门（仅调试）
 *
 * 采集间隔不能小于 1 小时：CLI 与 Web 一键采集共用 CrawlControl 闸门；
 * --no-guard 仅供 Web 触发链路内部使用（许可已在 Web 进程原子获取）。
 *
 * cron 示例（各站错开时段，§7；间隔小于 1 小时的触发会被闸门拒绝）：
 *   10 3 * * *  php /path/to/app/bin/crawl.php --site=oulang
 *   40 4 * * *  php /path/to/app/bin/crawl.php --site=huarenjie
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require dirname(__DIR__) . '/bootstrap.php';

use Cj\Scheduler\CrawlControl;
use Cj\Scheduler\CrawlRunner;
use Cj\Support\Logger;

$options = getopt('', ['site:', 'all', 'force', 'no-guard']);
$sitesDir = CJ_APP_ROOT . '/config/sites';

$targets = [];
if (isset($options['all'])) {
    foreach (glob($sitesDir . '/*.php') ?: [] as $file) {
        $targets[] = require $file;
    }
} elseif (isset($options['site'])) {
    $file = $sitesDir . '/' . preg_replace('/[^a-z0-9_]/i', '', (string) $options['site']) . '.php';
    if (!is_file($file)) {
        fwrite(STDERR, "未知站点：{$options['site']}（app/config/sites 下无对应配置）\n");
        exit(1);
    }
    $targets[] = require $file;
} else {
    fwrite(STDERR, "用法：php crawl.php --site=<站点标识> | --all [--force]\n");
    exit(1);
}

// 触发闸门：采集间隔 ≥ 1 小时 + 防并发（Web 链路已获许可时跳过）
if (!isset($options['no-guard'])) {
    $permit = CrawlControl::acquire(isset($options['force']));
    if (!$permit['ok']) {
        Logger::info('crawl', '触发被拒绝：' . $permit['message']);
        exit(1);
    }
}

$exit = 0;
foreach ($targets as $site) {
    if (!($site['enabled'] ?? false)) {
        Logger::info('crawl', "[{$site['site']}] enabled=false，跳过（P0 勘察回填选择器后开启）");
        continue;
    }
    try {
        (new CrawlRunner($site))->run();
    } catch (Throwable $e) {
        Logger::error('crawl', "[{$site['site']}] " . $e->getMessage());
        $exit = 1;
    }
}
exit($exit);
