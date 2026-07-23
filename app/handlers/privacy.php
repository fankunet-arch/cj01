<?php
declare(strict_types=1);

// 隐私政策与 Cookie（GDPR，中西双语）。内容源：app/content/privacy.md
// 占位【】项上线前律师审阅补全（docs/04 闸门项）
$md = (string) file_get_contents(ZP_APP_PATH . '/content/privacy.md');
zp_render('privacy', ['title' => '隐私政策与Cookie', 'page' => 'privacy', 'md' => $md]);
