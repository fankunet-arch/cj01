<?php
declare(strict_types=1);

// 信息详情：按对外随机码读库渲染；联系方式不出明文，点击经 get_contact.php 取号
zp_posts_housekeeping();

$code = (string) ($_GET['id'] ?? '');
$post = $code !== '' ? zp_post_by_code($code) : null;
if ($post === null) {
    http_response_code(404);
    zp_render('placeholder', ['title' => '信息不存在或已删除', 'feature' => 'detail', 'page' => 'plain']);
    return;
}

// 相似信息：同板块同大区，最近 3 条（排除自己）
$topRegion = (int) ($post['region_parent'] ?: $post['region_id']);
$stmt = zp_db()->prepare(
    'SELECT p.public_code, p.content, p.contact_name, p.bumped_at, r.name AS region_name, c.name AS category_name'
    . ' FROM ' . zp_table('posts') . ' p'
    . ' JOIN ' . zp_table('regions') . ' r ON r.id = p.region_id'
    . ' JOIN ' . zp_table('categories') . ' c ON c.id = p.category_id'
    . ' WHERE p.status = 1 AND p.type = ? AND p.id <> ? AND (p.region_id = ? OR r.parent_id = ?)'
    . ' ORDER BY p.is_top DESC, p.bumped_at DESC LIMIT 3'
);
$stmt->execute([(int) $post['type'], (int) $post['id'], $topRegion, $topRegion]);
$related = $stmt->fetchAll();

$expireDays = zp_setting_int('post_expire_days', 30);
$daysLeft = max(0, $expireDays - intdiv(time() - (new DateTime($post['created_at'], new DateTimeZone('UTC')))->getTimestamp(), 86400));

zp_render('detail', [
    'title' => zp_post_excerpt($post['content']),
    'page'  => 'detail',
    'mode'  => (int) $post['type'] === 2 ? 'seek' : 'hire',
    'post'  => $post,
    'related' => $related,
    'daysLeft' => $daysLeft,
]);
