<?php
declare(strict_types=1);

// 退出管理后台
zp_session_start();
$_SESSION = [];
session_destroy();
header('Location: /c/cp/login');
exit;
