<?php
/**
 * 发布页：真实表单（字段与 zhaopin_posts 对齐 + 字段名混淆 + 验证码 + 蜜罐）。
 * @var array $fm 字段名映射  @var string $captchaQ
 * @var array $categories  @var array $topRegions  @var array $cities [parent_id => [{id,name}]]
 */
?>
  <nav class="nav">
    <a class="back" href="/"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>返回</a>
    <a class="brand" href="/"><span class="zh">西华<span class="hl">招聘</span></span></a>
    <span class="spacer"></span>
    <?php require __DIR__ . '/_navuser.php'; ?>
    <span class="dom">zhaopin.es</span>
  </nav>

  <div class="shell">
    <div class="head">
      <h1>发布信息</h1>
      <p>填几项就行，<b>1 分钟搞定</b> · 免注册 · 帖子 30 天有效</p>
      <div class="seg">
        <button type="button" data-mode="hire" class="on">我要招人</button>
        <button type="button" data-mode="seek">我要找活</button>
      </div>
    </div>

    <div class="cols">

      <!-- ===== 表单 ===== -->
      <div class="formcol">
        <form class="panel" id="pubForm" autocomplete="off">
          <input type="hidden" name="<?= zp_e($fm['type']) ?>" id="fType" value="1">
          <?php /* 蜜罐：机器人可见、真人不可见 */ ?>
          <div class="hp"><label>网站<input type="text" name="website" tabindex="-1"></label></div>

          <div class="field">
            <label class="lab">职位类别 <span class="req">*</span></label>
            <span class="selwrap">
              <select class="sel" name="<?= zp_e($fm['category_id']) ?>" id="fCat">
                <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= zp_e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </span>
            <input class="input newcat" type="text" name="<?= zp_e($fm['new_category']) ?>" maxlength="30" placeholder="没有合适的？输入新类别，管理员审核后生效（选填）">
          </div>

          <div class="field">
            <label class="lab" id="labContent">信息内容 <span class="req">*</span></label>
            <textarea class="area big" name="<?= zp_e($fm['content']) ?>" id="fContent" maxlength="1000" placeholder="如：急招川菜炒锅师傅，有经验优先。做六休一提供食宿，待遇面谈。&#10;&#10;⚠ 电话和微信请填下面的联系方式栏，不要写在这里"></textarea>
          </div>

          <div class="row">
            <div class="field">
              <label class="lab" id="labZona1">所在大区 <span class="req">*</span></label>
              <span class="selwrap">
                <select class="sel" id="fRegionTop">
                  <?php foreach ($topRegions as $r): ?>
                  <option value="<?= (int) $r['id'] ?>"><?= zp_e($r['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </span>
            </div>
            <div class="field">
              <label class="lab">城市 <span class="req">*</span></label>
              <span class="selwrap">
                <select class="sel" name="<?= zp_e($fm['region_id']) ?>" id="fRegion"></select>
              </span>
            </div>
          </div>

          <div class="row">
            <div class="field">
              <label class="lab">联系人称呼 <span class="req">*</span></label>
              <input class="input" name="<?= zp_e($fm['contact_name']) ?>" id="fName" maxlength="50" placeholder="如：李先生 / 老李川菜馆">
            </div>
            <div class="field">
              <label class="lab">联系电话 <span class="req">*</span></label>
              <input class="input" name="<?= zp_e($fm['phone']) ?>" id="fTel" inputmode="tel" maxlength="20" placeholder="612 34 56 78">
            </div>
          </div>

          <div class="row">
            <div class="field">
              <label class="lab">微信 <span class="opt">选填</span></label>
              <input class="input" name="<?= zp_e($fm['wechat']) ?>" id="fWx" maxlength="60" placeholder="微信号">
            </div>
            <div class="field">
              <label class="lab">防机器人验证 <span class="req">*</span> <span class="opt"><?= zp_e($captchaQ) ?></span></label>
              <input class="input" name="<?= zp_e($fm['captcha']) ?>" inputmode="numeric" maxlength="3" placeholder="算一下填答案">
            </div>
          </div>

          <button class="submit" id="submitBtn" type="submit">发布招聘</button>
          <p class="formerr" id="formErr"></p>

          <div class="notes">
            <div class="note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>招到人/找到活后信息 <b>30 天</b>自动过期下架，列表永远新鲜。</span></div>
            <div class="note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span>电话/微信默认<b>不显示明文</b>，对方点「拨打/查看」才看到，防爬虫采集。</span></div>
            <div class="note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/></svg><span>游客发布后<b>不可修改</b>；想要改帖/顶帖的控制权，之后可用 Google 注册发布。</span></div>
          </div>
        </form>
      </div>

      <!-- ===== 实时预览 ===== -->
      <div class="previewcol">
        <div class="peyebrow"><span class="d"></span>实时预览</div>
        <div class="card" id="preview"><!-- 由 assets/js/publish.js 渲染 --></div>
        <p class="pafter">发布后会显示在「今日」最上方</p>
      </div>

    </div>
  </div>

  <div class="toast" id="toast"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg><span id="toastMsg"></span></div>

  <script>window.ZP_CITIES = <?= json_encode($cities, JSON_UNESCAPED_UNICODE) ?>;</script>
