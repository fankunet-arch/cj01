<?php

declare(strict_types=1);

namespace Cj\Support;

/**
 * 极简文件日志。日志目录在 web root 之外，不可对外。
 */
final class Logger
{
    public static function log(string $channel, string $level, string $message): void
    {
        $dir = cj_config('log_dir') ?: CJ_APP_ROOT . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
        @file_put_contents($dir . '/' . $channel . '-' . date('Ymd') . '.log', $line, FILE_APPEND | LOCK_EX);
        if (PHP_SAPI === 'cli') {
            fwrite($level === 'error' ? STDERR : STDOUT, $line);
        }
    }

    public static function info(string $channel, string $message): void
    {
        self::log($channel, 'info', $message);
    }

    public static function error(string $channel, string $message): void
    {
        self::log($channel, 'error', $message);
    }
}
