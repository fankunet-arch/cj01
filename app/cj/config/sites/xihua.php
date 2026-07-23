<?php
/**
 * 西华网 采集配置（占位框架）。
 * P0 站点勘察后按真实页面结构回填选择器（联系方式获取方式待确认）。
 */

return [
    'site'          => 'xihua',
    'enabled'       => false,
    'list_url'      => 'https://example-xihua.test/job/list?page=%d',   // 待确认
    'list_selector' => '.job-item a.title',
    'detail'        => [
        'title'   => '.job-title',
        'company' => '.company',
        'salary'  => '.salary',
        'desc'    => '.job-desc',
        'phone'   => '.contact-phone',
        'wechat'  => '.contact-wechat',
        'city'    => '.location',
        'date'    => '.publish-date',
    ],
    'category'      => '待分类',
    'contact_mode'  => 'plain',
    'rate_limit'    => ['min_delay' => 8, 'max_delay' => 20],
    'render'        => 'php',
    'charset'       => null,
];
