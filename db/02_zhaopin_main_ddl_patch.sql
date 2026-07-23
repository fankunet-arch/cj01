-- ============================================================
-- zhaopin.es 主库配合改动清单（DDL 补丁）
-- 依据：《招聘采集程序_需求与设计文档_v1.2》 §11，并对齐真实主库结构
-- 目标环境：MySQL 8.4
--
-- 真实招聘表为 `zhaopin_posts`（不是文档假设的 `jobs`）。
-- 主库已自带 `phone_norm`（并入 idx_dedup）与 `content_hash`（精确去重），
-- 因此三级去重的“电话键”直接复用 phone_norm，无需再加 contact_key。
-- 本补丁只新增采集器缺失的两列：simhash（模糊去重）、origin（来源标记）。
--
-- 在 zhaopin 主库执行。若表名/库名与此不同，按实际调整。
-- ============================================================

-- USE `你的主库名`;   -- ← 例如导出中的 mhdlmskzoi87b0i，按实际取消注释

-- ------------------------------------------------------------
-- 改动一：新增内容指纹列 simhash（三级去重的模糊比对，见文档 §3、§4.4）
-- 主库已有 phone_norm，故不再新增 contact_key —— 电话去重直接用 phone_norm。
-- ------------------------------------------------------------
ALTER TABLE `zhaopin_posts`
    ADD COLUMN `simhash` BIGINT UNSIGNED NULL
        COMMENT '内容指纹 SimHash(64bit)，采集器三级去重用' AFTER `content_hash`,
    ADD KEY `idx_simhash` (`simhash`);

-- 存量数据需回填一次 simhash（对已有 content 计算指纹）：
--   php app/bin/backfill_main.php --dry-run
--   php app/bin/backfill_main.php
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
