<?php
/** 信息墙卡片局部。@var array $post（zp_posts_feed 行） */
$views = (int) $post['phone_views'] + (int) $post['wechat_views'];
$isToday = zp_day_bucket($post['bumped_at']) === '今日';
$isSeekPost = (int) $post['type'] === 2;
?>
      <div class="card<?= (int) $post['status'] !== 1 ? ' faded' : '' ?>" data-href="/detail?id=<?= zp_e($post['public_code']) ?>">
        <div class="ctop">
          <span class="tag"><?= $isSeekPost ? '求职 · ' : '' ?><?= zp_e($post['category_name']) ?></span>
          <?php if ((int) $post['is_top'] === 1): ?><span class="new">置顶</span>
          <?php elseif ($isToday): ?><span class="new">今日新</span><?php endif; ?>
          <span class="ago"><?= zp_e(zp_time_ago($post['bumped_at'])) ?></span>
        </div>
        <div class="title"><?= zp_e(zp_post_excerpt($post['content'])) ?></div>
        <div class="meta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><?= zp_e($post['region_name']) ?> · <?= zp_e($post['contact_name']) ?></div>
        <div class="foot">
          <div class="pay"><?= $views > 0 ? $views . ' <small>次查看</small>' : '<small>新发布</small>' ?></div>
          <div class="cact">
            <button class="call" data-code="<?= zp_e($post['public_code']) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>拨打 <span class="num"><?= zp_e(zp_phone_mask($post['phone'])) ?></span></button>
            <?php if (($post['wechat'] ?? null) !== null): ?>
            <button class="wx" title="查看微信" data-code="<?= zp_e($post['public_code']) ?>"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 4C5 4 2 6.6 2 9.9c0 1.8 1 3.4 2.6 4.5L4 17l2.7-1.4c.7.2 1.5.3 2.3.3h.6a4.6 4.6 0 0 1-.2-1.3c0-3 2.9-5.3 6.4-5.3h.6C15.8 6 12.7 4 9 4z"/></svg></button>
            <?php endif; ?>
          </div>
        </div>
      </div>
