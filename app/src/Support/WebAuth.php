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
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!in_array($ip, $whitelist, true)) {
                http_response_code(403);
                exit('Forbidden');
            }
        }

        $user = $cfg['auth_user'] ?? '';
        $pass = $cfg['auth_pass'] ?? '';
        if ($user === '' || $pass === '' || $pass === 'CHANGE_ME') {
            http_response_code(503);
            exit('内部页面未配置访问口令（app/config/config.php → web.auth_pass）');
        }

        $givenUser = $_SERVER['PHP_AUTH_USER'] ?? '';
        $givenPass = $_SERVER['PHP_AUTH_PW'] ?? '';
        if (!hash_equals($user, $givenUser) || !hash_equals($pass, $givenPass)) {
            header('WWW-Authenticate: Basic realm="cj internal"');
            http_response_code(401);
            exit('Unauthorized');
        }
    }
}
