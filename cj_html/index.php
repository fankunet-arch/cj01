<?php
/**
 * 极简内部入口：仅引导 + 转发到 app 层，不写业务代码（文档 §4.2 要点）。
 */
require __DIR__ . '/../app/bootstrap.php';

\Cj\Support\WebAuth::guard();

require CJ_APP_ROOT . '/web/home.php';
