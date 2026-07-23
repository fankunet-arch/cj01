<?php
declare(strict_types=1);

/**
 * 管理员管理（仅超级管理员）：
 * 新增/停用管理员、设定角色、切换"普通管理员可管理置顶券"开关。
 */
require __DIR__ . '/_common.php';
$me = zp_require_super_admin();
$db = zp_db();
$A = zp_table('admins');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = (int) ($_POST['role'] ?? 1) === 2 ? 2 : 1;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $db->prepare("INSERT IGNORE INTO $A (email, role, status, created_at) VALUES (?, ?, 1, ?)");
            $stmt->execute([$email, $role, zp_now()]);
            zp_flash_set($stmt->rowCount() > 0 ? '已添加 ' . $email : '该邮箱已在名单中');
        } else {
            zp_flash_set('邮箱格式不对');
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id !== (int) $me['id']) { // 不能停用自己，防锁死
            $db->prepare("UPDATE $A SET status = 1 - status WHERE id = ?")->execute([$id]);
            zp_flash_set('已切换启用状态');
        } else {
            zp_flash_set('不能停用自己');
        }
    } elseif ($action === 'role') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id !== (int) $me['id']) { // 不能给自己降级，防失去超管
            $db->prepare("UPDATE $A SET role = IF(role = 2, 1, 2) WHERE id = ?")->execute([$id]);
            zp_flash_set('已切换角色');
        } else {
            zp_flash_set('不能修改自己的角色');
        }
    } elseif ($action === 'coupon_switch') {
        $v = ($_POST['value'] ?? '0') === '1' ? '1' : '0';
        $db->prepare('UPDATE ' . zp_table('settings') . " SET svalue = ?, updated_at = ? WHERE skey = 'normal_admin_can_coupon'")
           ->execute([$v, zp_now()]);
        zp_flash_set($v === '1' ? '已允许普通管理员管理置顶券' : '已收回普通管理员的置顶券权限');
    }
    header('Location: /c/cp/admins');
    exit;
}

$rows = $db->query("SELECT * FROM $A ORDER BY role DESC, id")->fetchAll();
$couponSwitch = zp_setting('normal_admin_can_coupon', '0') === '1';

zp_admin_page('admins', '管理员', [
    'rows' => $rows, 'meId' => (int) $me['id'],
    'couponSwitch' => $couponSwitch, 'notice' => zp_flash_get(),
]);
