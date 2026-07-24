<?php

declare(strict_types=1);

namespace Cj\Scheduler;

use Cj\Support\Db;
use Cj\Support\Logger;

/**
 * 采集触发闸门：一键采集按钮与 CLI/cron 共用。
 *
 * 规则：
 *  1. 采集间隔硬下限 1 小时（MIN_INTERVAL），配置只能调大不能调小；
 *  2. 已有采集任务在运行时拒绝再次触发；
 *  3. 触发时间戳存文件锁（flock 原子读写），Web 与 CLI 竞争安全。
 */
final class CrawlControl
{
    /** 生产硬下限：采集间隔不能小于 1 小时 */
    private const MIN_INTERVAL = 3600;

    /** 调试模式下的安全下限：仍不允许无限连点（防误触打爆目标站） */
    private const DEBUG_MIN_INTERVAL = 10;

    public static function minInterval(): int
    {
        $crawl = cj_config('crawl') ?? [];
        // 调试模式（配置强制开 或 网页开关开）：采集间隔缩短，便于反复调试。
        if (self::debugEnabled()) {
            $dbg = (int) ($crawl['debug_interval'] ?? 60);   // 默认 60 秒
            return max(self::DEBUG_MIN_INTERVAL, $dbg);
        }
        $configured = (int) ($crawl['min_trigger_interval'] ?? self::MIN_INTERVAL);
        return max(self::MIN_INTERVAL, $configured);
    }

    private static function stateDir(): string
    {
        $dir = cj_config('log_dir') ?: CJ_APP_ROOT . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }

    private static function lockFile(): string
    {
        return self::stateDir() . '/crawl_trigger.lock';
    }

    private static function debugFlagFile(): string
    {
        return self::stateDir() . '/debug.enabled';
    }

    /** 调试模式是否开启：配置 crawl.debug 强制开，或网页开关（标记文件）开。 */
    public static function debugEnabled(): bool
    {
        if (!empty(cj_config('crawl')['debug'])) {
            return true;
        }
        return is_file(self::debugFlagFile());
    }

    /** 配置是否强制开启调试（此时网页开关无法关闭）。 */
    public static function debugForcedByConfig(): bool
    {
        return !empty(cj_config('crawl')['debug']);
    }

    /** 网页开关：开/关调试模式（写/删标记文件）。 */
    public static function setDebug(bool $on): void
    {
        $file = self::debugFlagFile();
        if ($on) {
            @file_put_contents($file, (string) time());
        } else {
            @unlink($file);
        }
    }

    /** 上次触发时间戳（文件锁记录与 cj_crawl_runs 取较新者）。 */
    public static function lastTriggeredAt(): ?int
    {
        $fromFile = null;
        $file = self::lockFile();
        if (is_file($file)) {
            $ts = (int) trim((string) @file_get_contents($file));
            $fromFile = $ts > 0 ? $ts : null;
        }

        $fromDb = null;
        try {
            $v = Db::crawler()->query('SELECT MAX(started_at) FROM cj_crawl_runs')->fetchColumn();
            if ($v) {
                $fromDb = strtotime((string) $v) ?: null;
            }
        } catch (\Throwable) {
            // 采集库不可达时退化为仅看文件锁
        }

        if ($fromFile === null) {
            return $fromDb;
        }
        return $fromDb === null ? $fromFile : max($fromFile, $fromDb);
    }

