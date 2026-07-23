<?php
declare(strict_types=1);

// 我的发布：本人帖子列表 + 顶帖 / 主动下架 / 删除 + 置顶券（兑换/使用）
$user = zp_require_user();
$db = zp_db();
$P = zp_table('posts');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $stmt = $db->prepare("SELECT * FROM $P WHERE id = ? AND user_id = ? AND poster_type = 2");
    $stmt->execute([$id, $user['id']]);
    if (($post = $stmt->fetch()) !== false) {
        if ($action === 'bump') {
            // 顶帖：刷新排序时间；同帖 24 小时内限一次，防刷屏
            if (strtotime($post['bumped_at'] . ' UTC') > time() - 86400) {
                zp_flash_set('这条信息 24 小时内已顶过，明天再来吧');
            } else {
                $db->prepare("UPDATE $P SET bumped_at = ?, status = 1 WHERE id = ?")->execute([zp_now(), $id]);
                zp_flash_set('已顶帖，信息回到列表最前');
            }
        } elseif ($action === 'offline') {
            $db->prepare("UPDATE $P SET status = 2, updated_at = ? WHERE id = ?")->execute([zp_now(), $id]);
            zp_flash_set('已下架（已招到/已找到）');
        } elseif ($action === 'delete') {
            $db->prepare("UPDATE $P SET status = 3, updated_at = ? WHERE id = ?")->execute([zp_now(), $id]);
            zp_flash_set('已删除');
        }
    }
    header('Location: /user/my_posts');
    exit;
}

$stmt = $db->prepare(
    'SELECT p.*, r.name AS region_name, c.name AS category_name'
    . " FROM $P p JOIN " . zp_table('regions') . ' r ON r.id = p.region_id'
    . ' JOIN ' . zp_table('categories') . ' c ON c.id = p.category_id'
    . ' WHERE p.user_id = ? AND p.poster_type = 2 AND p.status <> 3 ORDER BY p.id DESC'
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

// 我的置顶券：已兑换未用
$stmt = $db->prepare('SELECT * FROM ' . zp_table('coupons') . ' WHERE user_id = ? AND status = 1 ORDER BY redeemed_at');
$stmt->execute([$user['id']]);
$coupons = $stmt->fetchAll();

zp_render('user/my_posts', [
    'title' => '我的发布', 'page' => 'user',
    'user' => $user, 'rows' => $rows, 'coupons' => $coupons,
    'notice' => zp_flash_get(), 'csrf' => zp_csrf_token(),
]);
