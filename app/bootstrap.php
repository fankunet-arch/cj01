<?php
declare(strict_types=1);

/**
 * 公共启动文件：所有 zp_html/ 薄壳入口的唯一 require 目标。
 * 职责：载入机密配置 → 公共类库 → 注册表，并提供 zp_dispatch() 分发函数。
 * 本文件位于 document root 之外（app/），URL 不可访问。
 */

define('ZP_APP_PATH', __DIR__);

// 对外零内部信息：错误细节只进日志，页面一律通用文案
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $e): void {
    error_log('[zhaopin] uncaught: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    exit('服务暂时不可用，请稍后再试');
});

// 机密配置不进版本库：部署时把 config/config.example.php 复制为 config/config.php 填写
$zpConfigFile = ZP_APP_PATH . '/config/config.php';
if (!is_file($zpConfigFile)) {
    error_log('[zhaopin] config missing: ' . $zpConfigFile);
    http_response_code(500);
    exit('服务暂时不可用，请稍后再试');
}
$GLOBALS['ZP_CONFIG'] = require $zpConfigFile;

require ZP_APP_PATH . '/lib/db.php';
require ZP_APP_PATH . '/lib/view.php';
require ZP_APP_PATH . '/lib/response.php';
require ZP_APP_PATH . '/lib/util.php';
require ZP_APP_PATH . '/lib/settings.php';
require ZP_APP_PATH . '/lib/session.php';
require ZP_APP_PATH . '/lib/auth.php';
require ZP_APP_PATH . '/lib/posts.php';
require ZP_APP_PATH . '/lib/mail.php';

$GLOBALS['ZP_REGISTRY'] = require ZP_APP_PATH . '/registry.php';

/**
 * 读取配置项，支持 'db.host' 点号路径。
 */
function zp_config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['ZP_CONFIG'];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/**
 * 注册表分发：薄壳声明功能名，这里定位并执行 app/handlers/ 内的处理器。
 * 注册表兼作白名单——未登记的功能名一律 404。
 */
function zp_dispatch(string $feature): void
{
    $registry = $GLOBALS['ZP_REGISTRY'];
    if (!isset($registry[$feature])) {
        http_response_code(404);
        exit('Not Found');
    }
    require ZP_APP_PATH . '/handlers/' . $registry[$feature];
}
