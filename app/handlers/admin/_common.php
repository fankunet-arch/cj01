<?php
declare(strict_types=1);

/** 管理后台公共：鉴权门卫 + 统一渲染（带侧栏导航的 admin 视图）。 */
function zp_admin_page(string $view, string $title, array $data = []): void
{
    $data['title'] = $title . ' · 管理后台';
    $data['page'] = 'admin';
    $data['admin'] = zp_require_admin();
    $data['nav_active'] = $view;
    $data['csrf'] = zp_csrf_token();
    zp_render('admin/' . $view, $data);
}

/** 帖子状态文案。 */
function zp_post_status_label(int $status): string
{
    return [1 => '在线', 0 => '已到期', 2 => '已下架', 3 => '已删除'][$status] ?? (string) $status;
}
