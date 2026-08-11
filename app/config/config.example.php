<?php
/**
 * 主站机密配置模板。
 *
 * 【部署步骤】把本文件复制为同目录下的 config.php 再填写：
 *     cp app/config/config.example.php app/config/config.php
 *
 * config.php 含数据库口令与 OAuth 密钥，已在 .gitignore 中排除，切勿提交版本库。
 * 缺少 config.php 时 app/bootstrap.php 会直接 500（全站不可用）。
 *
 * 本文件位于 document root 之外（app/），URL 不可访问。
 */

return [

    // ── 数据库 ──────────────────────────────────────────────
    // MySQL 8.0/8.4 与 MariaDB 10.5+ 均可，库本身用
    //   db/00_zhaopin_main_schema.sql（建表）+ db/04_zhaopin_seed_data.sql（基础数据）
    // 建立。建库语句：
    //   CREATE DATABASE zhaopin DEFAULT CHARACTER SET utf8mb4
    //     DEFAULT COLLATE utf8mb4_unicode_520_ci;
    'db' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'name'   => 'zhaopin',
        'user'   => 'zhaopin',
        'pass'   => '',
        'prefix' => 'zhaopin_',   // 表前缀，与 SQL 文件中的表名一致，通常不改
    ],

    // ── Google 登录 ─────────────────────────────────────────
    // 后台 /c/cp/ 与用户登录 /user/login 都走 Google OAuth，站内零密码。
    // 在 Google Cloud Console → API 和服务 → 凭据 建「OAuth 2.0 客户端 ID」，
    // 「已获授权的重定向 URI」需同时填这两条（域名换成你的）：
    //     https://zhaopin.es/c/cp/login
    //     https://zhaopin.es/user/login
    // 留空则登录页显示「登录服务尚未配置」，其余页面正常。
    'google_oauth' => [
        'client_id'     => '',
        'client_secret' => '',
    ],

    // ── 邮件（Brevo）────────────────────────────────────────
    // 用于举报通知等站务邮件。api_key 留空则不发信，功能其余部分不受影响。
    'brevo' => [
        'api_key'    => '',
        'from_email' => 'no-reply@zhaopin.es',
        'from_name'  => '西华招聘',
    ],

    // ── 站点 ────────────────────────────────────────────────
    // base_url 不带结尾斜杠；OAuth 回调地址由它拼出，必须与线上真实域名一致
    // （含协议，线上用 https）。
    'site' => [
        'base_url' => 'https://zhaopin.es',
        'name'     => '西华招聘',
    ],

    // ── 本地调试直登 ★ 生产环境必须留空 ★ ───────────────────
    // 非空时登录页会出现一个「本地调试直登」按钮，可跳过 Google 直接以该身份进入。
    // 线上填了等于后台无密码敞开，务必保持空字符串。
    'dev' => [
        'fake_admin_email' => '',   // 例：本地调试填 'you@example.com'（须在 zhaopin_admins 中）
        'fake_user_name'   => '',   // 例：本地调试填 '测试用户'
    ],
];
