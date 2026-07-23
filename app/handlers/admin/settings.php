<?php
declare(strict_types=1);

// 参数配置（仅超级管理员）：zhaopin_settings 键值编辑，带中文说明
require __DIR__ . '/_common.php';
zp_require_super_admin();
$db = zp_db();
$S = zp_table('settings');

// 每个参数键的中文说明（新键在此补充说明即可）
$descriptions = [
    'post_expire_days'        => '信息有效期（天）：发布满 N 天自动下架，数据保留',
    'backfill_n_recruit'      => '招聘板块保底条数：在线不足 N 条时把最近下架的捞回补显，防墙空',
    'backfill_n_seek'         => '求职板块保底条数：同上，求职独立计算',
    'daily_post_limit'        => '同一电话+同一内容每天限发条数',
    'bump_window_days'        => '顶帖窗口（天）：注册用户 N 天内重发相同内容自动转为顶帖',
    'top_slot_limit'          => '同时置顶条数上限（准入闸：只拦新进场，不踢已在场的）',
    'report_dedup_window_min' => '举报去重窗口（分钟）：同 IP 对同帖窗口内重复举报不重复计数',
    'report_email_merge_min'  => '举报邮件合并窗口（分钟）：同帖窗口内最多发一封提醒邮件',
    'report_recipients'       => '举报提醒收件邮箱：JSON 数组，如 ["a@x.com","b@x.com"]，留 [] 不发信',
    'invalid_threshold'       => '失效标记高亮阈值：同帖被标记"已失效"达 N 次后台高亮提醒',
    'contact_reveal_per_hour' => '取号频率上限：同 IP 每小时最多查看联系方式次数',
    'post_per_hour_per_ip'    => '发布频率上限：同 IP 每小时最多发布条数',
    'field_name_map'          => '发布表单字段名混淆映射（JSON）：改动后立即生效，防爬虫脚本硬编码字段名',
    'normal_admin_can_coupon' => '普通管理员能否管理置顶券：0=禁止 1=允许（建议在「管理员」页用开关改）',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    zp_csrf_check();
    foreach ((array) ($_POST['s'] ?? []) as $key => $value) {
        $db->prepare("UPDATE $S SET svalue = ?, updated_at = ? WHERE skey = ?")
           ->execute([(string) $value, zp_now(), (string) $key]);
    }
    header('Location: /c/cp/settings?saved=1');
    exit;
}
$saved = ($_GET['saved'] ?? '') === '1';

$rows = $db->query("SELECT * FROM $S ORDER BY skey")->fetchAll();
zp_admin_page('settings', '参数配置', ['rows' => $rows, 'saved' => $saved, 'descriptions' => $descriptions]);
