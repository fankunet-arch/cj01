<?php
declare(strict_types=1);

/** 信息墙（首页/列表共用）：读筛选参数、查库、组装视图数据并渲染。 */
function zp_wall_render(): void
{
    zp_posts_housekeeping();
    $db = zp_db();

    $mode = ($_GET['type'] ?? '') === 'seek' ? 'seek' : 'hire';
    $type = $mode === 'seek' ? 2 : 1;
    $filters = [
        'region_id'   => (int) ($_GET['region'] ?? 0),
        'category_id' => (int) ($_GET['cat'] ?? 0),
        'q'           => trim((string) ($_GET['q'] ?? '')),
    ];

    // 两类在线数（分段切换上的角标）
    $counts = ['hire' => 0, 'seek' => 0];
    foreach ($db->query('SELECT type, COUNT(*) c FROM ' . zp_table('posts') . ' WHERE status = 1 GROUP BY type') as $r) {
        $counts[(int) $r['type'] === 1 ? 'hire' : 'seek'] = (int) $r['c'];
    }

    // 今日新增（当前板块，马德里时区当天 = UTC 起点换算）
    $todayStartUtc = (new DateTime('today', new DateTimeZone('Europe/Madrid')))
        ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $stmt = $db->prepare('SELECT COUNT(*) FROM ' . zp_table('posts') . ' WHERE type = ? AND status = 1 AND created_at >= ?');
    $stmt->execute([$type, $todayStartUtc]);
    $todayNew = (int) $stmt->fetchColumn();

    // 大区（level=1）+ 当前板块在线数
    $regions = $db->query('SELECT id, name FROM ' . zp_table('regions') . " WHERE level = 1 AND status = 1 ORDER BY sort")->fetchAll();
    $regionCounts = [];
    $stmt = $db->prepare(
        'SELECT IF(r.parent_id = 0, r.id, r.parent_id) top_id, COUNT(*) c FROM ' . zp_table('posts') . ' p'
        . ' JOIN ' . zp_table('regions') . ' r ON r.id = p.region_id'
        . ' WHERE p.status = 1 AND p.type = ? GROUP BY top_id'
    );
    $stmt->execute([$type]);
    foreach ($stmt as $r) {
        $regionCounts[(int) $r['top_id']] = (int) $r['c'];
    }
    $coveredRegions = count($regionCounts);

    // 类别 + 当前板块在线数
    $categories = $db->query('SELECT id, name FROM ' . zp_table('categories') . ' WHERE status = 1 ORDER BY sort')->fetchAll();
    $catCounts = [];
    $stmt = $db->prepare('SELECT category_id, COUNT(*) c FROM ' . zp_table('posts') . ' WHERE status = 1 AND type = ? GROUP BY category_id');
    $stmt->execute([$type]);
    foreach ($stmt as $r) {
        $catCounts[(int) $r['category_id']] = (int) $r['c'];
    }

    // 信息流：置顶 > 时间倒序，按 今日/昨天/更早 分组
    $groups = ['今日' => [], '昨天' => [], '更早' => []];
    foreach (zp_posts_feed($type, $filters) as $post) {
        $groups[zp_day_bucket($post['bumped_at'])][] = $post;
    }

    zp_render('home', [
        'title' => $filters['q'] !== '' ? '搜索：' . $filters['q'] : ($mode === 'seek' ? '求职' : ''),
        'page' => 'home', 'mode' => $mode,
        'type' => $type, 'filters' => $filters, 'counts' => $counts,
        'todayNew' => $todayNew, 'coveredRegions' => $coveredRegions,
        'regions' => $regions, 'regionCounts' => $regionCounts,
        'categories' => $categories, 'catCounts' => $catCounts,
        'groups' => $groups,
    ]);
}
