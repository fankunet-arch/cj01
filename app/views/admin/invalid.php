<?php require __DIR__ . '/_nav.php'; ?>
  <div class="shell a-shell">
    <p class="a-note">浏览者的善意纠错（空号/岗位没了）。累计 ≥ <?= (int) $threshold ?> 次自动高亮（阈值在参数配置可调）。</p>
    <div class="a-tablewrap"><table class="a-table">
      <tr><th>信息</th><th>失效标记数</th><th>地区 / 类别</th><th>状态</th><th>操作</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr class="<?= (int) $r['invalid_count'] >= $threshold ? 'sus' : '' ?>">
        <td class="c-content"><a class="a-link" href="/detail?id=<?= zp_e($r['public_code']) ?>" target="_blank"><?= zp_e(zp_post_excerpt($r['content'], 28)) ?></a></td>
        <td><b><?= (int) $r['invalid_count'] ?></b> 次</td>
        <td><?= zp_e($r['region_name']) ?> / <?= zp_e($r['category_name']) ?></td>
        <td><?= zp_e(zp_post_status_label((int) $r['status'])) ?></td>
        <td class="c-act">
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="offline"><button class="a-btn sm danger">下架</button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="reset"><button class="a-btn sm">误报·清零</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?><tr><td colspan="5" class="empty">没有被标记失效的信息</td></tr><?php endif; ?>
    </table></div>
  </div>
