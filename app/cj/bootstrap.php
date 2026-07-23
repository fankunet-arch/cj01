<?php
/**
 * 采集器统一引导文件。
 * zp_html/cj 下的 Web 入口与 app/cj/bin 下的 CLI 脚本都从这里启动。
 * 本文件位于 web root 之外，网络不可直达。
 */

declare(strict_types=1);

define('CJ_APP_ROOT', __DIR__);
define('CJ_PROJECT_ROOT', dirname(__DIR__));

date_default_timezone_set('Europe/Madrid');
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', CJ_APP_ROOT . '/logs/php_error.log');

// PSR-4 风格自动加载：Cj\Foo\Bar → app/cj/src/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Cj\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $path = CJ_APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Composer 依赖（Guzzle / DomCrawler 等，可选：核心功能不依赖 vendor 也能跑）
if (is_file(CJ_APP_ROOT . '/vendor/autoload.php')) {
    require CJ_APP_ROOT . '/vendor/autoload.php';
}

/** 全局配置访问器 */
function cj_config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $file = CJ_APP_ROOT . '/config/config.php';
        if (!is_file($file)) {
            fwrite(STDERR, "缺少配置文件 app/cj/config/config.php（可从 config.example.php 复制）\n");
            exit(1);
        }
        $config = require $file;
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? null;
}
