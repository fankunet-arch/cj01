<?php require __DIR__ . '/_nav.php'; /** @var array $rows @var bool $saved @var array $descriptions */ ?>
  <div class="shell a-shell">
    <?php if ($saved): ?><p class="a-saved">✓ 已保存</p><?php endif; ?>
    <form method="post" action="/c/cp/settings">
      <input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>">
      <div class="a-tablewrap"><table class="a-table">
        <tr><th>参数</th><th>说明</th><th style="min-width:200px">值</th><th>最后修改</th></tr>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><code><?= zp_e($r['skey']) ?></code></td>
          <td class="c-desc"><?= zp_e($descriptions[$r['skey']] ?? '（自定义参数）') ?></td>
          <td class="c-val"><input class="input" type="text" name="s[<?= zp_e($r['skey']) ?>]" value="<?= zp_e($r['svalue'] ?? '') ?>"></td>
          <td><?= $r['updated_at'] !== null ? zp_e(zp_time_ago($r['updated_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </table></div>
      <button class="a-btn primary" type="submit">保存全部</button>
    </form>
    <p class="a-note" style="margin-top:12px">改动即时生效（下一次页面请求起）。数值类参数填纯数字；report_recipients 和 field_name_map 必须是合法 JSON。</p>
  </div>
