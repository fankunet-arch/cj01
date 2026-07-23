<?php
declare(strict_types=1);

/**
 * 数据库封装：懒连接 PDO 单例。页面不触库时不建连接。
 */
function zp_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            zp_config('db.host'),
            (int) zp_config('db.port', 3306),
            zp_config('db.name')
        );
        $pdo = new PDO($dsn, (string) zp_config('db.user'), (string) zp_config('db.pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * 带前缀表名（前缀 zhaopin_，见 docs/03_数据库设计.md）。
 */
function zp_table(string $name): string
{
    return (string) zp_config('db.prefix', 'zhaopin_') . $name;
}
