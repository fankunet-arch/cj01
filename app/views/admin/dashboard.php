<?php require __DIR__ . '/_nav.php'; ?>
  <div class="shell a-shell">
    <div class="a-stats">
      <?php foreach ($stats as $label => $value): ?>
      <div class="stat"><div class="v"><?= (int) $value ?></div><div class="l"><?= zp_e($label) ?></div></div>
      <?php endforeach; ?>
    </div>

    <h2 class="a-h2">最近发布</h2>
    <div class="a-tablewrap"><table class="a-table">
      <tr><th>板块</th><th>内容</th><th>联系人</th><th>地区 / 类别</th><th>状态</th><th>时间</th><th></th></tr>
      <?php foreach ($recent as $r): ?>
      <tr class="<?= (int) $r['suspicious'] === 1 ? 'sus' : '' ?>">
        <td><?= (int) $r['type'] === 1 ? '招聘' : '求职' ?></td>
        <td class="c-content"><?= zp_e(zp_post_excerpt($r['content'], 30)) ?><?= (int) $r['suspicious'] === 1 ? ' <span class="flag">可疑</span>' : '' ?></td>
        <td><?= zp_e($r['contact_name']) ?></td>
        <td><?= zp_e($r['region_name']) ?> / <?= zp_e($r['category_name']) ?></td>
        <td><?= zp_e(zp_post_status_label((int) $r['status'])) ?></td>
        <td><?= zp_e(zp_time_ago($r['created_at'])) ?></td>
        <td><a class="a-link" href="/detail?id=<?= zp_e($r['public_code']) ?>" target="_blank">查看</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if ($recent === []): ?><tr><td colspan="7" class="empty">还没有任何发布</td></tr><?php endif; ?>
    </table></div>
  </div>
