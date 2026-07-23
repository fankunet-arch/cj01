<?php
declare(strict_types=1);

/**
 * 发布：GET 渲染表单；POST 校验入库，返回 JSON。
 * 防护：算术验证码 + 蜜罐 + 同IP频率限制 + 每日限发1条 + 7天同内容顶帖(注册)/拒重(游客)
 *      + 手机号格式校验 + 正文夹带联系方式检测 + 字段名混淆（settings.field_name_map）。
 */
zp_session_start();
$fm = zp_field_map();
$db = zp_db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // ---- 渲染表单 ----
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['captcha'] = (string) ($a + $b);
    $categories = $db->query('SELECT id, name FROM ' . zp_table('categories') . ' WHERE status = 1 ORDER BY sort')->fetchAll();
    $regions = $db->query('SELECT id, parent_id, name FROM ' . zp_table('regions') . ' WHERE status = 1 ORDER BY level, sort')->fetchAll();
    $topRegions = array_values(array_filter($regions, fn($r) => (int) $r['parent_id'] === 0));
    $cities = [];
    foreach ($regions as $r) {
        if ((int) $r['parent_id'] !== 0) {
            $cities[(int) $r['parent_id']][] = ['id' => (int) $r['id'], 'name' => $r['name']];
        }
    }
    zp_render('publish', [
        'title' => '发布信息', 'page' => 'publish', 'mode' => 'hire',
        'fm' => $fm, 'captchaQ' => "$a + $b = ?",
        'categories' => $categories, 'topRegions' => $topRegions, 'cities' => $cities,
    ]);
    return;
}

// ---- 提交 ----
$field = fn(string $name): string => trim((string) ($_POST[$fm[$name]] ?? ''));

// 蜜罐：隐藏框被填即判机器人——照常"成功"但打可疑标记不入正常流程
$honeypot = trim((string) ($_POST['website'] ?? ''));

$type        = (int) $field('type') === 2 ? 2 : 1;
$content     = $field('content');
$contactName = $field('contact_name');
$phone       = $field('phone');
$wechat      = $field('wechat');
$regionId    = (int) $field('region_id');
$categoryId  = (int) $field('category_id');
$newCategory = mb_substr($field('new_category'), 0, 30);
$captcha     = $field('captcha');

// 验证码
if ($captcha === '' || $captcha !== ($_SESSION['captcha'] ?? null)) {
    zp_json(['ok' => false, 'error' => 'captcha', 'msg' => '验证答案不对，请重试'], 400);
}
unset($_SESSION['captcha']);

// 必填与格式
if ($content === '' || mb_strlen($content) > 1000) {
    zp_json(['ok' => false, 'error' => 'content', 'msg' => '请填写信息内容（1000 字以内）'], 400);
}
if ($contactName === '' || mb_strlen($contactName) > 50) {
    zp_json(['ok' => false, 'error' => 'contact_name', 'msg' => '请填写联系人称呼'], 400);
}
if (!preg_match('/^\+?[0-9 ().-]{9,20}$/', $phone) || strlen(zp_phone_norm($phone)) < 9) {
    zp_json(['ok' => false, 'error' => 'phone', 'msg' => '电话格式不对（西班牙号码 9 位起）'], 400);
}
if ($wechat !== '' && mb_strlen($wechat) > 60) {
    zp_json(['ok' => false, 'error' => 'wechat', 'msg' => '微信号过长'], 400);
}

// 地区必须是启用的市级/顶级项
$stmt = $db->prepare('SELECT id FROM ' . zp_table('regions') . ' WHERE id = ? AND status = 1');
$stmt->execute([$regionId]);
if ($stmt->fetch() === false) {
    zp_json(['ok' => false, 'error' => 'region', 'msg' => '请选择所在地区'], 400);
}
// 类别必须在正式表
$stmt = $db->prepare('SELECT id FROM ' . zp_table('categories') . ' WHERE id = ? AND status = 1');
$stmt->execute([$categoryId]);
if ($stmt->fetch() === false) {
    zp_json(['ok' => false, 'error' => 'category', 'msg' => '请选择职位类别'], 400);
}

