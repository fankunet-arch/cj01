<?php
/**
 * 信息详情：真实数据渲染。联系方式不出明文，点击经 get_contact.php 取号。
 * @var array $post  @var array $related  @var int $daysLeft
 */
$isSeekPost = (int) $post['type'] === 2;
$offline = (int) $post['status'] !== 1;
$publishedAt = zp_madrid($post['created_at'])->format('n月j日 H:i');
$phoneMask = zp_phone_mask($post['phone']);
$hasWechat = ($post['wechat'] ?? null) !== null;
$code = $post['public_code'];
?>
  <nav class="nav">
    <a class="back" href="/?type=<?= $isSeekPost ? 'seek' : 'job' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>返回列表</a>
    <a class="brand" href="/"><span class="zh">西华<span class="hl">招聘</span></span></a>
    <span class="spacer"></span>
    <?php require __DIR__ . '/_navuser.php'; ?>
    <span class="dom">zhaopin.es</span>
  </nav>

  <div class="shell">
    <div class="cols">

      <!-- ===== 主栏 ===== -->
      <div class="main">
        <div class="ltop">
          <span class="tag"><?= $isSeekPost ? '求职 · ' : '' ?><?= zp_e($post['category_name']) ?></span>
          <?php if ((int) $post['is_top'] === 1): ?><span class="new">置顶</span>
          <?php elseif (zp_day_bucket($post['created_at']) === '今日'): ?><span class="new">今日新</span><?php endif; ?>
          <?php if ($offline): ?><span class="offline-tag">已下架</span><?php endif; ?>
        </div>
        <h1><?= zp_e(zp_post_excerpt($post['content'], 60)) ?></h1>
        <div class="metaline">
          <span class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= zp_e($publishedAt) ?> 发布</span>
          <span class="mi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>电话 <?= (int) $post['phone_views'] ?> 次 · 微信 <?= (int) $post['wechat_views'] ?> 次查看</span>
          <?php if (!$offline): ?>
          <span class="mi exp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/><circle cx="12" cy="12" r="5"/></svg><?= (int) $daysLeft ?> 天后过期</span>
          <?php endif; ?>
          <button class="report" id="reportBtn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 21V4h13l-2 4 2 4H4"/></svg>举报</button>
        </div>

        <div class="facts">
          <div class="fact"><div class="l"><?= $isSeekPost ? '现居地区' : '工作地区' ?></div><div class="v"><?= zp_e($post['region_name']) ?></div></div>
          <div class="fact"><div class="l">类别</div><div class="v"><?= zp_e($post['category_name']) ?></div></div>
          <div class="fact"><div class="l">联系人</div><div class="v"><?= zp_e($post['contact_name']) ?></div></div>
          <div class="fact"><div class="l">信息类型</div><div class="v"><?= $isSeekPost ? '求职' : '招聘' ?><?= (int) $post['poster_type'] === 2 ? ' · 注册用户' : '' ?></div></div>
        </div>

        <div class="sec">
          <h2><?= $isSeekPost ? '求职说明' : '职位描述' ?></h2>
          <div class="desc">
            <?php foreach (preg_split('/\n+/', $post['content']) ?: [] as $para): if (trim($para) === '') continue; ?>
            <p><?= zp_e(trim($para)) ?></p>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="sec">
          <div class="safety">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M12 8v4M12 16h.01"/></svg>
            <div class="t"><b>谨防诈骗：</b>正规招工不会收取押金、报名费、培训费。请勿在见面前向对方转账或缴纳任何费用。</div>
          </div>
        </div>

        <?php if ($related !== []): ?>
        <div class="sec">
          <h2>相似信息 · 同在 <?= zp_e(strtok($post['region_name'], ' ')) ?>一带</h2>
          <div class="rel">
            <?php foreach ($related as $r): ?>
            <a class="relitem" href="/detail?id=<?= zp_e($r['public_code']) ?>">
              <div class="rt"><div class="rti"><?= zp_e(zp_post_excerpt($r['content'])) ?></div><div class="rtm"><?= zp_e($r['contact_name']) ?> · <?= zp_e($r['region_name']) ?> · <?= zp_e(zp_time_ago($r['bumped_at'])) ?></div></div>
              <div class="rp"><?= zp_e($r['category_name']) ?></div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- ===== 侧栏联系卡 ===== -->
      <aside class="side">
        <div class="contact">
          <div class="store"><?= zp_e($post['contact_name']) ?></div>
          <div class="sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><?= zp_e($post['region_name']) ?></div>

          <button class="callbtn js-call" data-code="<?= zp_e($code) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
            拨打 <span class="num"><?= zp_e($phoneMask) ?></span>
          </button>

          <?php if ($hasWechat): ?>
          <div class="wxrow">
            <span class="wxi"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 4C5 4 2 6.6 2 9.9c0 1.8 1 3.4 2.6 4.5L4 17l2.7-1.4c.7.2 1.5.3 2.3.3h.6a4.6 4.6 0 0 1-.2-1.3c0-3 2.9-5.3 6.4-5.3h.6C15.8 6 12.7 4 9 4z"/></svg></span>
            <span class="wxv"><span class="lab">微信</span><span class="js-wx-value">·····</span></span>
            <button class="copybtn js-wx" data-code="<?= zp_e($code) ?>">点击查看</button>
          </div>
          <?php endif; ?>

          <div class="cmeta">
            <div class="cmi"><span>发布时间</span><b><?= zp_e($publishedAt) ?></b></div>
            <div class="cmi"><span>电话查看</span><b><?= (int) $post['phone_views'] ?> 次</b></div>
            <div class="cmi"><span>微信查看</span><b><?= (int) $post['wechat_views'] ?> 次</b></div>
            <?php if (!$offline): ?><div class="cmi"><span>有效期</span><span class="exp"><?= (int) $daysLeft ?> 天后过期</span></div><?php endif; ?>
          </div>

          <button class="invalidbtn" id="invalidBtn" data-code="<?= zp_e($code) ?>">电话打不通 / 已失效？点这里反馈</button>
        </div>
      </aside>

    </div>
  </div>

  <!-- 移动端底部联系栏 -->
  <div class="m-contact">
    <button class="callbtn js-call" data-code="<?= zp_e($code) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
      拨打 <span class="num"><?= zp_e($phoneMask) ?></span>
    </button>
    <?php if ($hasWechat): ?>
    <button class="mwx js-wx" data-code="<?= zp_e($code) ?>"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 4C5 4 2 6.6 2 9.9c0 1.8 1 3.4 2.6 4.5L4 17l2.7-1.4c.7.2 1.5.3 2.3.3h.6a4.6 4.6 0 0 1-.2-1.3c0-3 2.9-5.3 6.4-5.3h.6C15.8 6 12.7 4 9 4z"/></svg></button>
    <?php endif; ?>
  </div>

  <!-- 举报弹层 -->
  <div class="overlay" id="overlay">
    <div class="modal">
      <h3>举报这条信息</h3>
      <p class="ms">帮我们让列表保持真实、干净</p>
      <div class="reasons" id="reasons">
        <label class="reason"><input type="radio" name="r" value="虚假或诈骗信息">虚假或诈骗信息</label>
        <label class="reason"><input type="radio" name="r" value="职位已招满/已过期">职位已招满 / 已过期</label>
        <label class="reason"><input type="radio" name="r" value="重复发布">重复发布</label>
        <label class="reason"><input type="radio" name="r" value="内容不当">内容不当</label>
        <label class="reason"><input type="radio" name="r" value="其他">其他</label>
      </div>
      <div class="mbtns">
        <button class="mcancel" id="mCancel">取消</button>
        <button class="mok" id="mOk" data-code="<?= zp_e($code) ?>">提交举报</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg><span id="toastMsg"></span></div>
