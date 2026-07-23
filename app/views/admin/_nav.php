<?php /** @var array $admin @var string $nav_active */
$isSuper = (int) ($admin['role'] ?? 1) === 2;
$items = [
    'dashboard'  => ['/c/cp/',           '看板'],
    'posts'      => ['/c/cp/posts',      '信息管理'],
    'categories' => ['/c/cp/categories', '类别管理'],
    'reports'    => ['/c/cp/reports',    '举报处理'],
    'invalid'    => ['/c/cp/invalid',    '失效标记'],
];
if (zp_admin_can_coupon()) {
    $items['coupons'] = ['/c/cp/coupons', '置顶券'];
}
if ($isSuper) {
    $items['admins']   = ['/c/cp/admins',   '管理员'];
    $items['settings'] = ['/c/cp/settings', '参数配置'];
}
?>
  <nav class="nav">
    <a class="brand" href="/c/cp/"><span class="zh">西华<span class="hl">招聘</span></span></a>
    <span class="a-badge"><?= $isSuper ? '超级管理员' : '管理后台' ?></span>
    <span class="spacer"></span>
    <span class="a-user"><?= zp_e($admin['email']) ?></span>
    <a class="a-out" href="/c/cp/logout">退出</a>
  </nav>
  <div class="a-tabs">
    <?php foreach ($items as $key => [$url, $label]): ?>
    <a href="<?= zp_e($url) ?>" class="<?= $key === $nav_active ? 'on' : '' ?>"><?= zp_e($label) ?></a>
    <?php endforeach; ?>
  </div>
