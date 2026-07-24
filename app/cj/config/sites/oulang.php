<?php
/**
 * 欧浪网 采集配置（已按真实页面结构回填，P0 勘察完成）。
 * 站点：https://infohuaxin.com  招聘求职频道 class1=13
 * 编码：gb2312（Fetcher 按 GBK 解码转 UTF-8）；静态 HTML，无需 JS 渲染。
 *
 * 列表页每条 .inflist5_list 里，真正的详情链接是“查看”那个 <a class="inf_a">
 * （标题链接是 href="###" 的 JS 展开，不可用）。详情页 showinfo.asp?id=XXX。
 * 站点改版时只改本文件，不动核心代码。
 */

return [
    'site'          => 'oulang',
    'enabled'       => true,
    'list_url'      => 'https://infohuaxin.com/showclass.asp?class1=13&page=%d',
    'list_selector' => '.inflist5_list a.inf_a',   // “查看”→ showinfo.asp?id=XXX
    'detail'        => [
        'title'        => 'title',              // 详情页 <title> = 岗位标题（干净）
        'company'      => null,                 // 该站无独立店名字段
        'salary'       => null,                 // 无独立薪资字段（并入正文）
        'desc'         => '.inftext_box p',      // 正文段落
        'phone'        => '.inftel',             // 电话，形如 0034-611048491
        'wechat'       => null,                  // 无独立微信字段（常写在正文）
        'contact_name' => '.inftextline p',      // 第一个 .inftextline 的 <p> = 联系人
        'city'         => '.inftext_address',     // “地区：VALENCIA”（含前缀，后续可清洗）
        'district'     => null,
        'date'         => null,                  // 详情页只有“今天/到期时间”，易误取到期日，暂不采
    ],
    'category'      => '招聘求职',
    'contact_mode'  => 'plain',                 // 联系方式明文
    'rate_limit'    => ['min_delay' => 8, 'max_delay' => 20],   // 秒（§6.1，礼貌采集）
    'render'        => 'php',
    'charset'       => 'GBK',                   // gb2312 的超集，兼容页面里的扩展字
];
