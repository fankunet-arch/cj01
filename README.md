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
│   ├── .htaccess            # 主站路由：无扩展名 URL → 同名 .php（缺它站内链接全 404）
│   └── cj/                  # 采集器唯一对外暴露目录，访问 http://zhaopin.es/cj/
│       ├── index.php        # 内部概览入口（薄转发到 ../../app/cj/bootstrap.php）
│       ├── review.php       # 人工复核队列入口
│       ├── dashboard.php    # 采集运行看板入口
│       ├── assets/          # 静态资源（/cj/assets/…）
│       └── .htaccess        # Apache 目录保护
├── app/                     # ← zhaopin 主站私有目录（web root 之外，网络不可访问）
│   ├── config/
│   │   └── config.example.php # 主站配置模板（复制为 config.php，缺它全站 500）
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
├── db/                      # 数据库导入文件（MySQL 8.0/8.4 与 MariaDB 10.2+ 通用）
│   ├── 00_zhaopin_main_schema.sql    # 主库全部 zhaopin_ 表（全新搭建用，已内建 simhash/origin）
│   ├── 01_crawler_db_schema.sql      # 采集库 crawler_db 全部 cj_ 表
│   ├── 02_zhaopin_main_ddl_patch.sql # 主库已存在时的增量补丁（只加 simhash/origin 两列）
│   ├── 03_sample_data.sql            # 可选：开发联调样例数据
│   └── 04_zhaopin_seed_data.sql      # 主库基础数据（参数/地区/类别/敏感词/管理员，全新搭建必需）
└── docs/                    # 需求与设计文档
```

## 安装部署

### 0. 全新搭建速查表

从零搭一套（服务器上没有任何老数据）需要且只需要下面这些，缺一项就有对应故障：

| 步骤 | 要做的事 | 漏了会怎样 |
|---|---|---|
| 1 | 选中目标库，导入 `db/00_zhaopin_main_schema.sql` | 主库没有表，全站报错 |
| 2 | 导入 `db/04_zhaopin_seed_data.sql`（**先改管理员邮箱**） | 发布页下拉为空、后台参数页空白、无人能登录后台 |
| 3 | 导入 `db/01_crawler_db_schema.sql`（6 张 `cj_` 表，可与主站同库） | 采集器所有页面报错 |
| 4 | `app/config/config.example.php` → `config.php` 并填写 | 全站 HTTP 500 |
| 5 | `app/cj/config/config.example.php` → `config.php` 并填写 | 采集器页面报错 |
| 6 | Apache 保证 `mod_rewrite` + `AllowOverride All`；Nginx 按 §3 配 `try_files` | 站内每个链接都 404 |
| 7 | `app/cj/logs/` 可写（属主给 PHP 运行账号） | 采集无法写日志 |

不要导入 `db/02`（那是给已有主库用的增量补丁，全新搭建导了会报
`Duplicate column name 'simhash'`）；`db/03` 是本机联调用的假数据，生产别导。
SQL 文件都不含建库语句，共享主机没有建库权也能导。

### 1. 数据库（MySQL 8.0/8.4 与 MariaDB 10.2+ 通用）

所有 SQL 文件均为跨库通用，两种数据库导入命令相同、文件无需改动，可无缝切换。
统一采用 `utf8mb4_unicode_520_ci` + `ROW_FORMAT=DYNAMIC`（理由见各文件头部说明）。

**所有 SQL 文件都不建库、不选库**，只建表/写数据，导进「你当前选中的那个库」。
共享主机（OVH / cPanel 等）的库账号没有 `CREATE DATABASE` 权限，
文件里带建库语句会直接报 `#1044 - Access denied ... to database 'xxx'`。
建库这一步请在主机控制面板里做，或直接用主机分配好的那个库。

**采集表放哪个库？** 6 张表全是 `cj_` 前缀，和主站的 `zhaopin_` 表不会撞名，
两种放法代码都支持（没有任何跨库 JOIN），按主机条件选：

- **A：与主站同库**（共享主机只给一个库时用这个）——`crawler_db.name` 填主站库名。
- **B：独立一个库**（能自己建库时用这个）——冷启动结束后整库删掉更干净。

全新搭建按顺序导入三个文件：

```bash
# ① 主库建表（已内建采集器所需的 simhash/origin 两列）
mysql -u 用户名 -p 你的库名 < db/00_zhaopin_main_schema.sql

# ② 主库基础数据 ★必需★（导入前先改文件末尾的管理员邮箱）
mysql -u 用户名 -p 你的库名 < db/04_zhaopin_seed_data.sql

# ③ 采集表（6 张 cj_ 表；放主站同库就填主站库名，独立库就填那个库名）
mysql -u 用户名 -p 你的库名 < db/01_crawler_db_schema.sql

# 可选：开发联调样例数据（生产别导）
mysql -u 用户名 -p 你的库名 < db/03_sample_data.sql
```

