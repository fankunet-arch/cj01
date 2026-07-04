<?php
/**
 * 华人街 采集配置（占位框架）。
 * 备注：部分需注册——登录墙内容不采（文档 §2.2）。
 * P0 站点勘察后按真实页面结构回填选择器。
 */

return [
    'site'          => 'huarenjie',
    'enabled'       => false,
    'list_url'      => 'https://example-huarenjie.test/zhaopin?page=%d',   // 待确认
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
    'contact_mode'  => 'click',   // 需点击展开且可通过正常请求获取则获取（§2.2 降级策略）
    'rate_limit'    => ['min_delay' => 8, 'max_delay' => 20],
    'render'        => 'php',
    'charset'       => null,
];
