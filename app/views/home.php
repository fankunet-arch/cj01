<?php
/**
 * 首页（信息墙）：服务端渲染真实数据。
 * @var string $mode  @var int $type  @var array $filters  @var array $counts
 * @var int $todayNew @var int $coveredRegions
 * @var array $regions @var array $regionCounts @var array $categories @var array $catCounts
 * @var array $groups  今日/昨天/更早 => posts[]
 */
$hire = $mode !== 'seek';
$qs = fn(array $extra) => '/list?' . http_build_query(array_filter(array_merge([
    'type' => $mode === 'seek' ? 'seek' : 'job',
    'region' => $filters['region_id'] ?: null,
    'cat' => $filters['category_id'] ?: null,
    'q' => $filters['q'] !== '' ? $filters['q'] : null,
], $extra), fn($v) => $v !== null && $v !== ''));
$total = count($groups['今日']) + count($groups['昨天']) + count($groups['更早']);
$regionName = '全西班牙';
foreach ($regions as $r) {
    if ((int) $r['id'] === $filters['region_id']) { $regionName = strtok($r['name'], ' '); break; }
}
?>
  <!-- ===== 桌面顶栏 ===== -->
  <nav class="d-nav">
    <a class="brand" href="/"><span class="zh">西华<span class="hl">招聘</span></span></a>
    <form class="d-search" method="get" action="/list">
      <input type="hidden" name="type" value="<?= $hire ? 'job' : 'seek' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4-4"/></svg>
      <input name="q" value="<?= zp_e($filters['q']) ?>" placeholder="搜内容、联系人、地区…">
    </form>
    <div class="spacer"></div>
    <?php require __DIR__ . '/_navuser.php'; ?>
    <button class="postbtn" data-href="/publish"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>发布信息</button>
  </nav>

  <div class="band">
    <span class="eyebrow"><span class="d"></span>西班牙华人 · 餐饮 / 百元店 / 美甲 招工求职</span>
    <h1>在 <span class="loc"><?= zp_e($regionName) ?></span> <?= $hire ? '找活、招人' : '想招到的人在这' ?></h1>
    <p class="sub">看一眼就联系上 —— <b>免注册发布、一键拨打、每天刷新</b>，不用研究网站。</p>
    <div class="bandrow">
      <div class="seg">
        <button data-href="<?= zp_e($qs(['type' => 'job'])) ?>" class="<?= $hire ? 'on' : '' ?>">招聘 <span class="n"><?= (int) $counts['hire'] ?></span></button>
        <button data-href="<?= zp_e($qs(['type' => 'seek'])) ?>" class="<?= $hire ? '' : 'on' ?>">求职 <span class="n"><?= (int) $counts['seek'] ?></span></button>
      </div>
      <div class="stats">
        <div class="stat"><div class="v up">+<?= (int) $todayNew ?></div><div class="l">今日新增</div></div>
        <div class="stat"><div class="v"><?= (int) $counts[$hire ? 'hire' : 'seek'] ?></div><div class="l"><?= $hire ? '在招岗位' : '在找工作' ?></div></div>
        <div class="stat"><div class="v"><?= (int) $coveredRegions ?></div><div class="l">覆盖地区</div></div>
      </div>
    </div>
  </div>

  <!-- ===== 移动端头部 ===== -->
  <header class="m-head">
    <div class="m-bar">
      <a class="brand" href="/"><span class="zh">西华<span class="hl">招聘</span></span></a>
      <?php require __DIR__ . '/_navuser.php'; ?>
    </div>
    <div class="m-seg">
      <div class="seg">
        <button data-href="<?= zp_e($qs(['type' => 'job'])) ?>" class="<?= $hire ? 'on' : '' ?>">招聘 <span class="n"><?= (int) $counts['hire'] ?></span></button>
        <button data-href="<?= zp_e($qs(['type' => 'seek'])) ?>" class="<?= $hire ? '' : 'on' ?>">求职 <span class="n"><?= (int) $counts['seek'] ?></span></button>
      </div>
    </div>
    <div class="m-chips">
      <a class="chip <?= $filters['category_id'] === 0 ? 'act' : '' ?>" href="<?= zp_e($qs(['cat' => null])) ?>">全部</a>
      <?php foreach ($categories as $c): if (($catCounts[(int) $c['id']] ?? 0) === 0 && $filters['category_id'] !== (int) $c['id']) continue; ?>
      <a class="chip <?= $filters['category_id'] === (int) $c['id'] ? 'act' : '' ?>" href="<?= zp_e($qs(['cat' => (int) $c['id']])) ?>"><?= zp_e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </header>

  <!-- ===== 主体 ===== -->
  <div class="wrap">
    <aside class="rail">
      <div class="group">
        <div class="gt">地区</div>
        <div class="filt">
          <a class="<?= $filters['region_id'] === 0 ? 'on' : '' ?>" href="<?= zp_e($qs(['region' => null])) ?>">全部地区<span class="c"><?= (int) $counts[$hire ? 'hire' : 'seek'] ?></span></a>
          <?php foreach ($regions as $r): $c = $regionCounts[(int) $r['id']] ?? 0; if ($c === 0 && $filters['region_id'] !== (int) $r['id']) continue; ?>
          <a class="<?= $filters['region_id'] === (int) $r['id'] ? 'on' : '' ?>" href="<?= zp_e($qs(['region' => (int) $r['id']])) ?>"><?= zp_e(strtok($r['name'], ' ')) ?><span class="c"><?= $c ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="group">
        <div class="gt">类别</div>
        <div class="filt">
          <a class="<?= $filters['category_id'] === 0 ? 'on' : '' ?>" href="<?= zp_e($qs(['cat' => null])) ?>">全部类别<span class="c"><?= (int) $counts[$hire ? 'hire' : 'seek'] ?></span></a>
          <?php foreach ($categories as $c): $n = $catCounts[(int) $c['id']] ?? 0; if ($n === 0 && $filters['category_id'] !== (int) $c['id']) continue; ?>
          <a class="<?= $filters['category_id'] === (int) $c['id'] ? 'on' : '' ?>" href="<?= zp_e($qs(['cat' => (int) $c['id']])) ?>"><?= zp_e($c['name']) ?><span class="c"><?= $n ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="postcard">
        <h4><?= $hire ? '要招人？' : '在找活？' ?></h4>
        <p>发布只填几项，1 分钟搞定。招满随手下架，否则 30 天自动过期。</p>
        <button data-href="/publish">免费发布<?= $hire ? '招聘' : '求职' ?></button>
      </div>
    </aside>

    <main id="feed">
      <?php if ($total === 0): ?>
      <div class="day">暂无信息</div>
      <p class="feed-empty">这个筛选条件下还没有信息。<a href="/publish">来发第一条 →</a></p>
      <?php endif; ?>
      <?php foreach ($groups as $label => $posts): if ($posts === []) continue; ?>
      <div class="day <?= $label !== '今日' ? 'gap' : '' ?>"><?= zp_e($label) ?></div>
      <div class="grid">
        <?php foreach ($posts as $post) { require __DIR__ . '/_card.php'; } ?>
      </div>
      <?php endforeach; ?>
    </main>
  </div>

  <!-- 移动端底部发布栏 -->
  <div class="m-post">
    <button data-href="/publish"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>发布信息 <span class="free">· 免费 · 30天有效</span></button>
  </div>

  <div class="toast" id="toast"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg><span id="toastMsg"></span></div>
