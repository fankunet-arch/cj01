<?php
/**
 * 采集源一览入口（内部访问：Basic Auth + 可选 IP 白名单）。
 * 业务逻辑在 web root 之外的 app/cj/web/sources.php。
 */
require __DIR__ . '/../../app/cj/bootstrap.php';

\Cj\Support\WebAuth::guard();

require CJ_APP_ROOT . '/web/sources.php';