    /** 是否有采集任务正在运行（6 小时前的 running 视为僵尸记录，不阻塞）。 */
    public static function isRunning(): bool
    {
        try {
            return (bool) Db::crawler()->query(
                "SELECT 1 FROM cj_crawl_runs
                 WHERE status = 'running' AND started_at > NOW() - INTERVAL 6 HOUR
                 LIMIT 1"
            )->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** 供页面展示的状态。 */
    public static function status(): array
    {
        $last = self::lastTriggeredAt();
        $nextAllowed = $last !== null ? $last + self::minInterval() : null;
        $running = self::isRunning();
        $canTrigger = !$running && ($nextAllowed === null || time() >= $nextAllowed);

        $debug = self::debugEnabled();
        $interval = self::minInterval();
        $reason = null;
        if ($running) {
            $reason = '已有采集任务正在运行';
        } elseif (!$canTrigger && $nextAllowed !== null) {
            $reason = $debug
                ? sprintf('调试模式：间隔 %d 秒，%s 后可再次触发', $interval, date('H:i:s', $nextAllowed))
                : sprintf('采集间隔不能小于 1 小时，%s 后可再次触发', date('Y-m-d H:i', $nextAllowed));
        }

        return [
            'running'         => $running,
            'last_triggered'  => $last !== null ? date('Y-m-d H:i:s', $last) : null,
            'next_allowed_at' => $nextAllowed !== null ? date('Y-m-d H:i:s', $nextAllowed) : null,
            'can_trigger'     => $canTrigger,
            'reason'          => $reason,
            'debug'           => $debug,
            'debug_forced'    => self::debugForcedByConfig(),
            'interval'        => $interval,
        ];
    }

    /**
     * 原子获取采集许可：flock 下检查间隔与运行态，通过则写入本次触发时间。
     * $force=true 跳过检查但仍记录时间（仅 CLI 调试用）。
     */
    public static function acquire(bool $force = false): array
    {
        $fp = @fopen(self::lockFile(), 'c+');
        if ($fp === false) {
            return ['ok' => false, 'message' => '无法打开触发锁文件（检查 app/cj/logs 写权限）'];
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return ['ok' => false, 'message' => '无法获取触发锁'];
        }
        try {
            if (!$force) {
                if (self::isRunning()) {
                    return ['ok' => false, 'message' => '已有采集任务正在运行，请等待其完成'];
                }
                $last = self::lastTriggeredAt();
                $nextAllowed = $last !== null ? $last + self::minInterval() : 0;
                if (time() < $nextAllowed) {
                    return ['ok' => false, 'message' =>
                        sprintf('采集间隔不能小于 1 小时（上次 %s），%s 后可再次触发',
                            date('H:i', (int) $last), date('Y-m-d H:i', $nextAllowed))];
                }
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) time());
            fflush($fp);
            return ['ok' => true, 'message' => '已获取采集许可'];
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * 一键采集（Web 按钮调用）：获取许可后后台拉起 CLI 全站采集。
     * Web 进程不做抓取（单页间隔 8–20 秒，请求内跑不完），核心动作仍走 app/bin CLI。
     */
    public static function trigger(): array
    {
        $acquired = self::acquire();
        if (!$acquired['ok']) {
            return $acquired;
        }

        $php = (string) (cj_config('crawl')['php_cli'] ?? 'php');
        $script = CJ_APP_ROOT . '/bin/crawl.php';
        $logDir = cj_config('log_dir') ?: CJ_APP_ROOT . '/logs';
        $logFile = $logDir . '/crawl-web-' . date('Ymd') . '.log';

        // --no-guard：许可已在本进程原子获取，CLI 侧不再重复闸门检查
        $cmd = sprintf(
            'nohup %s %s --all --no-guard >> %s 2>&1 &',
            escapeshellcmd($php),
            escapeshellarg($script),
            escapeshellarg($logFile)
        );
        exec($cmd, $out, $exit);
        if ($exit !== 0) {
            Logger::error('crawl', "一键采集拉起失败 exit=$exit cmd=$cmd");
            return ['ok' => false, 'message' => '后台采集进程拉起失败，请检查 php_cli 配置与日志'];
        }

        Logger::info('crawl', '一键采集已触发（Web），后台执行 crawl.php --all');
        return ['ok' => true, 'message' => '一键采集已启动，进度见运行看板（本次采集完成后 1 小时内不可再次触发）'];
    }
}
