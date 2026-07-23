<?php
declare(strict_types=1);

// 退出登录（普通用户）
zp_session_start();
unset($_SESSION['user']);
header('Location: /');
exit;
