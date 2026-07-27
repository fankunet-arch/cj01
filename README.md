# zhaopin.es 招聘信息采集器（cj crawler）

zhaopin.es 冷启动期的**独立临时采集模块**：从四个西班牙华人信息门户采集招聘信息，
经清洗与三级去重后作为冷启动填充数据。冷启动完成后整体下线并清理数据（用完即删）。

设计依据：`docs/招聘采集程序_需求与设计文档_v1.2.md`。

## 目录结构（公开 / 私有分离，并入 zhaopin 现有部署）

采集器作为 zhaopin.es 的子模块挂载：可见文件在主站 web root 下的 `zp_html/cj/`
（访问地址 `http://zhaopin.es/cj/`），私有逻辑在主站私有目录下的 `app/cj/`。

```
cj01/
├── zp_html/                 # ← zhaopin 主站 web root（DocumentRoot 指向这里）
│   └── cj/                  # 采集器唯一对外暴露目录，访问 http://zhaopin.es/cj/
│       ├── index.php        # 内部概览入口（薄转发到 ../../app/cj/bootstrap.php）
│       ├── review.php       # 人工复核队列入口
│       ├── dashboard.php    # 采集运行看板入口
│       ├── assets/          # 静态资源（/cj/assets/…）
│       └── .htaccess        # Apache 目录保护
├── app/                     # ← zhaopin 主站私有目录（web root 之外，网络不可访问）
│   └── cj/                  # 采集器私有逻辑
│       ├── bootstrap.php    # 统一引导（自动加载、配置、时区）
│       ├── config/
│       │   ├── config.example.php   # 配置模板（复制为 config.php，勿提交）
│       │   └── sites/       # 每站一份采集配置（选择器、频率、字段映射）
│       ├── src/
│       │   ├── Fetcher/     # HTTP 抓取（频控、重试、UA、cookie）
│       │   ├── Parser/      # 配置驱动解析器 → 统一数据模型
│       │   ├── Normalizer/  # 电话/微信/文本归一化
│       │   ├── Dedup/       # 三级去重引擎（URL / contact_key / SimHash）
│       │   ├── Repository/  # crawler_db 读写 + zhaopin 主库比对
│       │   ├── Scheduler/   # 采集编排、一键采集闸门、改版告警（Brevo）
│       │   ├── Import/      # 导入主库 + 写 cj_import_map
│       │   └── Purge/       # 一键清理
│       ├── web/             # 内部页面业务逻辑（入口在 zp_html/cj，逻辑在这里）
│       ├── bin/             # CLI 入口（cron 调用，不经 Web）
│       └── logs/            # 日志（不可对外）
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
php app/cj/bin/backfill_main.php --dry-run
php app/cj/bin/backfill_main.php
```

### 2. 应用配置

```bash
cp app/cj/config/config.example.php app/cj/config/config.php
# 编辑 config.php：采集库连接、主库比对方式（main.mode）、内部页面口令、Brevo
# web.base_path 默认 '/cj'（对应 http://zhaopin.es/cj/），换挂载路径只改这里
```

> **主库连接复用主站配置**：采集器与主站同一部署，`main.db.reuse_zhaopin` 默认 `true`，
> 直接读取主站 `app/config/config.php` 的 `db` 段与 `prefix`（表名由 prefix 派生），
> 无需在采集器里重复填主库账号密码。此时该 DB 账号需具备导入/清理所需的写、删权限。
> 若想用独立只读账号仅做去重比对，把 `reuse_zhaopin` 设为 `false` 并填 `main.db` 内的连接。

