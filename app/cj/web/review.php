<?php
/**
 * 人工复核队列（文档 §3.5）：低置信度判重记录在此人工定夺。
 *  keep    = 不是重复，保留并标记可导入
 *  discard = 确认重复，按跨站重复丢弃
 *  merge   = 同源改文案，保留数据但不导入
 */

use Cj\Repository\CrawlerRepository;

$repo = new CrawlerRepository();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $queueId = (int) ($_POST['queue_id'] ?? 0);
    $resolution = (string) ($_POST['resolution'] ?? '');
    if ($queueId > 0 && in_array($resolution, ['keep', 'merge', 'discard'], true)) {
        $repo->resolveReview($queueId, $resolution);
        $message = "队列 #$queueId 已处理：$resolution";
    }
}

$items = $repo->pendingReviews(50);

$pageTitle = '复核队列';
$renderBody = function () use ($items, $message) {
    ?>
    <?php if ($message !== null): ?>
        <div class="card status-ok"><?= cj_e($message) ?></div>
    <?php endif; ?>
    <div class="card">
        <h2>待复核（<?= count($items) ?>）</h2>
        <?php if ($items === []): ?>
            <p class="muted">队列为空。</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>#</th><th>待复核记录</th><th>疑似重复对象</th><th>原因</th><th>操作</th>
                </tr>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= (int) $it['queue_id'] ?></td>
                        <td>
                            <span class="pill"><?= cj_e($it['source_site']) ?></span>
                            <strong><?= cj_e($it['title'] ?? '(无标题)') ?></strong>
                            <div class="muted">
                                <?= cj_e($it['city'] ?? '') ?> · <?= cj_e((string) ($it['publish_date'] ?? '')) ?> ·
                                <a href="<?= cj_e($it['source_url']) ?>" target="_blank" rel="noopener noreferrer">原文</a>
                            </div>
                        </td>
                        <td>
                            <?php if ($it['cand_id'] !== null): ?>
                                <span class="pill"><?= cj_e($it['cand_site']) ?></span>
                                <?= cj_e($it['cand_title'] ?? '(无标题)') ?>
                                <div class="muted">
                                    <a href="<?= cj_e($it['cand_url']) ?>" target="_blank" rel="noopener noreferrer">原文</a>
                                </div>
                            <?php else: ?>
                                <span class="muted">—（主库或未记录）</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted"><?= cj_e($it['reason']) ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="queue_id" value="<?= (int) $it['queue_id'] ?>">
                                <button class="btn" name="resolution" value="keep">保留</button>
                                <button class="btn" name="resolution" value="merge">合并</button>
                                <button class="btn btn-danger" name="resolution" value="discard">丢弃</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    <?php
};

require __DIR__ . '/layout.php';
