<?php
declare(strict_types=1);

// 类别管理：待审核（通过并入/驳回）+ 正式类别的新增/改名/排序/启停
require __DIR__ . '/_common.php';
zp_require_admin();
$db = zp_db();
$CP = zp_table('categories_pending');
$C = zp_table('categories');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'approve' || $action === 'reject') {
        $stmt = $db->prepare("SELECT * FROM $CP WHERE id = ? AND status = 0");
        $stmt->execute([$id]);
        if (($row = $stmt->fetch()) !== false) {
            if ($action === 'approve') {
                $maxSort = (int) $db->query("SELECT COALESCE(MAX(sort),0) FROM $C WHERE sort < 999")->fetchColumn();
                $db->prepare("INSERT IGNORE INTO $C (name, sort, status, created_at) VALUES (?, ?, 1, ?)")
                   ->execute([$row['name'], $maxSort + 10, zp_now()]);
                $db->prepare("UPDATE $CP SET status = 1, reviewed_at = ? WHERE id = ?")->execute([zp_now(), $id]);
                zp_flash_set('已通过「' . $row['name'] . '」并并入正式类别');
            } else {
                $db->prepare("UPDATE $CP SET status = 2, reviewed_at = ? WHERE id = ?")->execute([zp_now(), $id]);
                zp_flash_set('已驳回');
            }
        }
    } elseif ($action === 'add') {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 30);
        $sort = (int) ($_POST['sort'] ?? 0);
        if ($name !== '') {
            $stmt = $db->prepare("INSERT IGNORE INTO $C (name, sort, status, created_at) VALUES (?, ?, 1, ?)");
            $stmt->execute([$name, $sort, zp_now()]);
            zp_flash_set($stmt->rowCount() > 0 ? '已新增类别「' . $name . '」' : '类别「' . $name . '」已存在');
        }
    } elseif ($action === 'update' && $id > 0) {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 30);
        $sort = (int) ($_POST['sort'] ?? 0);
        if ($name !== '') {
            try {
                $db->prepare("UPDATE $C SET name = ?, sort = ? WHERE id = ?")->execute([$name, $sort, $id]);
                zp_flash_set('已保存');
            } catch (PDOException $e) {
                zp_flash_set('保存失败：类别名已存在');
            }
        }
    } elseif ($action === 'toggle' && $id > 0) {
        $db->prepare("UPDATE $C SET status = 1 - status WHERE id = ?")->execute([$id]);
        zp_flash_set('已切换启用状态');
    }
    header('Location: /c/cp/categories');
    exit;
}

$pending = $db->query("SELECT * FROM $CP WHERE status = 0 ORDER BY id")->fetchAll();
$active = $db->query("SELECT c.*, (SELECT COUNT(*) FROM " . zp_table('posts') . " p WHERE p.category_id = c.id AND p.status = 1) post_count FROM $C c ORDER BY c.sort")->fetchAll();

zp_admin_page('categories', '类别管理', ['pending' => $pending, 'active' => $active, 'notice' => zp_flash_get()]);
