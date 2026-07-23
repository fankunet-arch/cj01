<?php require __DIR__ . '/_nav.php';
$statusLabel = [0 => '未兑换', 1 => '已兑换未用', 2 => '已使用', 3 => '已过期', 4 => '已作废'];
?>
  <div class="shell a-shell">
    <?php if (($notice ?? '') !== ''): ?><p class="a-saved">✓ <?= zp_e($notice) ?></p><?php endif; ?>

    <?php if (($justGenerated ?? []) !== []): ?>
    <div class="a-newcodes">
      <b>本次生成的兑换码（发给用户，仅此一次完整显示）：</b>
      <div class="codes"><?php foreach ($justGenerated as $c): ?><code><?= zp_e($c) ?></code><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <h2 class="a-h2">生成置顶券</h2>
    <form class="a-filter" method="post" action="/c/cp/coupons">
      <input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>">
      <input type="hidden" name="action" value="generate">
      <label class="a-lbl">数量 <input class="input" type="number" name="count" value="5" min="1" max="50" style="width:70px"></label>
      <label class="a-lbl">置顶天数 <input class="input" type="number" name="top_days" value="7" min="1" max="90" style="width:70px"></label>
      <label class="a-lbl">兑换有效期(天) <input class="input" type="number" name="valid_days" value="30" min="1" max="365" style="width:70px"></label>
      <button class="a-btn primary" type="submit">生成</button>
    </form>
    <p class="a-note">流程：这里生成兑换码 → 线下/微信发给注册用户 → 用户在「我的发布」兑换入账 → 对自己的帖子使用。用户丢码时把旧码作废再生成新的即可。已使用的券不可作废。</p>

    <h2 class="a-h2">台账（最近 200 张）</h2>
    <div class="a-tablewrap"><table class="a-table">
      <tr><th>兑换码</th><th>置顶天数</th><th>状态</th><th>兑换有效期</th><th>归属用户</th><th>所用帖子</th><th>生成人 / 时间</th><th>操作</th></tr>
      <?php foreach ($rows as $r): $st = (int) $r['status']; ?>
      <tr class="<?= $st === 4 ? 'sus' : '' ?>">
        <td><code><?= zp_e($r['code']) ?></code></td>
        <td><?= (int) $r['top_days'] ?> 天</td>
        <td><b><?= zp_e($statusLabel[$st] ?? (string) $st) ?></b></td>
        <td><?= zp_e(zp_madrid($r['valid_until'])->format('Y-m-d')) ?></td>
        <td><?= $r['owner_name'] !== null ? zp_e($r['owner_name']) : '—' ?></td>
        <td><?= $r['post_code'] !== null ? '<a class="a-link" target="_blank" href="/detail?id=' . zp_e($r['post_code']) . '">查看</a>' : '—' ?></td>
        <td><small><?= zp_e($r['creator_email'] ?? '—') ?><br><?= zp_e(zp_time_ago($r['created_at'])) ?></small></td>
        <td class="c-act">
          <?php if ($st !== 2 && $st !== 4): ?>
          <form method="post" onsubmit="return confirm('作废后该码不可再兑换/使用，确认？')"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="void"><button class="a-btn sm danger">作废</button></form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?><tr><td colspan="8" class="empty">还没有生成过置顶券</td></tr><?php endif; ?>
    </table></div>
  </div>
