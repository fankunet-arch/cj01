<?php
declare(strict_types=1);

// 编辑自己的信息（仅注册用户本人帖，docs/01 §3 权限矩阵）
$user = zp_require_user();
$db = zp_db();
$P = zp_table('posts');

$code = (string) ($_GET['id'] ?? ($_POST['id'] ?? ''));
$stmt = $db->prepare("SELECT * FROM $P WHERE public_code = ? AND user_id = ? AND poster_type = 2 AND status <> 3");
$stmt->execute([$code, $user['id']]);
$post = $stmt->fetch();
if ($post === false) {
    http_response_code(404);
    zp_render('placeholder', ['title' => '信息不存在或不属于你', 'feature' => 'user.edit', 'page' => 'plain']);
    return;
}

$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $content = trim((string) ($_POST['content'] ?? ''));
    $contactName = trim((string) ($_POST['contact_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $wechat = trim((string) ($_POST['wechat'] ?? ''));
    $regionId = (int) ($_POST['region_id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);

    if ($content === '' || mb_strlen($content) > 1000) {
        $err = '请填写信息内容（1000 字以内）';
    } elseif ($contactName === '' || mb_strlen($contactName) > 50) {
        $err = '请填写联系人称呼';
    } elseif (!preg_match('/^\+?[0-9 ().-]{9,20}$/', $phone) || strlen(zp_phone_norm($phone)) < 9) {
        $err = '电话格式不对（西班牙号码 9 位起）';
    } elseif (preg_match('/(\+?\d[\d ().-]{7,})/', $content) || preg_match('/(微信|weixin|wechat|wx)\s*[:：]?\s*[A-Za-z0-9_-]{5,}/iu', $content)) {
        $err = '请不要在正文里写电话/微信，填到联系方式栏';
    } else {
        $stmt = $db->prepare('SELECT 1 FROM ' . zp_table('regions') . ' WHERE id = ? AND status = 1');
        $stmt->execute([$regionId]);
        $regionOk = $stmt->fetch() !== false;
        $stmt = $db->prepare('SELECT 1 FROM ' . zp_table('categories') . ' WHERE id = ? AND status = 1');
        $stmt->execute([$categoryId]);
        if (!$regionOk || $stmt->fetch() === false) {
            $err = '请选择地区和类别';
        } else {
            $db->prepare(
                "UPDATE $P SET content = ?, content_hash = ?, contact_name = ?, phone = ?, phone_norm = ?,"
                . ' wechat = ?, region_id = ?, category_id = ?, updated_at = ? WHERE id = ?'
            )->execute([
                $content, zp_content_hash($content), $contactName, $phone, zp_phone_norm($phone),
                $wechat !== '' ? $wechat : null, $regionId, $categoryId, zp_now(), (int) $post['id'],
            ]);
            header('Location: /user/my_posts');
            exit;
        }
    }
    // 校验失败：回填提交值再渲染
    $post = array_merge($post, [
        'content' => $content, 'contact_name' => $contactName, 'phone' => $phone,
        'wechat' => $wechat !== '' ? $wechat : null, 'region_id' => $regionId, 'category_id' => $categoryId,
    ]);
}

$categories = $db->query('SELECT id, name FROM ' . zp_table('categories') . ' WHERE status = 1 ORDER BY sort')->fetchAll();
$regions = $db->query('SELECT id, parent_id, name FROM ' . zp_table('regions') . ' WHERE status = 1 ORDER BY level, sort')->fetchAll();
$topRegions = array_values(array_filter($regions, fn($r) => (int) $r['parent_id'] === 0));
$cities = [];
foreach ($regions as $r) {
    if ((int) $r['parent_id'] !== 0) {
        $cities[(int) $r['parent_id']][] = ['id' => (int) $r['id'], 'name' => $r['name']];
    }
}
// 当前地区所属大区（回显联动下拉）
$curTop = 0;
foreach ($regions as $r) {
    if ((int) $r['id'] === (int) $post['region_id']) {
        $curTop = (int) $r['parent_id'] ?: (int) $r['id'];
        break;
    }
}

zp_render('user/edit', [
    'title' => '编辑信息', 'page' => 'user',
    'user' => $user, 'post' => $post, 'err' => $err, 'csrf' => zp_csrf_token(),
    'categories' => $categories, 'topRegions' => $topRegions, 'cities' => $cities, 'curTop' => $curTop,
]);