MariaDB 把 `mysql` 换成 `mariadb`，文件本身无需任何改动。
用 phpMyAdmin 的话：**先在左侧点中目标库**，再「导入」上传文件，
顺序同上（不点中库直接导入会报 `No database selected`）。

> **不要导入 `db/02`。** 它是给「主库早就存在、有数据、只想增量加两列」用的补丁。
> 全新搭建走的是 `00`，两列已经内建在建表语句里，再导 `02` 必然报
> `#1060 - Duplicate column name 'simhash'`。看到这个报错说明列早就有了，
> 是重复操作而非故障，忽略即可，数据库不会被改坏。
> 拿不准就先查：`SHOW COLUMNS FROM zhaopin_posts LIKE 'simhash';` 有输出就别导。
>
> **② 不可跳过**，`00` 只建空表结构。缺基础数据会出现三个致命现象：
> 发布页地区/类别下拉为空（发不出任何信息）、后台「参数配置」页一片空白
> （该页只 UPDATE 已存在的键，表空就什么都改不了）、后台无人能登录
> （管理员是「Google 登录 + 邮箱白名单」制，`zhaopin_admins` 空 = 全部拒绝）。
>
> **导入 `04` 前必须改一处**：文件末尾的 `you@example.com` 改成你自己用来登录
> Google 的真实邮箱，`role` 保持 `2`（超级管理员；`1` 是普通管理员，进不了
> 「参数配置」「管理员」「置顶券」三页，也就没法再添加别的管理员）。
> 文件可重复导入，不会覆盖你后来改过的值。
>
> `04` 里的城市名用 `MADRID` / `BARCELONA` 这类大写西语写法，是刻意为之：
> 采集器导入时按城市名在 `zhaopin_regions` 里查 `region_id`，目标站正是这种写法，
> 改成中文名会导致采集数据全部落进兜底的「其他地区」。
>
> **主库已存在且有数据**时才不要用 `00`，改用增量补丁只加两列：
> `mysql -u 用户名 -p 你的库名 < db/02_zhaopin_main_ddl_patch.sql`，
> 基础数据也按需自行取舍。
>
> 主库招聘表 `zhaopin_posts` 自带 `phone_norm`（电话去重）与 `content_hash`（精确去重），
> 采集器只额外需要 `simhash`（模糊去重）与 `origin`（来源标记）两列。

主库存量数据回填 `simhash`（跑一次；`phone_norm`/`origin` 无需回填）：

```bash
php app/cj/bin/backfill_main.php --dry-run
php app/cj/bin/backfill_main.php
```

### 2. 应用配置

两份配置都不进版本库（`.gitignore` 已排除），部署时各复制一份填写。

**主站配置（缺它全站 500）：**

```bash
cp app/config/config.example.php app/config/config.php
# 必填：db 段（库名/账号/口令，prefix 保持 zhaopin_）、site.base_url（线上真实域名，不带结尾斜杠）
# 选填：google_oauth（后台与用户登录用，留空则登录页提示"登录服务尚未配置"，其余页面正常）
#       brevo（举报通知邮件，留空则不发信）
# ★ dev.fake_admin_email / dev.fake_user_name 线上必须留空 ★
#   非空时登录页会出现"本地调试直登"按钮，可绕过 Google 直接进后台
```

**采集器配置：**

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

**主站用的是无扩展名 URL**（`/publish`、`/list`、`/c/cp/settings`、`/user/login`），
必须有重写规则把它们指到同名 `.php`，否则站内每个链接都是 404。

- **Apache**：`zp_html/.htaccess` 已内置这套规则，只需确保虚拟主机允许
  `AllowOverride All` 且启用了 `mod_rewrite`（绝大多数共享主机默认满足）。
- **Nginx**：不读 `.htaccess`，按下面的 `try_files` 配置。

```nginx
server {
    server_name zhaopin.es;
    root /srv/zhaopin/zp_html;
    index index.php;

    # 主站无扩展名路由：/publish → /publish.php
    location / {
        try_files $uri $uri/ $uri.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 采集器：只放行内部入口，其余 /cj/ 下的 .php 一律拒绝
    location ~ ^/cj/(index|review|dashboard|sources)\.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # 透传 Basic Auth 头，否则内部页面会”输对密码仍反复弹框”
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }
    location ~ ^/cj/.*\.php$ { return 403; }

    location ~ /\.  { deny all; }   # 隐藏文件
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
