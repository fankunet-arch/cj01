<?php /** @var array $user @var array $rows @var array $coupons @var string $notice @var string $csrf */ ?>
  <nav class="nav">
    <a class="brand" href="/"><span class="zh">西华<span class="hl">招聘</span></span></a>
    <span class="u-badge">我的发布</span>
    <span class="spacer"></span>
    <span class="u-name"><?= zp_e($user['name']) ?></span>
    <a class="u-out" href="/user/logout">退出</a>
  </nav>

  <div class="shell u-shell">
    <?php if ($notice !== ''): ?><p class="u-notice">✓ <?= zp_e($notice) ?></p><?php endif; ?>

    <div class="u-top">
      <h1>我的发布</h1>
      <a class="u-btn primary" href="/publish">+ 发新信息</a>
    </div>

    <!-- 置顶券：兑换 + 余量 -->
    <div class="u-coupon">
      <div class="u-coupon-info">
        <b>置顶券</b>
        <?php if ($coupons !== []): ?>
        <span>可用 <?= count($coupons) ?> 张（<?php
            $days = array_map(fn($c) => (int) $c['top_days'] . '天', $coupons);
            echo zp_e(implode('、', array_slice($days, 0, 5)));
        ?>）——在下方帖子上点「用券置顶」</span>
        <?php else: ?>
        <span>没有可用的券。找管理员领兑换码，在右侧输入即可入账</span>
        <?php endif; ?>
      </div>
      <form class="u-coupon-form" method="post" action="/user/redeem">
        <input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>">
        <input class="input" type="text" name="code" placeholder="输入兑换码" maxlength="20" required>
        <button class="u-btn primary" type="submit">兑换</button>
      </form>
    </div>

    <?php if ($rows === []): ?>
    <div class="plainbox">
      <h1>还没有发布过信息</h1>
      <p>登录状态下发布的信息会出现在这里，可以随时编辑、顶帖、下架。</p>
      <p><a class="u-btn primary" href="/publish">去发布第一条 →</a></p>
    </div>
    <?php endif; ?>

    <div class="u-list">
      <?php foreach ($rows as $r): $online = (int) $r['status'] === 1; ?>
      <div class="u-item <?= $online ? '' : 'off' ?>">
        <div class="u-itop">
          <span class="tag"><?= (int) $r['type'] === 2 ? '求职 · ' : '' ?><?= zp_e($r['category_name']) ?></span>
          <span class="u-status <?= $online ? 'on' : '' ?>"><?= [1 => '在线', 0 => '已到期', 2 => '已下架'][(int) $r['status']] ?? '' ?></span>
          <span class="ago"><?= zp_e(zp_time_ago($r['bumped_at'])) ?></span>
        </div>
        <a class="u-ititle" href="/detail?id=<?= zp_e($r['public_code']) ?>"><?= zp_e(zp_post_excerpt($r['content'])) ?></a>
        <div class="u-imeta"><?= zp_e($r['region_name']) ?> · 电话查看 <?= (int) $r['phone_views'] ?> 次 · 微信查看 <?= (int) $r['wechat_views'] ?> 次</div>
        <div class="u-iact">
          <a class="u-btn sm" href="/user/edit?id=<?= zp_e($r['public_code']) ?>">编辑</a>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="bump"><button class="u-btn sm" type="submit">顶帖</button></form>
          <?php if ($online): ?>
          <?php if ((int) $r['is_top'] === 1): ?>
          <span class="u-topbadge">置顶中 · <?= zp_e(zp_madrid($r['top_expire_at'])->format('n月j日')) ?>到期</span>
          <?php elseif ($coupons !== []): ?>
          <form method="post" action="/user/use_coupon"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="post_id" value="<?= (int) $r['id'] ?>"><button class="u-btn sm gold" type="submit">用券置顶</button></form>
          <?php endif; ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="offline"><button class="u-btn sm" type="submit">已招到/已找到·下架</button></form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('确认删除？删除后无法恢复')"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="u-btn sm danger" type="submit">删除</button></form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
