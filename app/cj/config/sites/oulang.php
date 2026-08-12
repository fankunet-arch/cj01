<?php
/**
 * 欧浪网 采集配置（新平台 eulam，列表页内联解析，P0 勘察完成）。
 * 站点：https://eulam.infohuaxin.com  招聘求职频道 /category/jobs
 * 编码：UTF-8；nginx，非 Cloudflare，服务器端可正常抓取。
 *
 * 该平台列表页每条 .category-listing-item 已内联全部字段（标题/城市/日期/正文/
 * 联系人/电话/详情链接），采用 list_inline 模式：只抓列表页、逐条入库，
 * 无需再抓详情页（请求更少、更礼貌，一页约 20 条）。
 * 站点改版时只改本文件，不动核心代码。
 *
 * 备注：传统站 infohuaxin.com 挂 Cloudflare，服务器端 403 抓不了，故改用本新平台。
 */

return [
    'site'               => 'oulang',
    'enabled'            => true,
    'mode'               => 'list_inline',
    'list_url'           => 'https://eulam.infohuaxin.com/category/jobs?page=%d',
    'list_item_selector' => '.category-listing-item',           // 每条招聘卡片容器
    'link_selector'      => '.category-detail-link',            // → /info/XXXXX（source_url 唯一键）
    'detail'             => [
        'title'        => '.category-compact-title',            // 岗位标题
        'company'      => null,
        'salary'       => null,
        'desc'         => '.category-detail-desc',              // 正文
        'phone'        => '.category-detail-contact a',         // 联络电话（tel 链接文本，无则空）
        'wechat'       => null,                                 // 微信常写在正文，无独立字段
        'contact_name' => '.category-detail-contact',           // “联络人：X · …”，解析器自动清洗
        'city'         => '.category-compact-city',             // 城市（MADRID 等）
        'district'     => null,
        'date'         => '.category-detail-meta',              // “发布 2026-07-27 · 地区 …”
    ],
    // 列表页的 .category-detail-desc 是源站截断的预览（以「...」结尾），
    // 电话/微信通常写在正文末尾、正好被截掉 → 抓不到联系方式就永远导不进主库。
    // 开启后每条额外抓一次 /info/XXXXX 详情页补全正文与联系方式（不依赖选择器）。
    'enrich_detail'      => true,
    'category'           => '招聘求职',
    'contact_mode'       => 'plain',
    'rate_limit'         => ['min_delay' => 8, 'max_delay' => 20],   // 秒（§6.1，礼貌采集）
    'render'             => 'php',
    'charset'            => null,                               // UTF-8，无需转码
];
