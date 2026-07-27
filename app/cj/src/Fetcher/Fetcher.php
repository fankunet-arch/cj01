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
    private array $extraHeaders;
    private ?string $uaOverride;
    private ?string $lastServer = null;
    private int $timeout;

    /**
     * @param array $options 站点级抓取选项：
     *   'user_agent' => string      覆盖默认 UA
     *   'headers'    => string[]     追加/覆盖请求头（如自定义 Referer）
     *   'timeout'    => int          单请求总超时秒数（默认 45；同步采集用更短值防 Web 超时）
     */
    public function __construct(string $site, array $rateLimit, array $options = [])
    {
        $this->rateLimit = $rateLimit + ['min_delay' => 8, 'max_delay' => 20];
        $this->extraHeaders = $options['headers'] ?? [];
        $this->uaOverride = $options['user_agent'] ?? null;
        $this->timeout = (int) ($options['timeout'] ?? 45);
        $dir = (cj_config('log_dir') ?: CJ_APP_ROOT . '/logs') . '/cookies';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $this->cookieFile = $dir . '/' . preg_replace('/[^a-z0-9_]/i', '', $site) . '.txt';
    }

    /** 上次响应的关键头（Server / CF-RAY 等），用于诊断被拦原因。 */
    public function serverHeader(): ?string
    {
        return $this->lastServer;
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
                    // 转码后同步把 meta 的 charset 改成 UTF-8，避免下游（DOM 解析/浏览器）
                    // 按原编码二次解码导致乱码。
                    $body = preg_replace('#(<meta[^>]*charset=["\']?)[\w-]+#i', '${1}UTF-8', $body, 1) ?? $body;
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
        $ua = $this->uaOverride
            ?? ($uas !== [] ? $uas[array_rand($uas)]
                : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36');

        // 尽量贴近真实浏览器的导航请求，降低被反爬按“非浏览器”拦截的概率
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,es;q=0.8,en;q=0.7',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: max-age=0',
        ];
        if ($this->lastUrl !== null) {
            $headers[] = 'Referer: ' . $this->lastUrl;
        }
        foreach ($this->extraHeaders as $h) {   // 站点自定义头（可覆盖 Referer 等）
            $headers[] = $h;
        }

        // 采集响应关键头（Server / CF-RAY），失败时用于判断是否 Cloudflare 等
        $meta = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => min(15, $this->timeout),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_COOKIEJAR      => $this->cookieFile,   // 会话连续（§6.2）
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_ENCODING       => '',                  // 接受 gzip/deflate
            CURLOPT_HEADERFUNCTION => function ($c, string $line) use (&$meta): int {
                if (preg_match('/^(server|cf-ray|cf-mitigated|x-powered-by):/i', trim($line))) {
                    $meta[] = trim($line);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->lastServer = $meta !== [] ? implode('; ', $meta) : null;
        if ($body === false) {
            Logger::error('fetch', "抓取失败：{$url} — " . curl_error($ch));
            $body = null;
        }
        curl_close($ch);
        return [$status, is_string($body) ? $body : null];
    }
}
