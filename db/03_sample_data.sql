-- ============================================================
-- 可选：开发/联调用样例数据（生产环境勿导入）
-- 导入：mysql -u root -p crawler_db < db/03_sample_data.sql
-- ============================================================

USE `crawler_db`;

INSERT INTO `cj_jobs_clean`
    (`source_site`, `source_url`, `title`, `company`, `category`, `city`, `district`,
     `salary_raw`, `description`, `contact_phone`, `contact_wechat`, `contact_name`,
     `phone_norm`, `wechat_norm`, `contact_key`, `simhash`, `publish_date`,
     `collected_at`, `purge_after`, `dedup_status`, `confidence`, `import_ready`)
VALUES
    ('oulang', 'https://example-oulang.test/job/1001', '餐馆招大厨', '中华饭店', '餐饮',
     'Madrid', 'Usera', '1600-1800€', '马德里中餐馆诚招大厨一名，包吃住，有经验者优先。',
     '+34 612 345 678', NULL, '王先生',
     '612345678', NULL, '612345678|', 1234567890123456789, '2026-07-01',
     NOW(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'unique', 'high', 1),
    ('huarenjie', 'https://example-huarenjie.test/post/2002', '百元店招店员', NULL, '百元店',
     'Barcelona', NULL, '面议', '巴塞罗那百元店招店员，要求勤快，会西语优先。',
     NULL, 'tienda_bcn', NULL,
     NULL, 'tienda_bcn', '|tienda_bcn', 987654321987654321, '2026-07-02',
     NOW(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'unique', 'high', 0),
    ('xihua', 'https://example-xihua.test/zhaopin/3003', '仓库工人若干', '进出口公司', '工厂',
     'Madrid', 'Cobo Calleja', '按天结算', '仓库装卸货，日结，地点 Fuenlabrada 工业区。',
     '0034 698 765 432', NULL, NULL,
     '698765432', NULL, '698765432|', 5555555555555555555, '2026-07-03',
     NOW(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'review', 'low', 0);

INSERT INTO `cj_review_queue` (`job_id`, `candidate_id`, `reason`, `resolved`, `created_at`)
VALUES (3, 1, 'simhash 汉明距离=5，联系方式不同，疑似同源改文案', 0, NOW());

INSERT INTO `cj_crawl_runs`
    (`source_site`, `started_at`, `finished_at`, `pages_fetched`, `new_jobs`, `dup_jobs`, `errors`, `status`, `note`)
VALUES
    ('oulang',    NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 110 MINUTE, 12, 8, 3, 0, 'ok', NULL),
    ('huarenjie', NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 50 MINUTE,  6, 2, 4, 1, 'ok', '1 页抓取超时后重试成功'),
    ('xihua',     NOW() - INTERVAL 30 MINUTE, NULL, 3, 1, 0, 0, 'running', NULL);
