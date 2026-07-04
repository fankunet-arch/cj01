<?php
/**
 * 采集器配置模板。
 * 复制为 config.php 并填入真实值。config.php 含密钥，严禁进 web root、严禁提交仓库。
 */

return [

    // ---- 采集库（crawler_db，读写） ----
    'crawler_db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'crawler_db',
        'user'    => 'crawler',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    // ---- zhaopin 主库比对（三级去重，见文档 §4.4） ----
    'main' => [
        // 'off'  = 不做主库比对（P4 之前）
        // 'db'   = 方案 B：只读账号直连主库
        // 'api'  = 方案 A：调用主站内部去重接口
        'mode' => 'off',

        // mode=db 时使用（只读账号；导入/清理阶段需要写权限的账号可单独配置）
        'db' => [
            'host'       => '127.0.0.1',
            'port'       => 3306,
            'name'       => 'zhaopin_db',
            'user'       => 'crawler_ro',
            'pass'       => 'CHANGE_ME',
            'charset'    => 'utf8mb4',
            'jobs_table' => 'jobs',        // 主库招聘表名，按实际调整
        ],

        // mode=api 时使用
        'api' => [
            'url'   => 'https://zhaopin.es/internal/dedup-check',
            'token' => 'CHANGE_ME',
        ],
    ],

    // ---- 去重阈值（文档 §3.3 / §3.4） ----
    'dedup' => [
        'hamming_dup'            => 3,  // 距离 ≤ 3 判定重复
        'hamming_review'         => 8,  // 距离 4–8 进人工复核队列
        'hamming_dup_no_contact' => 2,  // 联系方式缺失时收紧：≤ 2 才自动判重
    ],

    // ---- 采集行为 ----
    'crawl' => [
        // 采集触发最小间隔（秒）。硬下限 1 小时：配置小于 3600 时按 3600 生效
        'min_trigger_interval' => 3600,
        // Web 一键采集后台拉起 CLI 所用的 php 可执行文件（FPM 下 PHP_BINARY 不是 CLI，勿用）
        'php_cli'             => 'php',
        'purge_after_days'    => 90,   // 采集入库时写 purge_after = 今天 + N 天
        'stop_after_known'    => 5,    // 增量采集：连续 M 条已存在即停止翻页（§6.4）
        'max_pages_per_run'   => 10,   // 单站单次采集页数上限（§6.1）
        'retry_times'         => 3,    // 单页失败重试次数（指数退避）
        'title_empty_alert'   => 0.5,  // title 空值率超此比例判定疑似改版，暂停并告警（§6.5）
        'user_agents' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
        ],
    ],

    // ---- 内部页面（复核/看板）访问控制 ----
    'web' => [
        'auth_user'    => 'admin',
        'auth_pass'    => 'CHANGE_ME',
        'ip_whitelist' => [],          // 如 ['1.2.3.4']；留空则只做 Basic Auth
    ],

    // ---- 告警（Brevo 邮件，文档 §7） ----
    'alert' => [
        'brevo_api_key' => '',
        'from_email'    => 'crawler@zhaopin.es',
        'to_email'      => '',
    ],

    'log_dir' => __DIR__ . '/../logs',
];
