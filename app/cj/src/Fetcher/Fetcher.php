<?php

declare(strict_types=1);

namespace Cj\Fetcher;

use Cj\Support\Logger;

/**
 * HTTP 抓取（文档 §6 反爬应对与采集礼仪）：
 * - 请求间隔随机化（每站 min_delay–max_delay 秒），像一个正常访客
 * - 真实浏览器 UA 轮换、合理 Accept/Referer、cookie 会话连续
 * - 失败重试（指数退避）
 * 基于 cURL，零依赖；装了 Composer 也可换 Guzzle，接口不变。
 */
final class Fetcher
{
    private array $rateLimit;
    private string $cookieFile;
    private ?string $lastUrl = null;
    private ?float $lastRequestAt = null;

    public function __construct(string $site, array $rateLimit)
    {
        $this->rateLimit = $rateLimit + ['min_delay' => 8, 'max_delay' => 20];
        $dir = (cj_config('log_dir') ?: CJ_APP_ROOT . '/logs') . '/cookies';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $this->cookieFile = $dir . '/' . preg_replace('/[^a-z0-9_]/i', '', $site) . '.txt';
    }

    /**
     * 抓取一个 URL，返回 ['status' => int, 'body' => ?string]。
     * 抓取失败（网络错误 / 5xx）按指数退避重试。
     */
    public function get(string $url, ?string $charset = null): array
    {
        $retries = (int) (cj_config('crawl')['retry_times'] ?? 3);
        $attempt = 0;
        while (true) {
            $this->politeDelay();
            [$status, $body] = $this->doRequest($url);
            $this->lastUrl = $url;

            $retriable = ($status === 0 || $status >= 500);
            if (!$retriable || $attempt >= $retries) {
                if ($body !== null && $charset !== null) {
                    $body = mb_convert_encoding($body, 'UTF-8', $charset);
                }
                return ['status' => $status, 'body' => $body];
            }
            $attempt++;
            $backoff = 2 ** $attempt;   // 2s, 4s, 8s…
            Logger::info('fetch', "重试 {$attempt}/{$retries}（{$backoff}s 后）：{$url} status={$status}");
            sleep($backoff);
        }
    }

    /** 请求间隔随机化，避免固定节奏被识别（§6.1）。 */
    private function politeDelay(): void
    {
        $min = (int) $this->rateLimit['min_delay'];
        $max = max($min, (int) $this->rateLimit['max_delay']);
        if ($this->lastRequestAt !== null) {
            $wait = random_int($min, $max) - (microtime(true) - $this->lastRequestAt);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }
        $this->lastRequestAt = microtime(true);
    }

    private function doRequest(string $url): array
    {
        $uas = cj_config('crawl')['user_agents'] ?? [];
        $ua = $uas !== [] ? $uas[array_rand($uas)]
            : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,es;q=0.6,en;q=0.4',
        ];
        if ($this->lastUrl !== null) {
            $headers[] = 'Referer: ' . $this->lastUrl;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_COOKIEJAR      => $this->cookieFile,   // 会话连续（§6.2）
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_ENCODING       => '',                  // 接受 gzip
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            Logger::error('fetch', "抓取失败：{$url} — " . curl_error($ch));
            $body = null;
        }
        curl_close($ch);
        return [$status, is_string($body) ? $body : null];
    }
}
