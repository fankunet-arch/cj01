<?php

declare(strict_types=1);

namespace Cj\Scheduler;

use Cj\Support\Logger;

/**
 * 告警邮件（Brevo，文档 §7）。未配置 API key 时仅落日志。
 */
final class Alert
{
    public static function send(string $subject, string $body): void
    {
        $cfg = cj_config('alert') ?? [];
        $apiKey = $cfg['brevo_api_key'] ?? '';
        $to = $cfg['to_email'] ?? '';
        if ($apiKey === '' || $to === '') {
            Logger::info('alert', "（未配置 Brevo，仅记录）$subject — $body");
            return;
        }

        $payload = json_encode([
            'sender'      => ['email' => $cfg['from_email'] ?? 'crawler@localhost', 'name' => 'cj crawler'],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'textContent' => $body,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'api-key: ' . $apiKey,
            ],
        ]);
        $ok = curl_exec($ch) !== false;
        curl_close($ch);
        Logger::log('alert', $ok ? 'info' : 'error', ($ok ? '已发送' : '发送失败') . "：$subject");
    }
}
