<?php require __DIR__ . '/_nav.php'; ?>
  <div class="shell a-shell">
    <form class="a-filter" method="get" action="/c/cp/posts">
      <select name="type" class="sel">
        <option value="">全部板块</option>
        <option value="1" <?= ($_GET['type'] ?? '') === '1' ? 'selected' : '' ?>>招聘</option>
        <option value="2" <?= ($_GET['type'] ?? '') === '2' ? 'selected' : '' ?>>求职</option>
      </select>
      <select name="status" class="sel">
        <option value="">全部状态</option>
        <?php foreach ([1 => '在线', 0 => '已到期', 2 => '已下架', 3 => '已删除'] as $v => $l): ?>
        <option value="<?= $v ?>" <?= ($_GET['status'] ?? '') === (string) $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <label class="a-check"><input type="checkbox" name="sus" value="1" <?= ($_GET['sus'] ?? '') === '1' ? 'checked' : '' ?>>只看可疑</label>
      <input class="input" type="text" name="q" value="<?= zp_e($q) ?>" placeholder="搜内容 / 联系人 / 电话">
      <button class="a-btn" type="submit">筛选</button>
    </form>

    <div class="a-tablewrap"><table class="a-table">
      <tr><th>板块</th><th>内容</th><th>联系人 / 电话</th><th>地区 / 类别</th><th>热度</th><th>举报</th><th>状态</th><th>时间</th><th>操作</th></tr>
      <?php foreach ($rows as $r): ?>
      <tr class="<?= (int) $r['suspicious'] === 1 ? 'sus' : '' ?>">
        <td><?= (int) $r['type'] === 1 ? '招聘' : '求职' ?></td>
        <td class="c-content"><a class="a-link" href="/detail?id=<?= zp_e($r['public_code']) ?>" target="_blank"><?= zp_e(zp_post_excerpt($r['content'], 28)) ?></a><?= (int) $r['suspicious'] === 1 ? ' <span class="flag">可疑</span>' : '' ?></td>
        <td><?= zp_e($r['contact_name']) ?><br><small><?= zp_e($r['phone']) ?></small></td>
        <td><?= zp_e($r['region_name']) ?><br><small><?= zp_e($r['category_name']) ?></small></td>
        <td>📞<?= (int) $r['phone_views'] ?> 💬<?= (int) $r['wechat_views'] ?></td>
        <td><?= (int) $r['report_count'] ?></td>
        <td><?= zp_e(zp_post_status_label((int) $r['status'])) ?></td>
        <td><?= zp_e(zp_time_ago($r['created_at'])) ?></td>
        <td class="c-act">
          <a class="a-btn sm" href="/c/cp/post_edit?id=<?= zp_e($r['public_code']) ?>">编辑</a>
          <?php if ((int) $r['status'] === 1): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="offline"><button class="a-btn sm">下架</button></form>
          <?php elseif ((int) $r['status'] !== 3): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="restore"><button class="a-btn sm">恢复</button></form>
          <?php endif; ?>
          <?php if ((int) $r['status'] !== 3): ?>
          <form method="post" onsubmit="return confirm('确认删除？')"><input type="hidden" name="csrf" value="<?= zp_e($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="a-btn sm danger">删除</button></form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?><tr><td colspan="9" class="empty">没有匹配的信息</td></tr><?php endif; ?>
    </table></div>

    <div class="a-pager">
      <?php $qs = $_GET; ?>
      <?php if ($pageNo > 1): $qs['p'] = $pageNo - 1; ?><a class="a-btn sm" href="?<?= zp_e(http_build_query($qs)) ?>">上一页</a><?php endif; ?>
      <?php if (count($rows) === 50): $qs['p'] = $pageNo + 1; ?><a class="a-btn sm" href="?<?= zp_e(http_build_query($qs)) ?>">下一页</a><?php endif; ?>
    </div>
  </div>