> **导入逻辑已与主站发布代码（`app/handlers/publish.php`、`app/lib/util.php`）对齐：**
> `type=1`(招聘)/`poster_type=1`(游客)/`status=1`(在线) 按主站实际取值确定；
> `phone_norm`(=`zp_phone_norm`)、`content_hash`(=`zp_content_hash`：去空白+小写后 SHA-256)、
> `simhash`、`public_code`、UTC 时间戳均由导入代码按主站同一算法自动生成，无需配置。
>
> **仍需你确认 `main.import` 的兜底外键**：`default_region_id` / `default_category_id`
> ——采集的城市/分类名在 `zhaopin_regions` / `zhaopin_categories` 匹配不到时的兜底 id，
> 必须填主库中真实存在的「其他/未分类」记录 id（不能留 0），否则这类帖子会挂到无效外键上。

要求 PHP ≥ 8.1，扩展：curl、pdo_mysql、mbstring、dom。
核心功能零 Composer 依赖即可运行；如需更强的 CSS 选择器支持可
`composer require symfony/css-selector`。

### 3. Web 服务器

采集器并入 zhaopin 主站部署：DocumentRoot 指向主站 web root `zp_html/`，采集器可见文件在
`zp_html/cj/`，访问 `http://zhaopin.es/cj/`。私有目录 `app/` 与 web root 平级，Web 天然不可达。

Nginx 参考（在 zhaopin 主站 server 内为 `/cj/` 增加两个 location）：

```nginx
server {
    server_name zhaopin.es;
    root /srv/zhaopin/zp_html;
    index index.php;

    # 采集器：只放行内部入口，其余 /cj/ 下的 .php 一律拒绝
    location ~ ^/cj/(index|review|dashboard|sources)\.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # 透传 Basic Auth 头，否则内部页面会“输对密码仍反复弹框”
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }
    location ~ ^/cj/.*\.php$ { return 403; }

    # …（zhaopin 主站自身的 location 规则）…
}
```

内部页面已内置 Basic Auth（config.php → web），建议再加 IP 白名单。

### 4. cron 调度（各站错开，避免同时打满带宽）

```cron
10 3 * * *  php /srv/zhaopin/app/cj/bin/crawl.php --site=oulang
40 4 * * *  php /srv/zhaopin/app/cj/bin/crawl.php --site=ouhua
10 6 * * *  php /srv/zhaopin/app/cj/bin/crawl.php --site=huarenjie
40 7 * * *  php /srv/zhaopin/app/cj/bin/crawl.php --site=xihua
0  2 * * 0  php /srv/zhaopin/app/cj/bin/purge.php --mode=expired
```

> 站点配置默认 `enabled=false`：P0 站点勘察（确认列表页/详情页选择器、
> 联系方式获取方式、是否 JS 渲染、robots）后回填 `app/cj/config/sites/*.php` 再开启。

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
| 采集单站 | `php app/cj/bin/crawl.php --site=oulang` |
| 采集全部启用站 | `php app/cj/bin/crawl.php --all`（间隔 <1h 会被闸门拒绝；`--force` 仅调试用） |
| 存量重跑去重 | `php app/cj/bin/dedup.php` |
| 预览待导入 | `php app/cj/bin/import.php --dry-run` |
| 导入主库（人工把关） | `php app/cj/bin/import.php --limit=100` |
| 精准清理主库导入 | `php app/cj/bin/purge.php --mode=main [--batch=…] [--dry-run]` |
| 到期清理采集库 | `php app/cj/bin/purge.php --mode=expired` |
| 冷启动结束一键下线 | 先 `--mode=main` 清完主库 → `--mode=all` 清空采集库 → 摘 cron |

清理顺序约束（文档 §8）：**必须先据 `cj_import_map` 处理完主库，再删采集库**，
否则账本先没了，主库的采集数据只能靠 `origin='crawler'` 粗粒度清理。

## 合规边界（务必遵守）

- 尊重 robots.txt 与登录墙：登录墙内容**不采集、不绕过**，联系方式置空走降级去重。
- 频率控制：每站请求间隔 8–20 秒随机化，单次采集页数有上限，错峰调度。
- 最小化采集，全部记录带 `purge_after` 预期清理日期，“用完即删”可执行、可审计。
