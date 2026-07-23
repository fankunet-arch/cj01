<?php
declare(strict_types=1);

/**
 * 对某帖使用置顶券（仅本人帖）。
 * 置顶位上限是"准入闸"：在场置顶数 < 上限才放行，绝不取消已在场的（docs/01 §4.11）。
 * 置顶不延长帖子 30 天寿命。
 */
$user = zp_require_user();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /user/my_posts');
    exit;
}
zp_csrf_check();

$db = zp_db();
$CO = zp_table('coupons');
$P = zp_table('posts');
$postId = (int) ($_POST['post_id'] ?? 0);

// 帖子必须是本人的在线帖
$stmt = $db->prepare("SELECT * FROM $P WHERE id = ? AND user_id = ? AND poster_type = 2 AND status = 1");
$stmt->execute([$postId, $user['id']]);
$post = $stmt->fetch();

// 取最早兑换的一张未用券
$stmt = $db->prepare("SELECT * FROM $CO WHERE user_id = ? AND status = 1 ORDER BY redeemed_at LIMIT 1");
$stmt->execute([$user['id']]);
$coupon = $stmt->fetch();

if ($post === false) {
    zp_flash_set('只能对自己在线的信息使用置顶券');
} elseif ($coupon === false) {
    zp_flash_set('没有可用的置顶券，先在上方输入兑换码');
} elseif ((int) $post['is_top'] === 1) {
    zp_flash_set('这条信息已在置顶中');
} else {
    zp_posts_housekeeping(); // 先让到期的置顶回落，再数在场数
    $limit = zp_setting_int('top_slot_limit', 5);
    $inField = (int) $db->query("SELECT COUNT(*) FROM $P WHERE is_top = 1 AND top_expire_at > '" . zp_now() . "'")->fetchColumn();
    if ($inField >= $limit) {
        zp_flash_set('当前置顶位已满（' . $inField . '/' . $limit . '），等有位置空出来再用，券不会作废');
    } else {
        $expire = gmdate('Y-m-d H:i:s', time() + (int) $coupon['top_days'] * 86400);
        $db->prepare("UPDATE $P SET is_top = 1, top_expire_at = ?, updated_at = ? WHERE id = ?")
           ->execute([$expire, zp_now(), $postId]);
        $db->prepare("UPDATE $CO SET status = 2, post_id = ?, used_at = ? WHERE id = ?")
           ->execute([$postId, zp_now(), $coupon['id']]);
        zp_flash_set('置顶成功：将置顶展示 ' . (int) $coupon['top_days'] . ' 天');
    }
}
header('Location: /user/my_posts');
exit;
