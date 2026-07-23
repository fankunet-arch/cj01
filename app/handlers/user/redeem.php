<?php
declare(strict_types=1);

// 兑换置顶券：输入兑换码 → 入账到当前用户（仅注册用户）
$user = zp_require_user();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /user/my_posts');
    exit;
}
zp_csrf_check();

$db = zp_db();
$CO = zp_table('coupons');
$code = strtoupper(trim((string) ($_POST['code'] ?? '')));

if ($code === '') {
    zp_flash_set('请输入兑换码');
} else {
    $stmt = $db->prepare("SELECT * FROM $CO WHERE code = ?");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    if ($coupon === false) {
        zp_flash_set('兑换码不存在，请核对后重试');
    } elseif ((int) $coupon['status'] !== 0) {
        zp_flash_set('该兑换码已被使用或已失效');
    } elseif ($coupon['valid_until'] < zp_now()) {
        $db->prepare("UPDATE $CO SET status = 3 WHERE id = ?")->execute([$coupon['id']]);
        zp_flash_set('该兑换码已过兑换有效期');
    } else {
        $db->prepare("UPDATE $CO SET status = 1, user_id = ?, redeemed_at = ? WHERE id = ? AND status = 0")
           ->execute([$user['id'], zp_now(), $coupon['id']]);
        zp_flash_set('兑换成功：置顶 ' . (int) $coupon['top_days'] . ' 天券已入账，在下方帖子上点「用券置顶」即可使用');
    }
}
header('Location: /user/my_posts');
exit;
