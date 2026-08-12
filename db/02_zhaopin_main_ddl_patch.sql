-- ============================================================
-- zhaopin.es 主库配合改动清单（DDL 补丁）
-- 依据：《招聘采集程序_需求与设计文档_v1.2》 §11，并对齐真实主库结构
-- 目标环境：MySQL 8.0/8.4 与 MariaDB 10.x 通用
--
-- 真实招聘表为 `zhaopin_posts`（不是文档假设的 `jobs`）。
-- 主库已自带 `phone_norm`（并入 idx_dedup）与 `content_hash`（精确去重），
-- 因此三级去重的“电话键”直接复用 phone_norm，无需再加 contact_key。
-- 本补丁只新增采集器缺失的两列：simhash（模糊去重）、origin（来源标记）。
--
-- ★★ 全新搭建的话，本文件根本不要导入 ★★
--   db/00_zhaopin_main_schema.sql 已经把 simhash / origin 两列内建在建表语句里，
--   建完表再导本文件，必然报：
--       #1060 - Duplicate column name 'simhash'
--   看到这个报错说明两列早就有了，是重复操作，不是故障 —— 忽略即可，
--   数据库没有被改坏（ALTER 失败不会留下半成品）。
--
--   本文件只用于一种场景：主库是早先建的、里面已经有数据、不能推倒重建，
--   只想增量补上采集器需要的这两列。
--
-- 【怎么确认要不要导】先查一下，有输出就说明已经有了，别导：
--   SHOW COLUMNS FROM `zhaopin_posts` LIKE 'simhash';
--
-- 【本文件不选库】导进你当前选中的那个库。
--   phpMyAdmin 里先在左侧点中主站库，再「导入」上传本文件。
-- ============================================================

-- ------------------------------------------------------------
-- 改动一：新增内容指纹列 simhash（三级去重的模糊比对，见文档 §3、§4.4）
-- 主库已有 phone_norm，故不再新增 contact_key —— 电话去重直接用 phone_norm。
-- ------------------------------------------------------------
ALTER TABLE `zhaopin_posts`
    ADD COLUMN `simhash` BIGINT UNSIGNED NULL
        COMMENT '内容指纹 SimHash(64bit)，采集器三级去重用' AFTER `content_hash`,
    ADD KEY `idx_simhash` (`simhash`);

-- 存量数据需回填一次 simhash（对已有 content 计算指纹）：
--   php app/cj/bin/backfill_main.php --dry-run
--   php app/cj/bin/backfill_main.php
-- phone_norm 主库已有且为 NOT NULL，无需回填。
-- 主站新发布招聘应在写入时同步生成 simhash（与采集器同一算法：SimHash 类）。

-- ------------------------------------------------------------
-- 改动二：新增来源标记 origin（清理时双保险校验，见文档 §5.6、§8）
-- ------------------------------------------------------------
ALTER TABLE `zhaopin_posts`
    ADD COLUMN `origin` VARCHAR(20) NOT NULL DEFAULT 'user'
        COMMENT '数据来源：user=自有UGC / crawler=冷启动采集导入' AFTER `status`,
    ADD KEY `idx_origin` (`origin`);

-- 采集数据导入主库时置 origin='crawler'；自有用户发布保持默认 'user'。
-- DEFAULT 'user' 使全部存量记录自动标记为 user，无需回填。
-- 清理时以采集库 cj_import_map 为账本精准定位，origin='crawler' 作双保险，
-- 防止误删自有 UGC。

-- ------------------------------------------------------------
-- 不需要在主库做的事：
-- 1. 不新增 contact_key —— 复用已有 phone_norm。
-- 2. 不存指向采集库的任何 id 或外键。采集库↔主库的一一对应关系
--    全部存于采集库 cj_import_map。采集模块下线、采集库删除后，
--    主库不残留任何悬空引用。
-- ------------------------------------------------------------
