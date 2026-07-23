-- ============================================================
-- 招聘采集程序 采集库（crawler_db）建库导入文件
-- 依据：《招聘采集程序_需求与设计文档_v1.2》 §5
-- 目标环境：MySQL 8.4
-- 导入方式：mysql -u root -p < db/01_crawler_db_schema.sql
-- 命名约定：所有采集库表统一 cj_ 前缀（cj = 采集）
-- ============================================================

CREATE DATABASE IF NOT EXISTS `crawler_db`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_0900_ai_ci;

USE `crawler_db`;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='清洗后的统一招聘数据模型';

-- ------------------------------------------------------------
-- 5.3 cj_dedup_log — 去重判定日志
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
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
    KEY `idx_site_time` (`source_site`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
  COMMENT='采集库↔主库导入映射账本';
