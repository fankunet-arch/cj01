-- ============================================================
-- 招聘采集程序 采集库建表文件（6 张 cj_ 表）
-- 依据：《招聘采集程序_需求与设计文档_v1.2》 §5
-- 命名约定：所有采集表统一 cj_ 前缀（cj = 采集）
--
-- 【本文件不建库、不选库】只建表，导进「你当前选中的那个库」。
--   共享主机（OVH / cPanel 等）的库账号没有 CREATE DATABASE 权限，
--   文件里若带 CREATE DATABASE 会直接报
--     #1044 - Access denied for user 'xxx'@'%' to database 'crawler_db'
--   所以建库这一步交给你：要么在主机控制面板里新建一个库，
--   要么干脆用主站那个库（推荐，见下）。
--
-- 【放哪个库？】两种都行，代码不关心：
--   A) 与主站同库（共享主机只给一个库时用这个，推荐）
--      6 张表全是 cj_ 前缀，和主站的 zhaopin_ 表不会撞名，可以安心共存。
--      导入：phpMyAdmin 里选中主站那个库 → 导入本文件。
--      配置：app/cj/config/config.php 的 crawler_db.name 填主站库名
--            （host/user/pass 也和主站一样）。
--   B) 独立一个库（能自己建库时用这个，冷启动结束后可整库删掉，更干净）
--      先建库再选中导入：
--        CREATE DATABASE `crawler_db` DEFAULT CHARACTER SET utf8mb4
--            DEFAULT COLLATE utf8mb4_unicode_520_ci;
--      配置：crawler_db.name 填该库名。
--   两种方式下采集器与主站都各自独立连接，没有任何跨库 JOIN。
--
-- 【跨库通用】本文件在 MySQL 8.0/8.4 与 MariaDB 10.2+ 上均可直接导入，
--             两种数据库之间可无缝切换，无需改动本文件。为此做了三处约定：
--
--   1) 排序规则 utf8mb4_unicode_520_ci（UCA 5.2.0）
--      · 是两边都有、且版本最新的 UCA 排序规则：
--        MySQL 5.6+/8.0/8.4 与 MariaDB 10.x 全部支持。
--        （utf8mb4_0900_ai_ci 是 MySQL 8.0+ 专有，MariaDB 报 Unknown collation；
--         utf8mb4_uca1400_ai_ci 是 MariaDB 11.x 专有，MySQL 不认——都会让整个
--         导入直接失败，故都不可用。）
--      · 正确处理西语重音与大小写：Málaga=Malaga、España=Espana、a=A，
--        搜索时用户不打重音也能命中——这正是本站需要的。
--      · 汉字按码位区分（张≠章），中文内容不会误判相等。
--      · 已知差异（实测）：MySQL 8.0 下不同 emoji 判为不等，MariaDB 10.11 下
--        判为相等（emoji 是 Unicode 6.0+ 才加入，晚于 UCA 5.2.0，MariaDB 给
--        这些码位同一权重）。两边通用的排序规则里没有能区分 emoji 的
--        （general_ci / unicode_ci 同样判等，只有 utf8mb4_bin 能区分，但它
--        连大小写都区分，会破坏上面的西语检索）。
--        对本项目无实质影响：去重靠 content_hash（SHA-256 精确比对）与
--        phone_norm（归一化后的纯数字），都不走排序规则；受影响的只有
--        类别名/敏感词这类唯一键的极端场景（两个名字只差一个 emoji）。
--
--   2) 显式 ROW_FORMAT=DYNAMIC
--      InnoDB 索引长度上限依赖行格式：DYNAMIC 为 3072 字节，
--      老的 COMPACT 仅 767 字节（utf8mb4 下连 VARCHAR(255) 唯一索引都建不了）。
--      两边现代版本默认虽都是 DYNAMIC，但显式声明可避免服务器配置差异导致导入失败。
--
--   3) 只用两边通用语法：无 MySQL 专有函数/子句，保留字 `signal` 已加反引号。
--
-- 导入方式（两种数据库命令相同，库名换成你实际选定的那个）：
--   命令行： mysql -u 用户名 -p 你的库名 < db/03_crawler_tables.sql
--            mariadb -u 用户名 -p 你的库名 < db/03_crawler_tables.sql
--   phpMyAdmin：先在左侧点中目标库，再「导入」上传本文件。
--               （不点中库直接导入会报 "No database selected"）
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 5.1 cj_raw_pages — 原始抓取存档
-- 保留原始 HTML，解析器出错时可重跑，不必重新抓取
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cj_raw_pages` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `source_site`   VARCHAR(20)  NOT NULL COMMENT '来源站点标识：oulang/ouhua/huarenjie/xihua',
    `source_url`    VARCHAR(768) NOT NULL COMMENT '抓取页 URL',
    `raw_html`      MEDIUMTEXT   COMMENT '原始 HTML',
    `http_status`   SMALLINT     COMMENT 'HTTP 状态码',
    `fetched_at`    DATETIME     NOT NULL COMMENT '抓取时间',
    UNIQUE KEY `uk_url` (`source_url`),
    KEY `idx_site` (`source_site`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
  COMMENT='原始抓取存档';

-- ------------------------------------------------------------
-- 5.2 cj_jobs_clean — 清洗后的统一模型（Canonical Job Model）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cj_jobs_clean` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `source_site`    VARCHAR(20)  NOT NULL COMMENT '来源站点',
    `source_url`     VARCHAR(768) NOT NULL COMMENT '详情页 URL（站内去重键）',
    `title`          VARCHAR(255) COMMENT '岗位标题',
    `company`        VARCHAR(255) COMMENT '招聘方 / 店名（常缺失）',
    `category`       VARCHAR(50)  COMMENT '行业分类：餐饮/百元店/工厂/家政等',
    `city`           VARCHAR(50)  COMMENT '城市：Madrid/Barcelona…',
    `district`       VARCHAR(50)  COMMENT '区域，如 Usera',
    `salary_raw`     VARCHAR(100) COMMENT '薪资原文',
    `description`    TEXT         COMMENT '岗位描述原文（指纹来源）',
    `contact_phone`  VARCHAR(30)  COMMENT '电话原文',
    `contact_wechat` VARCHAR(50)  COMMENT '微信号原文',
    `contact_name`   VARCHAR(50)  COMMENT '联系人',
    `phone_norm`     VARCHAR(15)  COMMENT '归一化电话（纯数字末9位）',
    `wechat_norm`    VARCHAR(50)  COMMENT '归一化微信（小写去空格）',
    `contact_key`    VARCHAR(70)  COMMENT '去重键 phone_norm|wechat_norm',
    `simhash`        BIGINT UNSIGNED COMMENT '64-bit 内容指纹 SimHash',
    `publish_date`   DATE         COMMENT '发布日期',
    `collected_at`   DATETIME     NOT NULL COMMENT '采集时间',
    `purge_after`    DATE         COMMENT '预期清理日期（用完即删可执行、可审计）',
    `dedup_status`   ENUM('unique','dup_site','dup_cross','exists_in_main','review')
                     DEFAULT 'unique' COMMENT '去重判定结果',
    `confidence`     ENUM('high','low') DEFAULT 'high' COMMENT '判定置信度',
    `import_ready`   TINYINT(1)   DEFAULT 0 COMMENT '1=待导入主库（人工确认后）',
    `imported_at`    DATETIME     NULL COMMENT '导入主库时间',
    UNIQUE KEY `uk_url` (`source_url`),
    KEY `idx_contact_key` (`contact_key`),
    KEY `idx_simhash` (`simhash`),
    KEY `idx_status` (`dedup_status`),
    KEY `idx_purge_after` (`purge_after`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
  COMMENT='清洗后的统一招聘数据模型';

-- ------------------------------------------------------------
-- 5.3 cj_dedup_log — 去重判定日志
-- 注：signal 是 MySQL 保留字，必须加反引号
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cj_dedup_log` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id`          BIGINT UNSIGNED NOT NULL COMMENT 'cj_jobs_clean.id',
    `matched_against` ENUM('crawler','main') NOT NULL COMMENT '命中采集库还是主库',
    `matched_id`      BIGINT UNSIGNED COMMENT '命中的记录 id（采集库或主库）',
    `signal`          ENUM('url','contact_key','simhash') NOT NULL COMMENT '命中信号',
    `hamming_dist`    TINYINT COMMENT 'simhash 命中时的汉明距离',
    `decision`        ENUM('dup','review','unique') NOT NULL COMMENT '判定结论',
    `created_at`      DATETIME NOT NULL,
    KEY `idx_job` (`job_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
  COMMENT='去重判定日志';

-- ------------------------------------------------------------
-- 5.4 cj_review_queue — 人工复核队列
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cj_review_queue` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_id`       BIGINT UNSIGNED NOT NULL COMMENT '待复核记录 cj_jobs_clean.id',
    `candidate_id` BIGINT UNSIGNED COMMENT '疑似重复对象 cj_jobs_clean.id',
    `reason`       VARCHAR(255) COMMENT '进入复核的原因',
    `resolved`     TINYINT(1) DEFAULT 0 COMMENT '是否已处理',
    `resolution`   ENUM('keep','merge','discard') NULL COMMENT '复核结论',
    `created_at`   DATETIME NOT NULL,
    KEY `idx_resolved` (`resolved`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
  COMMENT='人工复核队列';

-- ------------------------------------------------------------
-- 5.5 cj_crawl_runs — 采集任务记录（监控/看板用）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cj_crawl_runs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `source_site`   VARCHAR(20) NOT NULL COMMENT '站点标识',
    `started_at`    DATETIME NOT NULL,
    `finished_at`   DATETIME,
    `pages_fetched` INT DEFAULT 0 COMMENT '本次抓取页数',
    `new_jobs`      INT DEFAULT 0 COMMENT '新增记录数',
    `dup_jobs`      INT DEFAULT 0 COMMENT '判重丢弃数',
    `errors`        INT DEFAULT 0 COMMENT '错误数',
    `status`        ENUM('running','ok','failed') DEFAULT 'running',
    `note`          VARCHAR(500) COMMENT '备注/告警信息',
    KEY `idx_site_time` (`source_site`, `started_at`),
    KEY `idx_status_time` (`status`, `started_at`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
  COMMENT='采集任务运行记录';

-- ------------------------------------------------------------
-- 5.6 cj_import_map — 导入映射表（采集数据 ↔ 主库数据一一对应）
-- 此映射只存在于采集库，主库不持有反向指针。
-- 清理时以本表为“账本”精准定位主库对应记录。
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cj_import_map` (
    `id`             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `crawler_job_id` BIGINT UNSIGNED NOT NULL COMMENT '采集库 cj_jobs_clean.id',
    `main_job_id`    BIGINT UNSIGNED NOT NULL COMMENT 'zhaopin 主库招聘表 id',
    `import_batch`   VARCHAR(40)     NOT NULL COMMENT '导入批次号，便于按批回滚/清理',
    `imported_at`    DATETIME        NOT NULL,
    `purged`         TINYINT(1)      DEFAULT 0 COMMENT '主库对应记录是否已清理',
    `purged_at`      DATETIME        NULL,
    UNIQUE KEY `uk_main` (`main_job_id`),
    KEY `idx_crawler` (`crawler_job_id`),
    KEY `idx_batch` (`import_batch`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
  COMMENT='采集库↔主库导入映射账本';
