<?php
declare(strict_types=1);

// 列表/筛选/搜索：与首页共用信息墙（?type=seek 求职板块，?region= ?cat= ?q= 筛选）
require __DIR__ . '/_wall_common.php';
zp_wall_render();
