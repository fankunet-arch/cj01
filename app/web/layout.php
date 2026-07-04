<?php
/**
 * 内部页面公共布局。调用方定义 $pageTitle 与 $renderBody（闭包）后 include 本文件。
 */

function cj_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= cj_e($pageTitle ?? '采集器') ?> · cj crawler</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header>
    <h1>zhaopin.es 采集器（冷启动临时模块）</h1>
    <nav>
        <a href="/dashboard.php">运行看板</a>
        <a href="/review.php">复核队列</a>
    </nav>
</header>
<main>
<?php ($renderBody)(); ?>
</main>
</body>
</html>
