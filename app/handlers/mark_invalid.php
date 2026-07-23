<?php
declare(strict_types=1);

/**
 * 标记"已失效"：{code} → {ok}
 * 同 IP 同帖只计一次（uk_post_ip 唯一键兜底）；累计数供后台按阈值处理。
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    zp_json(['ok' => false, 'error' => 'method'], 405);
}

$in = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$code = (string) ($in['code'] ?? '');

$post = $code !== '' ? zp_post_by_code($code) : null;
if ($post === null) {
    zp_json(['ok' => false, 'error' => 'not_found'], 404);
}

$db = zp_db();
$stmt = $db->prepare('INSERT IGNORE INTO ' . zp_table('invalid_marks') . ' (post_id, marker_ip, created_at) VALUES (?, ?, ?)');
$stmt->execute([(int) $post['id'], zp_ip_bin(), zp_now()]);
if ($stmt->rowCount() > 0) {
    $db->prepare('UPDATE ' . zp_table('posts') . ' SET invalid_count = invalid_count + 1 WHERE id = ?')
       ->execute([(int) $post['id']]);
}

zp_json(['ok' => true, 'msg' => '已记录，谢谢反馈']);
