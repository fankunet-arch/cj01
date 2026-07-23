<?php require __DIR__ . '/_nav.php'; /** @var array $rows @var int $meId @var bool $couponSwitch */ ?>
  <div class="shell a-shell">
    <?php if (($notice ?? '') !== ''): ?><p class="a-saved">✓ <?= zp_e($notice) ?></p><?php endif; ?>

    <h2 class="a-h2">权限开关</h2>
    <form class="a-filter" method="post" action="/c/cp/admins">
      <input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>">
      <input type="hidden" name="action" value="coupon_switch">
      <input type="hidden" name="value" value="<?= $couponSwitch ? '0' : '1' ?>">
      <span>普通管理员管理置顶券：<b><?= $couponSwitch ? '允许' : '禁止' ?></b></span>
      <button class="a-btn <?= $couponSwitch ? 'danger' : 'primary' ?>" type="submit"><?= $couponSwitch ? '改为禁止' : '改为允许' ?></button>
    </form>
    <p class="a-note">普通管理员：信息管理、类别管理、举报处理、失效标记。超级管理员另有：参数配置、管理员管理、置顶券（恒可）。</p>

    <h2 class="a-h2">添加管理员</h2>
    <form class="a-filter" method="post" action="/c/cp/admins">
      <input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>">
      <input type="hidden" name="action" value="add">
      <input class="input" type="email" name="email" placeholder="对方登录 Google 用的邮箱" required style="min-width:260px">
      <select class="sel" name="role">
        <option value="1">普通管理员</option>
        <option value="2">超级管理员</option>
      </select>
      <button class="a-btn primary" type="submit">添加</button>
    </form>

    <h2 class="a-h2">管理员名单</h2>
    <div class="a-tablewrap"><table class="a-table">
      <tr><th>邮箱</th><th>角色</th><th>显示名</th><th>状态</th><th>加入时间</th><th>操作</th></tr>
      <?php foreach ($rows as $r): $self = (int) $r['id'] === $meId; ?>
      <tr class="<?= (int) $r['status'] === 0 ? 'sus' : '' ?>">
        <td><?= zp_e($r['email']) ?><?= $self ? ' <span class="flag">我</span>' : '' ?></td>
        <td><b><?= (int) $r['role'] === 2 ? '超级管理员' : '普通管理员' ?></b></td>
        <td><?= zp_e($r['display_name'] ?? '—') ?></td>
        <td><?= (int) $r['status'] === 1 ? '启用' : '已停用' ?></td>
        <td><?= zp_e(zp_time_ago($r['created_at'])) ?></td>
        <td class="c-act">
          <?php if (!$self): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="role"><button class="a-btn sm"><?= (int) $r['role'] === 2 ? '降为普管' : '升为超管' ?></button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="a-btn sm <?= (int) $r['status'] === 1 ? 'danger' : '' ?>"><?= (int) $r['status'] === 1 ? '停用' : '启用' ?></button></form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div>
  </div>
