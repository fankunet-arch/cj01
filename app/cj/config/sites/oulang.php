<?php
/**
 * 欧浪网 采集配置（占位框架）。
 * P0 站点勘察后按真实页面结构回填选择器（文档 §2.2、§4.3、§9）。
 * 站点改版时只改本文件，不动核心代码。
 */

return [
    'site'          => 'oulang',
    'enabled'       => false,   // P0 勘察回填选择器后再开启
    'list_url'      => 'https://infohuaxin.com/showclass.asp?class1=13&page=%d',
    'list_selector' => '.job-item a.title',   // 列表页 → 详情链接（待确认）
    'detail'        => [
        'title'   => '.job-title',
        'company' => '.company',
        'salary'  => '.salary',
        'desc'    => '.job-desc',
        'phone'   => '.contact-phone',   // 明文时
        'wechat'  => '.contact-wechat',
        'city'    => '.location',
        'date'    => '.publish-date',
    ],
    'category'      => '待分类',
    'contact_mode'  => 'plain',            // plain | click | login_wall
    'rate_limit'    => ['min_delay' => 8, 'max_delay' => 20],   // 秒（§6.1）
    'render'        => 'php',              // php | headless（§4.1 JS 渲染例外）
    'charset'       => null,               // 页面编码非 UTF-8 时指定，如 'gbk'
];
