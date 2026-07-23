<?php require __DIR__ . '/_nav.php'; /** @var array $post @var string $err @var int $curTop @var int $topDaysLeft */ ?>
  <div class="shell a-shell" style="max-width:760px">
    <h2 class="a-h2">编辑信息（管理员）</h2>
    <?php if ($err !== ''): ?><p class="a-err"><?= zp_e($err) ?></p><?php endif; ?>

    <form class="u-panel a-editpanel" method="post" action="/c/cp/post_edit?id=<?= zp_e($post['public_code']) ?>">
      <input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>">

      <div class="row">
        <div class="field">
          <label class="lab">板块</label>
          <span class="selwrap"><select class="sel" name="type">
            <option value="1" <?= (int) $post['type'] === 1 ? 'selected' : '' ?>>招聘</option>
            <option value="2" <?= (int) $post['type'] === 2 ? 'selected' : '' ?>>求职</option>
          </select></span>
        </div>
        <div class="field">
          <label class="lab">职位类别</label>
          <span class="selwrap"><select class="sel" name="category_id">
            <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $post['category_id'] ? 'selected' : '' ?>><?= zp_e($c['name']) ?></option>
            <?php endforeach; ?>
          </select></span>
        </div>
      </div>

      <div class="field">
        <label class="lab">信息内容</label>
        <textarea class="area big" name="content" maxlength="1000"><?= zp_e($post['content']) ?></textarea>
      </div>

      <div class="row">
        <div class="field">
          <label class="lab">所在大区</label>
          <span class="selwrap"><select class="sel" id="fRegionTop">
            <?php foreach ($topRegions as $r): ?>
            <option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === $curTop ? 'selected' : '' ?>><?= zp_e($r['name']) ?></option>
            <?php endforeach; ?>
          </select></span>
        </div>
        <div class="field">
          <label class="lab">城市</label>
          <span class="selwrap"><select class="sel" name="region_id" id="fRegion" data-current="<?= (int) $post['region_id'] ?>"></select></span>
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label class="lab">联系人称呼</label>
          <input class="input" name="contact_name" maxlength="50" value="<?= zp_e($post['contact_name']) ?>">
        </div>
        <div class="field">
          <label class="lab">联系电话</label>
          <input class="input" name="phone" maxlength="20" value="<?= zp_e($post['phone']) ?>">
        </div>
        <div class="field">
          <label class="lab">微信（可清空）</label>
          <input class="input" name="wechat" maxlength="60" value="<?= zp_e($post['wechat'] ?? '') ?>">
        </div>
      </div>

      <div class="row">
        <div class="field">
          <label class="lab">置顶天数（0 = 不置顶 / 取消置顶）</label>
          <input class="input" type="number" name="top_days" min="0" max="365" value="<?= (int) $topDaysLeft ?>">
        </div>
        <div class="field">
          <label class="lab">标记</label>
          <label class="a-check" style="padding-top:12px"><input type="checkbox" name="suspicious" value="1" <?= (int) $post['suspicious'] === 1 ? 'checked' : '' ?>>可疑信息（仅后台高亮）</label>
        </div>
      </div>

      <button class="a-btn primary" type="submit" style="width:100%;padding:13px">保存修改</button>
    </form>
  </div>

  <script>window.ZP_CITIES = <?= json_encode($cities, JSON_UNESCAPED_UNICODE) ?>;</script>
