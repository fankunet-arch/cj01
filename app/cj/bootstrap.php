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

/**
 * 解析 zhaopin 主库连接配置。
 * main.db.reuse_zhaopin=true 时直接复用主站 config.php（app/config/config.php）的
 * db 段与 prefix，避免在采集器里重复维护主库账号密码；表名由 prefix 派生。
 * 否则使用采集器自身 main.db 内填写的连接与表名。
 * 返回：host/port/name/user/pass/charset/posts_table/regions_table/categories_table
 */
function cj_main_db_config(): array
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }
    $main = cj_config('main') ?? [];
    $db = $main['db'] ?? [];

    if (!empty($db['reuse_zhaopin'])) {
        $path = $main['zhaopin_config'] ?? (CJ_PROJECT_ROOT . '/config/config.php');
        if (!is_file($path)) {
            throw new \RuntimeException("main.db.reuse_zhaopin=true 但主站配置不存在：$path");
        }
        $zp = require $path;
        $zpDb = $zp['db'] ?? [];
        $prefix = (string) ($zpDb['prefix'] ?? 'zhaopin_');
        return $resolved = [
            'host'             => $zpDb['host'] ?? '127.0.0.1',
            'port'             => (int) ($zpDb['port'] ?? 3306),
            'name'             => (string) ($zpDb['name'] ?? ''),
            'user'             => (string) ($zpDb['user'] ?? ''),
            'pass'             => (string) ($zpDb['pass'] ?? ''),
            'charset'          => 'utf8mb4',
            'posts_table'      => $prefix . 'posts',
            'regions_table'    => $prefix . 'regions',
            'categories_table' => $prefix . 'categories',
        ];
    }

    return $resolved = $db + [
        'charset'          => 'utf8mb4',
        'posts_table'      => 'zhaopin_posts',
        'regions_table'    => 'zhaopin_regions',
        'categories_table' => 'zhaopin_categories',
    ];
}
