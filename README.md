# zhaopin.es 招聘信息采集器（cj crawler）

zhaopin.es 冷启动期的**独立临时采集模块**：从四个西班牙华人信息门户采集招聘信息，
经清洗与三级去重后作为冷启动填充数据。冷启动完成后整体下线并清理数据（用完即删）。

设计依据：`docs/招聘采集程序_需求与设计文档_v1.2.md`。

## 目录结构（公开 / 私有分离）

```
cj01/
├── cj_html/                 # ← web root，唯一对外暴露目录（DocumentRoot 指向这里）
│   ├── index.php            # 内部概览入口（薄转发）
│   ├── review.php           # 人工复核队列入口
│   ├── dashboard.php        # 采集运行看板入口
│   ├── assets/              # 静态资源
│   └── .htaccess            # Apache 目录保护
├── app/                     # ← web root 之外，网络不可访问（私有）
│   ├── bootstrap.php        # 统一引导（自动加载、配置、时区）
│   ├── config/
│   │   ├── config.example.php   # 配置模板（复制为 config.php，勿提交）
│   │   └── sites/           # 每站一份采集配置（选择器、频率、字段映射）
│   ├── src/
│   │   ├── Fetcher/         # HTTP 抓取（频控、重试、UA、cookie）
│   │   ├── Parser/          # 配置驱动解析器 → 统一数据模型
│   │   ├── Normalizer/      # 电话/微信/文本归一化
│   │   ├── Dedup/           # 三级去重引擎（URL / contact_key / SimHash）
│   │   ├── Repository/      # crawler_db 读写 + zhaopin 主库只读比对
│   │   ├── Scheduler/       # 采集编排、改版告警（Brevo）
│   │   ├── Import/          # 导入主库 + 写 cj_import_map
│   │   └── Purge/           # 一键清理
│   ├── web/                 # 内部页面业务逻辑（入口在 cj_html，逻辑在这里）
│   ├── bin/                 # CLI 入口（cron 调用，不经 Web）
│   └── logs/                # 日志（不可对外）
├── db/                      # 数据库导入文件（MySQL 8.4）
│   ├── 01_crawler_db_schema.sql      # 采集库 crawler_db 全部 cj_ 表
│   ├── 02_zhaopin_main_ddl_patch.sql # 主库 zhaopin_posts 配合改动（新增 simhash/origin）
│   └── 03_sample_data.sql            # 可选：开发联调样例数据
└── docs/                    # 需求与设计文档
```

## 安装部署

### 1. 数据库（MySQL 8.4）

```bash
# 建采集库（含全部 cj_ 表）
mysql -u root -p < db/01_crawler_db_schema.sql

# zhaopin 主库配合改动（在主库执行；给 zhaopin_posts 加 simhash/origin 两列）
# 主库名按实际填（导出示例为 mhdlmskzoi87b0i）
mysql -u root -p 你的主库名 < db/02_zhaopin_main_ddl_patch.sql

# 可选：开发联调样例数据
mysql -u root -p crawler_db < db/03_sample_data.sql
```

> 主库真实招聘表为 `zhaopin_posts`，已自带 `phone_norm`（电话去重）与 `content_hash`
> （精确去重），故补丁只新增 `simhash`（模糊去重）与 `origin`（来源标记）两列。

主库存量数据回填 `simhash`（跑一次；`phone_norm`/`origin` 无需回填）：

```bash
php app/bin/backfill_main.php --dry-run
php app/bin/backfill_main.php
```

### 2. 应用配置

```bash
cp app/config/config.example.php app/config/config.php
# 编辑 config.php：采集库连接、主库比对方式（main.mode）、内部页面口令、Brevo
```

