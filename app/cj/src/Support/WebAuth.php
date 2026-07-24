<?php

declare(strict_types=1);

namespace Cj\Support;

/**
 * 内部页面（复核/看板）访问控制：Basic Auth + 可选 IP 白名单。
 * 这些页面能看到采集数据，必须加基础鉴权（文档 §4.2 要点）。
 */
final class WebAuth
{
    public static function guard(): void
    {
        $cfg = cj_config('web') ?? [];

        $whitelist = $cfg['ip_whitelist'] ?? [];
        if ($whitelist !== []) {
            $ip = self::clientIp($cfg);
            if (!in_array($ip, $whitelist, true)) {
                // 记录实际来源 IP，便于排查（尤其代理后 REMOTE_ADDR 是代理 IP）
                error_log('[cj] WebAuth 403：来源 IP ' . $ip . ' 不在白名单。'
                    . '若站点在 CDN/代理后，请设 web.trust_proxy=true 或改用 Basic Auth（清空 ip_whitelist）。');
                http_response_code(403);
                exit('Forbidden');
            }
        }

        $user = $cfg['auth_user'] ?? '';
        $pass = $cfg['auth_pass'] ?? '';
        if ($user === '' || $pass === '' || $pass === 'CHANGE_ME') {
            http_response_code(503);
            exit('内部页面未配置访问口令（app/cj/config/config.php → web.auth_pass）');
        }

        [$givenUser, $givenPass] = self::basicCredentials();
        if (!hash_equals($user, $givenUser) || !hash_equals($pass, $givenPass)) {
            header('WWW-Authenticate: Basic realm="cj internal"');
            http_response_code(401);
            exit('Unauthorized');
        }
    }

    /**
     * 解析 Basic Auth 凭据。
     * FastCGI/PHP-FPM 下 $_SERVER['PHP_AUTH_USER'] 常为空（Authorization 头未透传），
     * 此时改从 Authorization 头自行解析，避免"输对密码仍反复弹框"。
     * 服务器侧仍建议透传该头（见 zp_html/cj/.htaccess 或 Nginx fastcgi_param）。
     *
     * @return array{0:string,1:string} [user, pass]
     */
    private static function basicCredentials(): array
    {
        $user = $_SERVER['PHP_AUTH_USER'] ?? '';
        if ($user !== '') {
            return [$user, $_SERVER['PHP_AUTH_PW'] ?? ''];
        }

        // 多路径兜底获取 Authorization 头
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
            $header = $headers['authorization'] ?? '';
        }

        if (stripos($header, 'basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$u, $p] = explode(':', $decoded, 2);
                return [$u, $p];
            }
        }
        return ['', ''];
    }

    /**
     * 客户端 IP。默认取 REMOTE_ADDR；仅当 web.trust_proxy=true（站点确在可信代理/CDN 后）
     * 才取 X-Forwarded-For 最左 IP——该头可伪造，非可信代理环境下切勿开启。
     */
    private static function clientIp(array $cfg): string
    {
        if (!empty($cfg['trust_proxy'])) {
            $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($xff !== '') {
                return trim(explode(',', $xff)[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
