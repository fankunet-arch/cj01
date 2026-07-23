<?php
declare(strict_types=1);

/**
 * 置顶券管理：生成（数量/置顶天数/兑换有效期）→ 台账 → 作废。
 * 权限：超级管理员恒可；普通管理员须开启 normal_admin_can_coupon。
 * 生命周期：0未兑换 → 1已兑换未用 → 2已使用；3已过期 4已作废。
 */
require __DIR__ . '/_common.php';
$admin = zp_require_admin();
if (!zp_admin_can_coupon()) {
    http_response_code(403);
    exit('无权访问');
}
$db = zp_db();
$CO = zp_table('coupons');

// 惰性维护：过了兑换有效期仍未兑换的券标记为已过期
$db->prepare("UPDATE $CO SET status = 3 WHERE status = 0 AND valid_until < ?")->execute([zp_now()]);

$newCodes = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'generate') {
        $count = max(1, min(50, (int) ($_POST['count'] ?? 1)));
        $topDays = max(1, min(90, (int) ($_POST['top_days'] ?? 7)));
        $validDays = max(1, min(365, (int) ($_POST['valid_days'] ?? 30)));
        $validUntil = gmdate('Y-m-d H:i:s', time() + $validDays * 86400);
        $stmt = $db->prepare("INSERT INTO $CO (code, top_days, valid_until, status, created_by, created_at) VALUES (?, ?, ?, 0, ?, ?)");
        for ($i = 0; $i < $count; $i++) {
            $code = 'ZP' . zp_public_code(10);
            try {
                $stmt->execute([$code, $topDays, $validUntil, (int) $admin['id'], zp_now()]);
                $newCodes[] = $code;
            } catch (PDOException $e) {
                $i--; // 撞唯一键重试
            }
        }
        zp_flash_set('已生成 ' . count($newCodes) . ' 张券（置顶 ' . $topDays . ' 天，' . $validDays . ' 天内有效）');
        zp_session_start();
        $_SESSION['new_coupon_codes'] = $newCodes;
        header('Location: /c/cp/coupons');
        exit;
    }
    if ($action === 'void') {
        $id = (int) ($_POST['id'] ?? 0);
        // 已使用(2)的不可作废；其余状态可作废（用户丢码时作废旧码再生成新码）
        $db->prepare("UPDATE $CO SET status = 4 WHERE id = ? AND status <> 2")->execute([$id]);
        zp_flash_set('已作废该券');
        header('Location: /c/cp/coupons');
        exit;
    }
}

zp_session_start();
$justGenerated = $_SESSION['new_coupon_codes'] ?? [];
unset($_SESSION['new_coupon_codes']);

$rows = $db->query(
    "SELECT co.*, u.display_name AS owner_name, u.public_code AS owner_code, p.public_code AS post_code, a.email AS creator_email"
    . " FROM $CO co"
    . ' LEFT JOIN ' . zp_table('users') . ' u ON u.id = co.user_id'
    . ' LEFT JOIN ' . zp_table('posts') . ' p ON p.id = co.post_id'
    . ' LEFT JOIN ' . zp_table('admins') . ' a ON a.id = co.created_by'
    . ' ORDER BY co.id DESC LIMIT 200'
)->fetchAll();

zp_admin_page('coupons', '置顶券', ['rows' => $rows, 'notice' => zp_flash_get(), 'justGenerated' => $justGenerated]);
