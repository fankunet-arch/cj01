<?php
declare(strict_types=1);

// 失效标记处理：按累计数排序，达阈值高亮；可下架或清零
require __DIR__ . '/_common.php';
zp_require_admin();
$db = zp_db();
$P = zp_table('posts');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($id > 0 && $action === 'offline') {
        $db->prepare("UPDATE $P SET status = 2, updated_at = ? WHERE id = ?")->execute([zp_now(), $id]);
    } elseif ($id > 0 && $action === 'reset') {
        $db->prepare("UPDATE $P SET invalid_count = 0 WHERE id = ?")->execute([$id]);
        $db->prepare('DELETE FROM ' . zp_table('invalid_marks') . ' WHERE post_id = ?')->execute([$id]);
    }
    header('Location: /c/cp/invalid');
    exit;
}

$threshold = zp_setting_int('invalid_threshold', 5);
$rows = $db->query(
    'SELECT p.*, r.name AS region_name, c.name AS category_name'
    . " FROM $P p JOIN " . zp_table('regions') . ' r ON r.id = p.region_id'
    . ' JOIN ' . zp_table('categories') . ' c ON c.id = p.category_id'
    . ' WHERE p.invalid_count > 0 AND p.status IN (0,1) ORDER BY p.invalid_count DESC, p.id DESC LIMIT 100'
)->fetchAll();

zp_admin_page('invalid', '失效标记', ['rows' => $rows, 'threshold' => $threshold]);
