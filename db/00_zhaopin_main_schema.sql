-- ============================================================
-- zhaopin.es 主库建表文件（MySQL / MariaDB 通用）
--
-- 用途：全新搭建主库时使用。结构与原 phpMyAdmin 导出完全一致，
--       仅做跨库兼容化处理，并把采集器所需的两列直接内建（无需再打补丁）。
--
-- 【与原导出的差异】
--   1) 排序规则 utf8mb4_0900_ai_ci → utf8mb4_unicode_520_ci
--      原值是 MySQL 8.0+ 专有，MariaDB 报 Unknown collation 导致导入失败。
--      520（UCA 5.2.0）是两边都有、版本又最新的 UCA 排序规则，
--      正确处理西语重音与大小写（Málaga=Malaga、España=Espana、a=A），
--      汉字按码位区分（张≠章）。
--      已知差异（实测）：不同 emoji 在 MySQL 8.0 下判为不等、MariaDB 10.11 下
--      判为相等；两边通用的排序规则里没有能区分 emoji 的，对本项目也无实质
--      影响（去重走 content_hash/phone_norm，不依赖排序规则）。详见
--      db/01_crawler_db_schema.sql 头部说明。
--   2) 显式 ROW_FORMAT=DYNAMIC
--      InnoDB 索引长度上限依赖行格式：DYNAMIC 3072 字节，老的 COMPACT 仅 767 字节。
--      本库 zhaopin_admins.uk_email 是 VARCHAR(255) utf8mb4 唯一索引（1020 字节），
--      在 COMPACT 下会直接建表失败。显式声明可免受服务器默认配置影响。
--   3) 主键 / AUTO_INCREMENT / 索引改为建表时内联声明
--      原导出是 phpMyAdmin 风格的「先建表、再 ALTER 加主键、再 ALTER 加 AUTO_INCREMENT」，
--      内联写法两边通用且一次成型，避免中途失败留下半成品表。
--   4) zhaopin_posts 内建 simhash 与 origin 两列（原为 db/02 的 ALTER 补丁内容）
--      · simhash：采集器三级去重的内容指纹
--      · origin ：数据来源标记（user=自有UGC / crawler=冷启动采集导入），
--                 清理采集数据时作双保险，默认 'user' 不影响主站自身逻辑
--
-- 【用法】先建库并选库，再导入本文件（MySQL 与 MariaDB 命令相同）：
--   CREATE DATABASE `你的库名` DEFAULT CHARACTER SET utf8mb4
--       DEFAULT COLLATE utf8mb4_unicode_520_ci;
--   mysql   -u root -p 你的库名 < db/00_zhaopin_main_schema.sql
--   mariadb -u root -p 你的库名 < db/00_zhaopin_main_schema.sql
--
-- 注：本文件只建结构，不含任何数据。全新搭建时必须紧接着导入基础数据：
--       mysql -u root -p 你的库名 < db/04_zhaopin_seed_data.sql
--     否则发布页地区/类别下拉为空、后台「参数配置」页空白、且无人能登录后台。
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 管理员（后台入口 /c/cp/，邮箱白名单）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_admins` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`        VARCHAR(255) NOT NULL,
    `role`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `google_sub`   VARCHAR(64) DEFAULT NULL,
    `display_name` VARCHAR(60) DEFAULT NULL,
    `status`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 职位类别（正式表）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(30) NOT NULL,
    `sort`       INT NOT NULL DEFAULT 0,
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`),
    KEY `idx_status_sort` (`status`, `sort`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 新类别建议（待审核，与正式表物理隔离）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_categories_pending` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(30) NOT NULL,
    `submit_ip`    VARBINARY(16) DEFAULT NULL,
    `user_id`      BIGINT UNSIGNED DEFAULT NULL,
    `status`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `submitted_at` DATETIME NOT NULL,
    `reviewed_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 联系方式查看日志（频率限制 / 统计）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_contact_log` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id`      BIGINT UNSIGNED NOT NULL,
    `contact_type` TINYINT UNSIGNED NOT NULL,
    `viewer_ip`    VARBINARY(16) NOT NULL,
    `created_at`   DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ip_time` (`viewer_ip`, `created_at`),
    KEY `idx_post_type` (`post_id`, `contact_type`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 置顶券
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_coupons` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`        VARCHAR(20) NOT NULL,
    `top_days`    SMALLINT UNSIGNED NOT NULL,
    `valid_until` DATETIME NOT NULL,
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `user_id`     BIGINT UNSIGNED DEFAULT NULL,
    `post_id`     BIGINT UNSIGNED DEFAULT NULL,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME NOT NULL,
    `redeemed_at` DATETIME DEFAULT NULL,
    `used_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_user` (`user_id`, `status`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- “信息失效”标记（同一 IP 对同一帖只能标一次）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_invalid_marks` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id`    BIGINT UNSIGNED NOT NULL,
    `marker_ip`  VARBINARY(16) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_post_ip` (`post_id`, `marker_ip`),
    KEY `idx_post` (`post_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 敏感词 / 关键词
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_keywords` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `word`       VARCHAR(50) NOT NULL,
    `type`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_word` (`word`),
    KEY `idx_type_status` (`type`, `status`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 帖子（招聘/求职信息主表）
-- simhash / origin 为采集器配套列（原 db/02 补丁，此处已内建）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_posts` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `public_code`   CHAR(10) NOT NULL,
    `type`          TINYINT UNSIGNED NOT NULL,
    `content`       VARCHAR(1000) NOT NULL,
    `content_hash`  CHAR(64) NOT NULL,
    `simhash`       BIGINT UNSIGNED DEFAULT NULL COMMENT '内容指纹 SimHash(64bit)，采集器三级去重用',
    `contact_name`  VARCHAR(50) NOT NULL,
    `phone`         VARCHAR(30) NOT NULL,
    `phone_norm`    VARCHAR(20) NOT NULL,
    `wechat`        VARCHAR(60) DEFAULT NULL,
    `region_id`     INT UNSIGNED NOT NULL,
    `category_id`   INT UNSIGNED NOT NULL,
    `poster_type`   TINYINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED DEFAULT NULL,
    `is_top`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `top_expire_at` DATETIME DEFAULT NULL,
    `invalid_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `phone_views`   INT UNSIGNED NOT NULL DEFAULT 0,
    `wechat_views`  INT UNSIGNED NOT NULL DEFAULT 0,
    `report_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `suspicious`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `origin`        VARCHAR(20) NOT NULL DEFAULT 'user'
                    COMMENT '数据来源：user=自有UGC / crawler=冷启动采集导入',
    `post_ip`       VARBINARY(16) DEFAULT NULL,
    `created_at`    DATETIME NOT NULL,
    `bumped_at`     DATETIME NOT NULL,
    `updated_at`    DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_public_code` (`public_code`),
    KEY `idx_browse` (`type`, `status`, `is_top`, `bumped_at`),
    KEY `idx_browse_cat` (`type`, `status`, `category_id`, `bumped_at`),
    KEY `idx_browse_region` (`type`, `status`, `region_id`, `bumped_at`),
    KEY `idx_dedup` (`phone_norm`, `content_hash`),
    KEY `idx_user` (`user_id`, `status`),
    KEY `idx_top` (`is_top`, `top_expire_at`),
    KEY `idx_simhash` (`simhash`),
    KEY `idx_origin` (`origin`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 地区（大区 / 城市两级）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_regions` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `name`      VARCHAR(50) NOT NULL,
    `level`     TINYINT UNSIGNED NOT NULL,
    `sort`      INT NOT NULL DEFAULT 0,
    `status`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_parent` (`parent_id`, `status`, `sort`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 举报
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_reports` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id`     BIGINT UNSIGNED NOT NULL,
    `reason`      VARCHAR(200) DEFAULT NULL,
    `reporter_ip` VARBINARY(16) DEFAULT NULL,
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL,
    `handled_at`  DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_post` (`post_id`),
    KEY `idx_status` (`status`),
    KEY `idx_dedup` (`post_id`, `reporter_ip`, `created_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 站点设置（键值对）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_settings` (
    `skey`       VARCHAR(64) NOT NULL,
    `svalue`     TEXT,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`skey`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- ------------------------------------------------------------
-- 注册用户（Google OAuth）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zhaopin_users` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `public_code`   CHAR(8) NOT NULL,
    `google_sub`    VARCHAR(64) NOT NULL,
    `display_name`  VARCHAR(60) DEFAULT NULL,
    `status`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL,
    `last_login_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_google_sub` (`google_sub`),
    UNIQUE KEY `uk_public_code` (`public_code`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
