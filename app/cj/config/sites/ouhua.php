<?php
/**
 * 欧华网 采集配置（占位框架）。
 * 备注：部分板块需注册——登录墙内容不采（文档 §2.2）。
 * P0 站点勘察后按真实页面结构回填选择器。
 */

return [
    'site'          => 'ouhua',
    'enabled'       => false,
    'list_url'      => 'https://example-ouhua.test/jobs?page=%d',   // 待确认
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
    'contact_mode'  => 'login_wall',   // 联系方式在登录墙后：不采集、不绕过，去重降级为文本指纹（§3.4）
    'rate_limit'    => ['min_delay' => 8, 'max_delay' => 20],
    'render'        => 'php',
    'charset'       => null,
];
