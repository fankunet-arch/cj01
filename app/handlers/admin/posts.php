<?php
declare(strict_types=1);

// 信息管理：查看 / 下架 / 恢复 / 删除任意信息
require __DIR__ . '/_common.php';
zp_require_admin();
$db = zp_db();
$P = zp_table('posts');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $newStatus = ['offline' => 2, 'restore' => 1, 'delete' => 3][(string) ($_POST['action'] ?? '')] ?? null;
    if ($id > 0 && $newStatus !== null) {
        $db->prepare("UPDATE $P SET status = ?, updated_at = ? WHERE id = ?")->execute([$newStatus, zp_now(), $id]);
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/c/cp/posts'));
    exit;
}

$where = ['1=1'];
$args = [];
if (($_GET['type'] ?? '') !== '') { $where[] = 'p.type = ?'; $args[] = (int) $_GET['type']; }
if (($_GET['status'] ?? '') !== '') { $where[] = 'p.status = ?'; $args[] = (int) $_GET['status']; }
if (($_GET['sus'] ?? '') === '1') { $where[] = 'p.suspicious = 1'; }
if (($q = trim((string) ($_GET['q'] ?? ''))) !== '') {
    $where[] = '(p.content LIKE ? OR p.contact_name LIKE ? OR p.phone LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%");
}
$pageNo = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($pageNo - 1) * 50;

$stmt = $db->prepare(
    'SELECT p.*, r.name AS region_name, c.name AS category_name'
    . " FROM $P p JOIN " . zp_table('regions') . ' r ON r.id = p.region_id'
    . ' JOIN ' . zp_table('categories') . ' c ON c.id = p.category_id'
    . ' WHERE ' . implode(' AND ', $where) . " ORDER BY p.id DESC LIMIT 50 OFFSET $offset"
);
$stmt->execute($args);
$rows = $stmt->fetchAll();

zp_admin_page('posts', '信息管理', ['rows' => $rows, 'pageNo' => $pageNo, 'q' => $q]);
