<?php
declare(strict_types=1);

/**
 * Brevo 事务邮件（docs/02 §6：域名发件，不用个人邮箱）。
 * api_key / 发件人取 app/config；收件人取 settings.report_recipients（JSON 数组）。
 */

/** 发送一封邮件。未配置 Brevo 或无收件人时静默跳过（返回 false）。 */
function zp_mail_send(string $subject, string $htmlBody, array $recipients): bool
{
    $apiKey = (string) zp_config('brevo.api_key', '');
    $from = (string) zp_config('brevo.from_email', '');
    if ($apiKey === '' || $from === '' || $recipients === []) {
        return false;
    }
    $payload = json_encode([
        'sender' => ['email' => $from, 'name' => (string) zp_config('brevo.from_name', '西华招聘')],
        'to' => array_map(fn($e) => ['email' => $e], $recipients),
        'subject' => $subject,
        'htmlContent' => $htmlBody,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['api-key: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $status >= 200 && $status < 300;
}

/**
 * 举报提醒（合并降频，docs/01 §4.9）：
 * 同帖在合并窗口（settings.report_email_merge_min）内只发第一封；
 * 窗口过后新举报再发一封，写明累计被举报次数。
 * 调用时机：新举报行已插入之后。
 */
function zp_mail_report_alert(array $post): bool
{
    $recipients = json_decode(zp_setting('report_recipients', '[]') ?? '[]', true);
    if (!is_array($recipients) || $recipients === []) {
        return false;
    }
    $db = zp_db();
    $mergeMin = zp_setting_int('report_email_merge_min', 60);
    // 窗口内已有更早的举报 → 那次已发过邮件，本次只累计不发
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM ' . zp_table('reports')
        . ' WHERE post_id = ? AND created_at > ? AND id <> (SELECT MAX(id) FROM ' . zp_table('reports') . ' WHERE post_id = ?)'
    );
    $stmt->execute([(int) $post['id'], gmdate('Y-m-d H:i:s', time() - $mergeMin * 60), (int) $post['id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return false;
    }
    $stmt = $db->prepare('SELECT COUNT(*) FROM ' . zp_table('reports') . ' WHERE post_id = ?');
    $stmt->execute([(int) $post['id']]);
    $total = (int) $stmt->fetchColumn();

    $url = zp_base_url() . '/detail?id=' . $post['public_code'];
    $cpUrl = zp_base_url() . '/c/cp/reports';
    $excerpt = zp_e(zp_post_excerpt($post['content'], 50));
    return zp_mail_send(
        "[西华招聘] 信息被举报（累计 {$total} 次）",
        "<p>信息「{$excerpt}」被举报，累计 <b>{$total}</b> 次。</p>"
        . "<p><a href=\"{$url}\">查看信息</a> · <a href=\"{$cpUrl}\">去后台处理</a></p>"
        . '<p style="color:#888;font-size:12px">同一信息 ' . $mergeMin . ' 分钟内只提醒一次，期间新增举报只累计不重发。</p>',
        $recipients
    );
}
