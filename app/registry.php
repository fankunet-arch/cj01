<?php
declare(strict_types=1);

/**
 * 注册表：功能名 → 处理器文件（路径相对 app/handlers/）。
 * 兼作白名单：仅登记在册的功能名可被 zp_dispatch() 调起，杜绝"猜文件名"探测。
 * 入口清单与 docs/02_技术架构.md §5 一一对应。
 */
return [
    // 前台
    'home'         => 'home.php',
    'list'         => 'list.php',
    'detail'       => 'detail.php',
    'publish'      => 'publish.php',
    'report'       => 'report.php',
    'mark_invalid' => 'mark_invalid.php',
    'get_contact'  => 'get_contact.php',
    'privacy'      => 'privacy.php',

    // 用户后台
    'user.login'      => 'user/login.php',
    'user.my_posts'   => 'user/my_posts.php',
    'user.edit'       => 'user/edit.php',
    'user.redeem'     => 'user/redeem.php',
    'user.use_coupon' => 'user/use_coupon.php',
    'user.logout'     => 'user/logout.php',

    // 管理员后台（约定入口：/c/cp/）
    'admin.login'      => 'admin/login.php',
    'admin.logout'     => 'admin/logout.php',
    'admin.posts'      => 'admin/posts.php',
    'admin.post_edit'  => 'admin/post_edit.php',
    'admin.admins'     => 'admin/admins.php',
    'admin.categories' => 'admin/categories.php',
    'admin.reports'    => 'admin/reports.php',
    'admin.coupons'    => 'admin/coupons.php',
    'admin.invalid'    => 'admin/invalid.php',
    'admin.dashboard'  => 'admin/dashboard.php',
    'admin.settings'   => 'admin/settings.php',
];
