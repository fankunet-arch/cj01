<?php
declare(strict_types=1);

// 管理员编辑任意信息：内容/类别/地区/联系方式/置顶
require __DIR__ . '/_common.php';
zp_require_admin();
$db = zp_db();
$P = zp_table('posts');

$code = (string) ($_GET['id'] ?? '');
$stmt = $db->prepare("SELECT * FROM $P WHERE public_code = ?");
$stmt->execute([$code]);
$post = $stmt->fetch();
if ($post === false) {
    http_response_code(404);
    zp_render('placeholder', ['title' => '信息不存在', 'page' => 'plain']);
    return;
}

$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    $type = (int) ($_POST['type'] ?? 1) === 2 ? 2 : 1;
    $content = trim((string) ($_POST['content'] ?? ''));
    $contactName = trim((string) ($_POST['contact_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $wechat = trim((string) ($_POST['wechat'] ?? ''));
    $regionId = (int) ($_POST['region_id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $topDays = max(0, min(365, (int) ($_POST['top_days'] ?? 0)));

    if ($content === '' || mb_strlen($content) > 1000) {
        $err = '请填写信息内容（1000 字以内）';
    } elseif ($contactName === '' || mb_strlen($contactName) > 50) {
        $err = '请填写联系人称呼';
    } elseif (!preg_match('/^\+?[0-9 ().-]{9,20}$/', $phone)) {
        $err = '电话格式不对';
    } else {
        $db->prepare(
            "UPDATE $P SET type = ?, content = ?, content_hash = ?, contact_name = ?, phone = ?, phone_norm = ?,"
            . ' wechat = ?, region_id = ?, category_id = ?, is_top = ?, top_expire_at = ?, suspicious = ?, updated_at = ? WHERE id = ?'
        )->execute([
            $type, $content, zp_content_hash($content), $contactName, $phone, zp_phone_norm($phone),
            $wechat !== '' ? $wechat : null, $regionId, $categoryId,
            $topDays > 0 ? 1 : 0,
            $topDays > 0 ? gmdate('Y-m-d H:i:s', time() + $topDays * 86400) : null,
            isset($_POST['suspicious']) ? 1 : 0,
            zp_now(), (int) $post['id'],
        ]);
        zp_flash_set('已保存');
        header('Location: /c/cp/posts');
        exit;
    }
    $post = array_merge($post, [
        'type' => $type, 'content' => $content, 'contact_name' => $contactName,
        'phone' => $phone, 'wechat' => $wechat !== '' ? $wechat : null,
        'region_id' => $regionId, 'category_id' => $categoryId,
    ]);
}

$categories = $db->query('SELECT id, name FROM ' . zp_table('categories') . ' ORDER BY sort')->fetchAll();
$regions = $db->query('SELECT id, parent_id, name FROM ' . zp_table('regions') . ' WHERE status = 1 ORDER BY level, sort')->fetchAll();
$topRegions = array_values(array_filter($regions, fn($r) => (int) $r['parent_id'] === 0));
$cities = [];
foreach ($regions as $r) {
    if ((int) $r['parent_id'] !== 0) {
        $cities[(int) $r['parent_id']][] = ['id' => (int) $r['id'], 'name' => $r['name']];
    }
}
$curTop = 0;
foreach ($regions as $r) {
    if ((int) $r['id'] === (int) $post['region_id']) {
        $curTop = (int) $r['parent_id'] ?: (int) $r['id'];
        break;
    }
}
$topDaysLeft = 0;
if ((int) $post['is_top'] === 1 && $post['top_expire_at'] !== null) {
    $topDaysLeft = max(0, (int) ceil((strtotime($post['top_expire_at'] . ' UTC') - time()) / 86400));
}

zp_admin_page('post_edit', '编辑信息', [
    'post' => $post, 'err' => $err,
    'categories' => $categories, 'topRegions' => $topRegions, 'cities' => $cities,
    'curTop' => $curTop, 'topDaysLeft' => $topDaysLeft,
]);
