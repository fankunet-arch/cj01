<?php require __DIR__ . '/_nav.php'; ?>
  <div class="shell a-shell">
    <div class="a-filter">
      <a class="a-btn sm <?= !$showAll ? 'primary' : '' ?>" href="/c/cp/reports">待处理</a>
      <a class="a-btn sm <?= $showAll ? 'primary' : '' ?>" href="/c/cp/reports?all=1">全部</a>
    </div>
    <div class="a-tablewrap"><table class="a-table">
      <tr><th>被举报信息</th><th>理由</th><th>举报时间</th><th>帖子状态</th><th>处理</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="c-content"><a class="a-link" href="/detail?id=<?= zp_e($r['public_code']) ?>" target="_blank"><?= zp_e(zp_post_excerpt($r['content'], 26)) ?></a><br><small><?= zp_e($r['contact_name']) ?></small></td>
        <td><?= zp_e($r['reason'] ?? '—') ?></td>
        <td><?= zp_e(zp_time_ago($r['created_at'])) ?></td>
        <td><?= zp_e(zp_post_status_label((int) $r['post_status'])) ?></td>
        <td class="c-act">
          <?php if ((int) $r['status'] === 0): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="handle"><input type="hidden" name="offline" value="1"><button class="a-btn sm danger">属实·下架帖子</button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="handle"><button class="a-btn sm">已处理</button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="ignore"><button class="a-btn sm">忽略</button></form>
          <?php else: ?>
          <?= (int) $r['status'] === 1 ? '已处理' : '已忽略' ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?><tr><td colspan="5" class="empty">没有待处理的举报 🎉</td></tr><?php endif; ?>
    </table></div>
  </div>
