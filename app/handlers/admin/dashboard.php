<?php
declare(strict_types=1);

// 数据看板（简版）：核心计数 + 最近发布
require __DIR__ . '/_common.php';
zp_require_admin();
zp_posts_housekeeping();
$db = zp_db();

$q = fn(string $sql) => (int) $db->query($sql)->fetchColumn();
$P = zp_table('posts');
$todayStartUtc = (new DateTime('today', new DateTimeZone('Europe/Madrid')))
    ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

$stats = [
    '在线招聘'   => $q("SELECT COUNT(*) FROM $P WHERE status = 1 AND type = 1"),
    '在线求职'   => $q("SELECT COUNT(*) FROM $P WHERE status = 1 AND type = 2"),
    '今日新增'   => (int) (function () use ($db, $P, $todayStartUtc) {
        $s = $db->prepare("SELECT COUNT(*) FROM $P WHERE created_at >= ?");
        $s->execute([$todayStartUtc]);
        return $s->fetchColumn();
    })(),
    '待审核类别' => $q('SELECT COUNT(*) FROM ' . zp_table('categories_pending') . ' WHERE status = 0'),
    '待处理举报' => $q('SELECT COUNT(*) FROM ' . zp_table('reports') . ' WHERE status = 0'),
    '可疑信息'   => $q("SELECT COUNT(*) FROM $P WHERE suspicious = 1 AND status = 1"),
];

$recent = $db->query(
    "SELECT p.public_code, p.type, p.content, p.contact_name, p.status, p.suspicious, p.created_at,"
    . ' r.name AS region_name, c.name AS category_name'
    . " FROM $P p JOIN " . zp_table('regions') . ' r ON r.id = p.region_id'
    . ' JOIN ' . zp_table('categories') . ' c ON c.id = p.category_id'
    . ' ORDER BY p.id DESC LIMIT 10'
)->fetchAll();

zp_admin_page('dashboard', '数据看板', ['stats' => $stats, 'recent' => $recent]);
