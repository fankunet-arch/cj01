<?php
/**
 * 人工复核队列页面入口（内部访问：Basic Auth + 可选 IP 白名单）。
 * 业务逻辑在 web root 之外的 app/cj/web/review.php。
 */
require __DIR__ . '/../../app/cj/bootstrap.php';

\Cj\Support\WebAuth::guard();

require CJ_APP_ROOT . '/web/review.php';
