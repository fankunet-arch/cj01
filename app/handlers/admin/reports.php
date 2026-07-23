<?php
declare(strict_types=1);

// 举报处理：待处理列表 → 标记已处理 / 忽略（可顺手下架帖子）
require __DIR__ . '/_common.php';
zp_require_admin();
$db = zp_db();
$R = zp_table('reports');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($id > 0 && in_array($action, ['handle', 'ignore'], true)) {
        $db->prepare("UPDATE $R SET status = ?, handled_at = ? WHERE id = ?")
           ->execute([$action === 'handle' ? 1 : 2, zp_now(), $id]);
    }
    if ($action === 'handle' && ($_POST['offline'] ?? '') === '1') {
        $stmt = $db->prepare("SELECT post_id FROM $R WHERE id = ?");
        $stmt->execute([$id]);
        if (($pid = $stmt->fetchColumn()) !== false) {
            $db->prepare('UPDATE ' . zp_table('posts') . ' SET status = 2, updated_at = ? WHERE id = ?')
               ->execute([zp_now(), (int) $pid]);
        }
    }
    header('Location: /c/cp/reports');
    exit;
}

$showAll = ($_GET['all'] ?? '') === '1';
$rows = $db->query(
    'SELECT rp.*, p.public_code, p.content, p.contact_name, p.status AS post_status'
    . " FROM $R rp JOIN " . zp_table('posts') . ' p ON p.id = rp.post_id'
    . ($showAll ? '' : ' WHERE rp.status = 0')
    . ' ORDER BY rp.id DESC LIMIT 100'
)->fetchAll();

zp_admin_page('reports', '举报处理', ['rows' => $rows, 'showAll' => $showAll]);
