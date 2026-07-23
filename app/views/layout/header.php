<?php
/**
 * @var string|null $title 页面标题
 * @var string|null $page  页面标识 home|detail|publish|plain：决定加载的页面级 css/js 与 body class
 * @var string|null $mode  hire|seek：决定强调色（珊瑚红/玉绿）
 */
$page = $page ?? 'plain';
$mode = ($mode ?? 'hire') === 'seek' ? 'seek' : 'hire';
$zpHtmlDir = dirname(ZP_APP_PATH) . '/zp_html';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= zp_e(isset($title) && $title !== '' ? $title . ' · ' : '') ?><?= zp_e((string) zp_config('site.name', '西华招聘')) ?> · zhaopin.es</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/site.css">
<?php if (is_file($zpHtmlDir . '/assets/css/' . $page . '.css')): ?>
<link rel="stylesheet" href="/assets/css/<?= zp_e($page) ?>.css">
<?php endif; ?>
</head>
<body data-mode="<?= zp_e($mode) ?>" class="page-<?= zp_e($page) ?>">
