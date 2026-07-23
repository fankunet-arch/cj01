<?php /** @var array $user @var array $post @var string $err @var string $csrf
        @var array $categories @var array $topRegions @var array $cities @var int $curTop */ ?>
  <nav class="nav">
    <a class="back" href="/user/my_posts"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>我的发布</a>
    <a class="brand" href="/"><span class="zh">西华<span class="hl">招聘</span></span></a>
    <span class="spacer"></span>
    <span class="u-name"><?= zp_e($user['name']) ?></span>
  </nav>

  <div class="shell u-shell">
    <h1 class="u-h1">编辑信息</h1>
    <?php if ($err !== ''): ?><p class="u-err"><?= zp_e($err) ?></p><?php endif; ?>

    <form class="u-panel" method="post" action="/user/edit?id=<?= zp_e($post['public_code']) ?>">
      <input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>">
      <input type="hidden" name="id" value="<?= zp_e($post['public_code']) ?>">

      <div class="field">
        <label class="lab">职位类别 <span class="req">*</span></label>
        <span class="selwrap">
          <select class="sel" name="category_id">
            <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $post['category_id'] ? 'selected' : '' ?>><?= zp_e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </span>
      </div>

      <div class="field">
        <label class="lab">信息内容 <span class="req">*</span></label>
        <textarea class="area big" name="content" maxlength="1000"><?= zp_e($post['content']) ?></textarea>
      </div>

      <div class="row">
        <div class="field">
          <label class="lab">所在大区 <span class="req">*</span></label>
          <span class="selwrap">
            <select class="sel" id="fRegionTop">
              <?php foreach ($topRegions as $r): ?>
              <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === $curTop ? 'selected' : '' ?>><?= zp_e($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </span>
        </div>
        <div class="field">
          <label class="lab">城市 <span class="req">*</span></label>
          <span class="selwrap"><select class="sel" name="region_id" id="fRegion" data-current="<?= (int) $post['region_id'] ?>"></select></span>
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label class="lab">联系人称呼 <span class="req">*</span></label>
          <input class="input" name="contact_name" maxlength="50" value="<?= zp_e($post['contact_name']) ?>">
        </div>
        <div class="field">
          <label class="lab">联系电话 <span class="req">*</span></label>
          <input class="input" name="phone" inputmode="tel" maxlength="20" value="<?= zp_e($post['phone']) ?>">
        </div>
      </div>

      <div class="field">
        <label class="lab">微信 <span class="opt">选填</span></label>
        <input class="input" name="wechat" maxlength="60" value="<?= zp_e($post['wechat'] ?? '') ?>">
      </div>

      <button class="u-submit" type="submit">保存修改</button>
    </form>
  </div>

  <script>window.ZP_CITIES = <?= json_encode($cities, JSON_UNESCAPED_UNICODE) ?>;</script>
