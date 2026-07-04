<?php
/**
 * 内部首页：模块状态一览 + 入口导航。
 */

use Cj\Support\Db;

$stats = ['total' => 0, 'unique' => 0, 'review' => 0, 'ready' => 0, 'imported' => 0];
$dbError = null;
try {
    $db = Db::crawler();
    $stats['total'] = (int) $db->query('SELECT COUNT(*) FROM cj_jobs_clean')->fetchColumn();
    $stats['unique'] = (int) $db->query("SELECT COUNT(*) FROM cj_jobs_clean WHERE dedup_status='unique'")->fetchColumn();
    $stats['review'] = (int) $db->query('SELECT COUNT(*) FROM cj_review_queue WHERE resolved=0')->fetchColumn();
    $stats['ready'] = (int) $db->query('SELECT COUNT(*) FROM cj_jobs_clean WHERE import_ready=1 AND imported_at IS NULL')->fetchColumn();
    $stats['imported'] = (int) $db->query('SELECT COUNT(*) FROM cj_import_map WHERE purged=0')->fetchColumn();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$pageTitle = '概览';
$renderBody = function () use ($stats, $dbError) {
    ?>
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
    <div class="card muted">
        采集/去重/导入/清理均走 <code>app/bin/</code> 下 CLI（cron 触发，不经 Web）。
        本页面仅为内部只读概览；导入与清理请在服务器上执行
        <code>php app/bin/import.php</code> / <code>php app/bin/purge.php</code>。
    </div>
    <?php
};

require __DIR__ . '/layout.php';
