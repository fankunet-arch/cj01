<?php
/**
 * 采集运行看板（文档 §7）：读 cj_crawl_runs 展示各站最近采集量、去重率、错误数。
 */

use Cj\Repository\CrawlerRepository;

$runs = (new CrawlerRepository())->recentRuns(30);

$pageTitle = '运行看板';
$renderBody = function () use ($runs) {
    ?>
    <div class="card">
        <h2>最近 <?= count($runs) ?> 次采集任务</h2>
        <?php if ($runs === []): ?>
            <p class="muted">暂无采集记录。cron 触发 <code>app/cj/bin/crawl.php</code> 后此处显示运行情况。</p>
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
