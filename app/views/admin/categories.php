<?php require __DIR__ . '/_nav.php'; ?>
  <div class="shell a-shell">
    <?php if (($notice ?? '') !== ''): ?><p class="a-saved">✓ <?= zp_e($notice) ?></p><?php endif; ?>

    <h2 class="a-h2">待审核类别（用户录入）</h2>
    <div class="a-tablewrap"><table class="a-table">
      <tr><th>名称</th><th>提交时间</th><th>操作</th></tr>
      <?php foreach ($pending as $r): ?>
      <tr>
        <td><b><?= zp_e($r['name']) ?></b></td>
        <td><?= zp_e(zp_time_ago($r['submitted_at'])) ?></td>
        <td class="c-act">
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="approve"><button class="a-btn sm">通过并入正式表</button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="reject"><button class="a-btn sm danger">驳回</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($pending === []): ?><tr><td colspan="3" class="empty">没有待审核的类别</td></tr><?php endif; ?>
    </table></div>

    <h2 class="a-h2">新增类别</h2>
    <form class="a-filter" method="post" action="/c/cp/categories">
      <input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>">
      <input type="hidden" name="action" value="add">
      <input class="input" type="text" name="name" maxlength="30" placeholder="类别名称" required>
      <input class="input" type="number" name="sort" value="500" style="width:90px" title="排序（小的靠前，999 为兜底「其他」）">
      <button class="a-btn primary" type="submit">新增</button>
    </form>

    <h2 class="a-h2">正式类别（可改名 / 调排序 / 启停）</h2>
    <div class="a-tablewrap"><table class="a-table">
      <tr><th>名称</th><th>排序</th><th>在线帖数</th><th>状态</th><th>操作</th></tr>
      <?php foreach ($active as $r): $fid = 'cf' . (int) $r['id']; ?>
      <tr class="<?= (int) $r['status'] === 0 ? 'sus' : '' ?>">
        <td><input class="input" type="text" name="name" maxlength="30" value="<?= zp_e($r['name']) ?>" style="min-width:130px" form="<?= $fid ?>"></td>
        <td><input class="input" type="number" name="sort" value="<?= (int) $r['sort'] ?>" style="width:80px" form="<?= $fid ?>"></td>
        <td><?= (int) $r['post_count'] ?></td>
        <td><?= (int) $r['status'] === 1 ? '启用' : '<b>已停用</b>' ?></td>
        <td class="c-act">
          <button class="a-btn sm" name="action" value="update" form="<?= $fid ?>">保存</button>
          <button class="a-btn sm <?= (int) $r['status'] === 1 ? 'danger' : '' ?>" name="action" value="toggle" form="<?= $fid ?>"
            <?= (int) $r['status'] === 1 && (int) $r['post_count'] > 0 ? 'onclick="return confirm(\'该类别下还有在线信息，停用后发布表单不再出现此类别（已发信息不受影响）。继续？\')"' : '' ?>>
            <?= (int) $r['status'] === 1 ? '停用' : '启用' ?></button>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php foreach ($active as $r): ?>
    <form id="cf<?= (int) $r['id'] ?>" method="post" action="/c/cp/categories"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"></form>
    <?php endforeach; ?>
    <p class="a-note">说明：停用只是从发布表单里隐藏，不影响已发布信息；类别下有在线帖时建议先改名而不是停用。</p>
  </div>
