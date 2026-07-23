<?php
declare(strict_types=1);

/**
 * 点击取号 API：{code, ctype: phone|wechat} → {ok, value, views}
 * 防护：同 IP 每小时上限；同 IP 同帖同类型 60 分钟去重（不重复计数）。
 * 计数：phone_views / wechat_views 分开累计（前台公开显示热度）。
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    zp_json(['ok' => false, 'error' => 'method'], 405);
}

$in = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$code = (string) ($in['code'] ?? '');
$ctype = ($in['ctype'] ?? '') === 'wechat' ? 2 : 1;

$post = $code !== '' ? zp_post_by_code($code) : null;
if ($post === null) {
    zp_json(['ok' => false, 'error' => 'not_found'], 404);
}
if ($ctype === 2 && ($post['wechat'] ?? null) === null) {
    zp_json(['ok' => false, 'error' => 'no_wechat'], 404);
}

$db = zp_db();
$ipBin = zp_ip_bin();
$hourAgo = gmdate('Y-m-d H:i:s', time() - 3600);

// 同 IP 每小时取号上限
$limit = zp_setting_int('contact_reveal_per_hour', 30);
$stmt = $db->prepare('SELECT COUNT(*) FROM ' . zp_table('contact_log') . ' WHERE viewer_ip = ? AND created_at > ?');
$stmt->execute([$ipBin, $hourAgo]);
if ((int) $stmt->fetchColumn() >= $limit) {
    zp_json(['ok' => false, 'error' => 'rate', 'msg' => '查看太频繁了，请稍后再试'], 429);
}

// 同 IP 同帖同类型短时去重：不重复计数，但正常返回号码
$stmt = $db->prepare('SELECT 1 FROM ' . zp_table('contact_log') . ' WHERE post_id = ? AND contact_type = ? AND viewer_ip = ? AND created_at > ? LIMIT 1');
$stmt->execute([(int) $post['id'], $ctype, $ipBin, $hourAgo]);
$dedup = $stmt->fetch() !== false;

if (!$dedup) {
    $db->prepare('INSERT INTO ' . zp_table('contact_log') . ' (post_id, contact_type, viewer_ip, created_at) VALUES (?, ?, ?, ?)')
       ->execute([(int) $post['id'], $ctype, $ipBin, zp_now()]);
    $col = $ctype === 1 ? 'phone_views' : 'wechat_views';
    $db->prepare('UPDATE ' . zp_table('posts') . " SET $col = $col + 1 WHERE id = ?")
       ->execute([(int) $post['id']]);
}

$views = (int) $post[$ctype === 1 ? 'phone_views' : 'wechat_views'] + ($dedup ? 0 : 1);
zp_json(['ok' => true, 'value' => $ctype === 1 ? $post['phone'] : $post['wechat'], 'views' => $views]);
