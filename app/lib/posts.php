<?php
declare(strict_types=1);

/** 帖子仓储与核心机制（到期下架 / 保底 N / 置顶回落）。 */

/** 惰性维护：置顶到期回落 + 超过有效期的在线帖下架（数据保留）。 */
function zp_posts_housekeeping(): void
{
    $db = zp_db();
    $now = zp_now();
    $db->prepare('UPDATE ' . zp_table('posts') . ' SET is_top = 0 WHERE is_top = 1 AND (top_expire_at IS NULL OR top_expire_at < ?)')
       ->execute([$now]);
    $expireDays = zp_setting_int('post_expire_days', 30);
    $cutoff = gmdate('Y-m-d H:i:s', time() - $expireDays * 86400);
    $db->prepare('UPDATE ' . zp_table('posts') . ' SET status = 0 WHERE status = 1 AND created_at < ?')
       ->execute([$cutoff]);
}

/**
 * 信息墙查询：置顶 > 顶帖时间倒序；在线不足保底 N 时把最近下架的捞回补满。
 * $filters: region_id(大区含子市) / category_id / q
 */
function zp_posts_feed(int $type, array $filters = [], int $limit = 60): array
{
    $db = zp_db();
    $where = ['p.type = :type'];
    $args = ['type' => $type];
    if (!empty($filters['category_id'])) {
        $where[] = 'p.category_id = :cat';
        $args['cat'] = (int) $filters['category_id'];
    }
    if (!empty($filters['region_id'])) {
        $where[] = '(p.region_id = :reg OR r.parent_id = :reg2)';
        $args['reg'] = (int) $filters['region_id'];
        $args['reg2'] = (int) $filters['region_id'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(p.content LIKE :q OR p.contact_name LIKE :q2)';
        $args['q'] = '%' . $filters['q'] . '%';
        $args['q2'] = '%' . $filters['q'] . '%';
    }
    $base = ' FROM ' . zp_table('posts') . ' p'
          . ' JOIN ' . zp_table('regions') . ' r ON r.id = p.region_id'
          . ' JOIN ' . zp_table('categories') . ' c ON c.id = p.category_id'
          . ' WHERE ' . implode(' AND ', $where);
    $cols = 'p.public_code, p.type, p.content, p.contact_name, p.phone, p.wechat, p.is_top,'
          . ' p.phone_views, p.wechat_views, p.status, p.created_at, p.bumped_at,'
          . ' r.name AS region_name, c.name AS category_name';

    $stmt = $db->prepare("SELECT $cols $base AND p.status = 1 ORDER BY p.is_top DESC, p.bumped_at DESC LIMIT $limit");
    $stmt->execute($args);
    $rows = $stmt->fetchAll();

    // 保底最新 N 条（招聘/求职各算）：在线不足 N 时用最近下架的补满
    $n = zp_setting_int($type === 1 ? 'backfill_n_recruit' : 'backfill_n_seek', 30);
    if (count($rows) < $n && empty($filters['category_id']) && empty($filters['region_id']) && empty($filters['q'])) {
        $need = min($n, $limit) - count($rows);
        if ($need > 0) {
            $stmt = $db->prepare("SELECT $cols $base AND p.status = 0 ORDER BY p.bumped_at DESC LIMIT $need");
            $stmt->execute($args);
            $rows = array_merge($rows, $stmt->fetchAll());
        }
    }
    return $rows;
}

/** 按对外随机码取在线帖（含下架补显期）。 */
function zp_post_by_code(string $code): ?array
{
    $stmt = zp_db()->prepare(
        'SELECT p.*, r.name AS region_name, r.parent_id AS region_parent, c.name AS category_name'
        . ' FROM ' . zp_table('posts') . ' p'
        . ' JOIN ' . zp_table('regions') . ' r ON r.id = p.region_id'
        . ' JOIN ' . zp_table('categories') . ' c ON c.id = p.category_id'
        . ' WHERE p.public_code = ? AND p.status IN (0, 1, 2)'
    );
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** 内容摘要（信息墙卡片标题）。 */
function zp_post_excerpt(string $content, int $len = 40): string
{
    $line = trim(strtok($content, "\n") ?: '');
    return mb_strlen($line) > $len ? mb_substr($line, 0, $len) . '…' : $line;
}
