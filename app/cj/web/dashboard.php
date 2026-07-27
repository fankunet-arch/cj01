<?php
/**
 * 采集运行看板（文档 §7）：读 cj_crawl_runs 展示各站最近采集量、去重率、错误数。
 */

use Cj\Repository\CrawlerRepository;
use Cj\Scheduler\CrawlControl;

// --- 强制重置拦截逻辑 ---
$sysMessage = '';
$sysMessageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'force_reset') {
    $res = CrawlControl::forceReset();
    $sysMessage = $res['message'];
    $sysMessageType = $res['ok'] ? 'success' : 'error';
}

$runs = (new CrawlerRepository())->recentRuns(30);

$pageTitle = '运行看板';
$renderBody = function () use ($runs, $sysMessage, $sysMessageType) {
    ?>
    
    <?php if ($sysMessage !== ''): ?>
        <div class="card" style="border-left: 4px solid <?= $sysMessageType === 'success' ? '#28a745' : '#dc3545' ?>; margin-bottom: 15px;">
            <p style="margin: 0; padding: 5px 0;"><strong>系统提示：</strong> <?= cj_e($sysMessage) ?></p>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;">最近 <?= count($runs) ?> 次采集任务</h2>
            
            <!-- 强制重置操作按钮 -->
            <form method="post" style="margin: 0;" onsubmit="return confirm('警告：此操作将强制杀死当前正在执行的采集进程，并物理清理数据库中的僵尸记录，最后采集时间将不被记录！\n\n仅在任务彻底卡死时使用。确认执行？');">
                <input type="hidden" name="action" value="force_reset">
                <button type="submit" style="background: #dc3545; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                    🚨 强制中断/急救重置
                </button>
            </form>
        </div>
        
        <?php if ($runs === []): ?>
            <p class="muted">暂无采集记录。在<a href="<?= cj_e(cj_url('index.php')) ?>">概览页</a>点「同步采集一批」（虚拟主机适用），或 cron 触发 <code>app/cj/bin/crawl.php</code> 后，此处显示运行情况。</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>#</th><th>站点</th><th>开始</th><th>结束</th>
                    <th>页数</th><th>新增</th><th>判重</th><th>去重率</th><th>错误</th><th>状态</th><th>备注</th>
                </tr>
                <?php foreach ($runs as $r): ?>
                    <?php
                    $processed = (int) $r['new_jobs'] + (int) $r['dup_jobs'];
                    $dupRate = $processed > 0 ? sprintf('%.0f%%', 100 * $r['dup_jobs'] / $processed) : '—';
                    ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><span class="pill"><?= cj_e($r['source_site']) ?></span></td>
                        <td class="muted"><?= cj_e($r['started_at']) ?></td>
                        <td class="muted"><?= cj_e($r['finished_at'] ?? '—') ?></td>
                        <td><?= (int) $r['pages_fetched'] ?></td>
                        <td><?= (int) $r['new_jobs'] ?></td>
                        <td><?= (int) $r['dup_jobs'] ?></td>
                        <td><?= $dupRate ?></td>
                        <td><?= (int) $r['errors'] ?></td>
                        <td class="status-<?= cj_e($r['status']) ?>"><?= cj_e($r['status']) ?></td>
                        <td class="muted"><?= cj_e($r['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    <?php
};

require __DIR__ . '/layout.php';
