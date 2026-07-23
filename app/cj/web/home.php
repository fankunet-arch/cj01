<?php
/**
 * 内部首页：模块状态一览 + 操作面板。
 *  - 一键采集：后台拉起 CLI 全站采集，触发间隔硬性 ≥ 1 小时（CrawlControl 闸门）
 *  - 导入主库：采集完成后不会自动导入，必须在此人工点击确认
 */

use Cj\Import\Importer;
use Cj\Repository\CrawlerRepository;
use Cj\Scheduler\CrawlControl;
use Cj\Support\Db;

$flash = null;   // ['ok' => bool, 'text' => string]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'crawl') {
        $r = CrawlControl::trigger();
        $flash = ['ok' => $r['ok'], 'text' => $r['message']];
    } elseif ($action === 'import') {
        try {
            $limit = max(1, min(1000, (int) ($_POST['limit'] ?? 200)));
            $r = (new Importer())->run($limit);
            $flash = ['ok' => true, 'text' =>
                $r['imported'] > 0
                    ? sprintf('导入完成：批次 %s，共 %d 条已写入主库并记入 cj_import_map 账本', $r['batch'], $r['imported'])
                    : '没有待导入的记录（import_ready=1 且未导入的记录为 0）'];
        } catch (Throwable $e) {
            $flash = ['ok' => false, 'text' => '导入失败：' . $e->getMessage()];
        }
    }
}

$stats = ['total' => 0, 'unique' => 0, 'review' => 0, 'ready' => 0, 'imported' => 0];
$dbError = null;
try {
    $db = Db::crawler();
    $repo = new CrawlerRepository($db);
    $stats['total'] = (int) $db->query('SELECT COUNT(*) FROM cj_jobs_clean')->fetchColumn();
    $stats['unique'] = (int) $db->query("SELECT COUNT(*) FROM cj_jobs_clean WHERE dedup_status='unique'")->fetchColumn();
    $stats['review'] = (int) $db->query('SELECT COUNT(*) FROM cj_review_queue WHERE resolved=0')->fetchColumn();
    $stats['ready'] = $repo->pendingImportCount();
    $stats['imported'] = (int) $db->query('SELECT COUNT(*) FROM cj_import_map WHERE purged=0')->fetchColumn();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$crawlStatus = CrawlControl::status();

$pageTitle = '概览';
$renderBody = function () use ($stats, $dbError, $flash, $crawlStatus) {
    ?>
    <?php if ($flash !== null): ?>
        <div class="card <?= $flash['ok'] ? 'status-ok' : 'status-failed' ?>"><?= cj_e($flash['text']) ?></div>
    <?php endif; ?>

    <?php if ($dbError !== null): ?>
        <div class="card"><strong class="status-failed">采集库连接失败：</strong><?= cj_e($dbError) ?></div>
    <?php else: ?>
        <div class="card">
            <table>
                <tr>
                    <th>采集总量</th><th>唯一记录</th><th>待复核</th><th>待导入</th><th>已导入主库（未清理）</th>
                </tr>
                <tr>
                    <td><?= $stats['total'] ?></td>
                    <td><?= $stats['unique'] ?></td>
                    <td><?= $stats['review'] ?></td>
                    <td><?= $stats['ready'] ?></td>
                    <td><?= $stats['imported'] ?></td>
                </tr>
            </table>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>采集</h2>
        <form method="post" onsubmit="return confirm('确认开始一键采集全部启用站点？');">
            <input type="hidden" name="action" value="crawl">
            <button class="btn btn-primary" type="submit" <?= $crawlStatus['can_trigger'] ? '' : 'disabled' ?>>
                <?= $crawlStatus['running'] ? '采集运行中…' : '一键采集' ?>
            </button>
            <?php if (!$crawlStatus['can_trigger'] && $crawlStatus['reason'] !== null): ?>
                <span class="muted"><?= cj_e($crawlStatus['reason']) ?></span>
            <?php endif; ?>
        </form>
        <p class="muted">
            上次采集：<?= cj_e($crawlStatus['last_triggered'] ?? '从未') ?>
            <?php if ($crawlStatus['next_allowed_at'] !== null): ?>
                · 下次可触发：<?= cj_e($crawlStatus['next_allowed_at']) ?>
            <?php endif; ?>
            · 采集间隔不能小于 1 小时（Web 与 cron 共用同一闸门）。进度见<a href="<?= cj_e(cj_url('dashboard.php')) ?>">运行看板</a>。
        </p>
    </div>

    <div class="card">
        <h2>导入主库</h2>
        <form method="post"
              onsubmit="return confirm('确认将待导入记录写入 zhaopin 主库？此操作会写 cj_import_map 账本，主库记录标记 origin=crawler。');">
            <input type="hidden" name="action" value="import">
            <label>本批上限
                <input type="number" name="limit" value="200" min="1" max="1000" style="width:70px">
            </label>
            <button class="btn btn-primary" type="submit" <?= $stats['ready'] > 0 ? '' : 'disabled' ?>>
                导入主库（待导入 <?= $stats['ready'] ?> 条）
            </button>
        </form>
        <p class="muted">
            采集完成后<strong>不会自动导入</strong> zhaopin 主站数据库——去重通过的记录仅标记为待导入，
            须在此人工点击确认（或在服务器执行 <code>php app/bin/import.php</code>）。
            低置信度记录先过<a href="<?= cj_e(cj_url('review.php')) ?>">复核队列</a>，复核“保留”后才进入待导入。
        </p>
    </div>
    <?php
};

require __DIR__ . '/layout.php';