> **导入主库前务必确认 `main.import` 映射值**（采集数据 → `zhaopin_posts` 必填字段）：
> `type`（招聘帖类型枚举）、`poster_type`（发布者类型枚举）、`status`（导入后帖子状态）、
> `default_region_id` / `default_category_id`（城市/分类按名称匹配不到时的兜底外键，
> 必须填主库中真实存在的“其他/未分类”记录 id，不能留 0）。这些值取自主站真实约定，
> 填错会导致导入的帖子归错类目/地区。另外 `content_hash` 默认按 `SHA-256(content)` 生成，
> 若主站入库前对 content 另做归一化，请在 `MainRepository::contentHash()` 对齐其算法。

要求 PHP ≥ 8.1，扩展：curl、pdo_mysql、mbstring、dom。
核心功能零 Composer 依赖即可运行；如需更强的 CSS 选择器支持可
`composer require symfony/css-selector`。

### 3. Web 服务器

DocumentRoot 指向 `cj_html/`（`app/` 在其上一级，Web 天然不可达）。

Nginx 参考：

```nginx
server {
    server_name cj.example.com;
    root /srv/cj01/cj_html;
    index index.php;

    location ~ ^/(index|review|dashboard)\.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    location ~ \.php$ { return 403; }   # 其余 PHP 一律拒绝
}
```

内部页面已内置 Basic Auth（config.php → web），建议再加 IP 白名单。

### 4. cron 调度（各站错开，避免同时打满带宽）

```cron
10 3 * * *  php /srv/cj01/app/bin/crawl.php --site=oulang
40 4 * * *  php /srv/cj01/app/bin/crawl.php --site=ouhua
10 6 * * *  php /srv/cj01/app/bin/crawl.php --site=huarenjie
40 7 * * *  php /srv/cj01/app/bin/crawl.php --site=xihua
0  2 * * 0  php /srv/cj01/app/bin/purge.php --mode=expired
```

> 站点配置默认 `enabled=false`：P0 站点勘察（确认列表页/详情页选择器、
> 联系方式获取方式、是否 JS 渲染、robots）后回填 `app/config/sites/*.php` 再开启。

## 日常操作

内部页面（`index.php` 概览）提供两个人工操作按钮：

- **一键采集**：后台拉起 `crawl.php --all` 采集全部启用站点。
  **采集间隔硬性 ≥ 1 小时**（`crawl.min_trigger_interval`，配置只能调大不能调小），
  Web 按钮与 cron 共用同一触发闸门（文件锁 + 运行态检查），间隔不足或采集进行中时按钮置灰。
- **导入主库**：采集完成后**不会自动导入** zhaopin 主站数据库——去重通过的记录仅标记
  `import_ready=1`，必须人工点击此按钮（或执行 `import.php`）确认导入。

对应 CLI：

| 操作 | 命令 |
|---|---|
| 采集单站 | `php app/bin/crawl.php --site=oulang` |
| 采集全部启用站 | `php app/bin/crawl.php --all`（间隔 <1h 会被闸门拒绝；`--force` 仅调试用） |
| 存量重跑去重 | `php app/bin/dedup.php` |
| 预览待导入 | `php app/bin/import.php --dry-run` |
| 导入主库（人工把关） | `php app/bin/import.php --limit=100` |
| 精准清理主库导入 | `php app/bin/purge.php --mode=main [--batch=…] [--dry-run]` |
| 到期清理采集库 | `php app/bin/purge.php --mode=expired` |
| 冷启动结束一键下线 | 先 `--mode=main` 清完主库 → `--mode=all` 清空采集库 → 摘 cron |

清理顺序约束（文档 §8）：**必须先据 `cj_import_map` 处理完主库，再删采集库**，
否则账本先没了，主库的采集数据只能靠 `origin='crawler'` 粗粒度清理。

## 合规边界（务必遵守）

- 尊重 robots.txt 与登录墙：登录墙内容**不采集、不绕过**，联系方式置空走降级去重。
- 频率控制：每站请求间隔 8–20 秒随机化，单次采集页数有上限，错峰调度。
- 最小化采集，全部记录带 `purge_after` 预期清理日期，“用完即删”可执行、可审计。
