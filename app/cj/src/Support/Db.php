<?php

declare(strict_types=1);

namespace Cj\Support;

use PDO;

/**
 * PDO 连接管理：采集库读写 + zhaopin 主库只读比对（文档 §4.4 方案 B）。
 */
final class Db
{
    private static ?PDO $crawler = null;
    private static ?PDO $main = null;

    public static function crawler(): PDO
    {
        if (self::$crawler === null) {
            self::$crawler = self::connect(cj_config('crawler_db'));
        }
        return self::$crawler;
    }

    /** 主库连接（mode=db 时可用；调用方需先检查 main.mode） */
    public static function main(): PDO
    {
        if (self::$main === null) {
            $cfg = cj_config('main');
            if (($cfg['mode'] ?? 'off') !== 'db') {
                throw new \RuntimeException('main.mode 未配置为 db，无法直连主库');
            }
            // 复用主站 config.php 或采集器自填，由 cj_main_db_config() 统一解析
            self::$main = self::connect(cj_main_db_config());
        }
        return self::$main;
    }

    private static function connect(array $c): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'], (int) $c['port'], $c['name'], $c['charset'] ?? 'utf8mb4'
        );
        return new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
