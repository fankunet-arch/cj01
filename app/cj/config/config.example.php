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
        // 'db'   = 方案 B：直连主库（去重只读；导入/清理需写权限）
        // 'api'  = 方案 A：调用主站内部去重接口
        'mode' => 'off',

        // 采集器与主站同一部署，推荐复用主站 config.php，避免重复维护主库账号密码。
        // 默认读取 app/config/config.php 的 db 段与 prefix，表名由 prefix 派生。
        'zhaopin_config' => __DIR__ . '/../../config/config.php',

        // mode=db 时使用。
        'db' => [
            // reuse_zhaopin=true：host/port/name/user/pass 及表名全部取自上面的
            // zhaopin_config（主站库），本块其余字段忽略。
            // ⚠ 此时该账号需具备导入/清理所需的写、删权限（不能是只读账号）。
            'reuse_zhaopin' => true,

            // reuse_zhaopin=false 时才用下面这份独立连接（例如想用只读账号仅做去重比对）：
            'host'             => '127.0.0.1',
            'port'             => 3306,
            'name'             => 'CHANGE_ME',          // ← zhaopin 主库名
            'user'             => 'crawler_ro',
            'pass'             => 'CHANGE_ME',
            'charset'          => 'utf8mb4',
            'posts_table'      => 'zhaopin_posts',      // 招聘帖表
            'regions_table'    => 'zhaopin_regions',    // 地区表（名称→region_id 映射）
            'categories_table' => 'zhaopin_categories', // 分类表（名称→category_id 映射）
        ],

        // mode=api 时使用
        'api' => [
            'url'   => 'https://www.zhaopin.es/internal/dedup-check',
            'token' => 'CHANGE_ME',
        ],

        // 导入映射：采集数据 → zhaopin_posts 必填字段的取值。
        // type/poster_type/status 已按主站 publish.php 实际取值确认（招聘/游客/在线）；
        // phone_norm、content_hash、simhash 由导入代码自动按主站算法生成，无需配置。
        'import' => [
            'type'                => 1,  // 1=招聘 / 2=求职（主站 publish.php 语义），采集为招聘
            'poster_type'         => 1,  // 1=游客发布 / 2=注册用户（采集无用户，取 1）
            'status'              => 1,  // 1=在线显示（主站发布即 status=1）
            // ⚠ 仍需你确认：城市/区域、分类无法按名称匹配到主库时的兜底外键 id，
            // 必须填主库 zhaopin_regions / zhaopin_categories 中真实存在的
            //「其他/未分类」记录 id（不能留 0，否则外键值无意义）。
            'default_region_id'   => 0,
            'default_category_id' => 0,
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
        // 调试模式（强制开）：true 时永久启用调试间隔，网页开关无法关闭。
        // 一般留 false，用概览页的「开启调试模式」按钮临时开关即可。
        // ⚠ 生产务必保持 false（保持 1 小时间隔的硬性要求）。
        'debug'                => false,
        // 调试模式下的采集间隔（秒，下限 10）。网页开关或 debug=true 时生效。
        'debug_interval'       => 60,
        // 正常采集触发最小间隔（秒）。非调试时硬下限 1 小时（配置小于 3600 按 3600）。
        // 另：命令行 `crawl.php --force` 可无视间隔立即采集，最适合调试单站。
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
        // 采集器挂载的 URL 基础路径（域名 http://zhaopin.es/cj/ → '/cj'）。
        // 页面内的静态资源与导航链接都以此为前缀；换路径只改这里。
        'base_path'    => '/cj',
        'auth_user'    => 'admin',
        'auth_pass'    => 'CHANGE_ME',   // ← 必改：留 CHANGE_ME/空会 503
        // IP 白名单：如 ['1.2.3.4']；留空 [] 则只用 Basic Auth（推荐先这样）。
        // ⚠ 若站点在 CDN/代理后，REMOTE_ADDR 是代理 IP，直接填公网 IP 会 403。
        'ip_whitelist' => [],
        // 仅当站点确在可信代理/CDN 后、且要用 IP 白名单时才设 true：
        // 改从 X-Forwarded-For 取客户端 IP（该头可伪造，非代理环境勿开）。
        'trust_proxy'  => false,
    ],

    // ---- 告警（Brevo 邮件，文档 §7） ----
    'alert' => [
        'brevo_api_key' => '',
        'from_email'    => 'crawler@zhaopin.es',
        'to_email'      => '',
    ],

    'log_dir' => __DIR__ . '/../logs',
];
