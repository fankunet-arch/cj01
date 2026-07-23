<?php
/** 导航登录态局部：未登录显示"登录/注册"（带回跳地址），已登录显示用户名→我的发布。 */
$navUser = zp_user();
$navBack = urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/'));
?>
<?php if ($navUser !== null): ?>
    <a class="nav-user in" href="/user/my_posts" title="我的发布">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>
      <?= zp_e($navUser['name']) ?>
    </a>
<?php else: ?>
    <a class="nav-user" href="/user/login?back=<?= $navBack ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>
      登录 / 注册
    </a>
<?php endif; ?>