// 正文夹带联系方式封堵（docs/01 §4.13）
if (preg_match('/(\+?\d[\d ().-]{7,})/', $content) || preg_match('/(微信|weixin|wechat|wx)\s*[:：]?\s*[A-Za-z0-9_-]{5,}/iu', $content)) {
    zp_json(['ok' => false, 'error' => 'contact_in_content', 'msg' => '请不要在正文里写电话/微信，填到下面的联系方式栏（正文明文会被爬虫采集）'], 400);
}

$ipBin = zp_ip_bin();
$now = zp_now();

// 同 IP 频率限制
$perHour = zp_setting_int('post_per_hour_per_ip', 5);
$stmt = $db->prepare('SELECT COUNT(*) FROM ' . zp_table('posts') . ' WHERE post_ip = ? AND created_at > ?');
$stmt->execute([$ipBin, gmdate('Y-m-d H:i:s', time() - 3600)]);
if ((int) $stmt->fetchColumn() >= $perHour) {
    zp_json(['ok' => false, 'error' => 'rate', 'msg' => '发布太频繁了，请稍后再试'], 429);
}

$phoneNorm = zp_phone_norm($phone);
$contentHash = zp_content_hash($content);

// 同电话同内容：每日限 1 条；7 天内重复 → 注册用户顶帖代替新发 / 游客拒绝（docs/01 §4.2）
$user = zp_user();
$bumpWindow = zp_setting_int('bump_window_days', 7);
$stmt = $db->prepare(
    'SELECT id, public_code, created_at, poster_type, user_id FROM ' . zp_table('posts')
    . ' WHERE phone_norm = ? AND content_hash = ? AND status IN (0,1) AND created_at > ? ORDER BY created_at DESC LIMIT 1'
);
$stmt->execute([$phoneNorm, $contentHash, gmdate('Y-m-d H:i:s', time() - $bumpWindow * 86400)]);
if (($dup = $stmt->fetch()) !== false) {
    if ($user !== null && (int) $dup['poster_type'] === 2 && (int) $dup['user_id'] === $user['id']) {
        $db->prepare('UPDATE ' . zp_table('posts') . ' SET bumped_at = ?, status = 1 WHERE id = ?')
           ->execute([$now, (int) $dup['id']]);
        zp_json(['ok' => true, 'bumped' => true, 'url' => '/detail?id=' . $dup['public_code'],
            'msg' => '这条信息 7 天内已发过，已为你顶帖刷新排序']);
    }
    zp_json(['ok' => false, 'error' => 'duplicate',
        'msg' => '相同内容近期已发布过（同一信息每天限发 1 条；注册用户可在 7 天内用"顶帖"刷新排序）',
        'url' => '/detail?id=' . $dup['public_code']], 409);
}

// 新类别建议 → 待审核表（物理隔离，帖子仍挂在所选正式类别）
if ($newCategory !== '') {
    $db->prepare('INSERT INTO ' . zp_table('categories_pending') . ' (name, submit_ip, user_id, status, submitted_at) VALUES (?, ?, NULL, 0, ?)')
       ->execute([$newCategory, $ipBin, $now]);
}

// 生成不可枚举随机码（撞唯一键重试）
$publicCode = zp_public_code(10);
for ($i = 0; $i < 3; $i++) {
    $stmt = $db->prepare('SELECT 1 FROM ' . zp_table('posts') . ' WHERE public_code = ?');
    $stmt->execute([$publicCode]);
    if ($stmt->fetch() === false) break;
    $publicCode = zp_public_code(10);
}

$suspicious = $honeypot !== '' ? 1 : 0; // 蜜罐命中：照常入库显示，仅后台高亮（docs/01 §7.1）

$db->prepare(
    'INSERT INTO ' . zp_table('posts')
    . ' (public_code, type, content, content_hash, contact_name, phone, phone_norm, wechat,'
    . ' region_id, category_id, poster_type, user_id, suspicious, status, post_ip, created_at, bumped_at)'
    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
)->execute([
    $publicCode, $type, $content, $contentHash, $contactName, $phone, $phoneNorm,
    $wechat !== '' ? $wechat : null, $regionId, $categoryId,
    $user !== null ? 2 : 1, $user['id'] ?? null,
    $suspicious, $ipBin, $now, $now,
]);

zp_json(['ok' => true, 'url' => '/detail?id=' . $publicCode, 'code' => $publicCode]);
