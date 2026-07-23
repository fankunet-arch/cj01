<?php
declare(strict_types=1);

/**
 * 举报提交：{code, reason} → {ok}
 * 同 IP 同帖窗口期去重不重复计数；邮件提醒经 Brevo（未配置则跳过，仅落库）。
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    zp_json(['ok' => false, 'error' => 'method'], 405);
}

$in = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$code = (string) ($in['code'] ?? '');
$reason = mb_substr(trim((string) ($in['reason'] ?? '')), 0, 200);

$post = $code !== '' ? zp_post_by_code($code) : null;
if ($post === null) {
    zp_json(['ok' => false, 'error' => 'not_found'], 404);
}

$db = zp_db();
$ipBin = zp_ip_bin();
$windowMin = zp_setting_int('report_dedup_window_min', 60);

// 去重：同 IP 对同帖窗口期内重复举报不重复计数
$stmt = $db->prepare('SELECT 1 FROM ' . zp_table('reports') . ' WHERE post_id = ? AND reporter_ip = ? AND created_at > ? LIMIT 1');
$stmt->execute([(int) $post['id'], $ipBin, gmdate('Y-m-d H:i:s', time() - $windowMin * 60)]);
if ($stmt->fetch() === false) {
    $db->prepare('INSERT INTO ' . zp_table('reports') . ' (post_id, reason, reporter_ip, status, created_at) VALUES (?, ?, ?, 0, ?)')
       ->execute([(int) $post['id'], $reason !== '' ? $reason : null, $ipBin, zp_now()]);
    $db->prepare('UPDATE ' . zp_table('posts') . ' SET report_count = report_count + 1 WHERE id = ?')
       ->execute([(int) $post['id']]);
    // Brevo 邮件提醒（同帖合并降频；未配置 Brevo/收件人时静默跳过）
    zp_mail_report_alert($post);
}

zp_json(['ok' => true, 'msg' => '已收到你的举报，谢谢']);
