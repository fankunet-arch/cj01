<?php
/**
 * 采集运行看板入口（读 cj_crawl_runs，内部访问）。
 * 业务逻辑在 web root 之外的 app/cj/web/dashboard.php。
 */
require __DIR__ . '/../../app/cj/bootstrap.php';

\Cj\Support\WebAuth::guard();

require CJ_APP_ROOT . '/web/dashboard.php';
