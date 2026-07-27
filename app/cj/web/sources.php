<?php
/**
 * 采集源一览：读取 app/cj/config/sites/*.php，展示每个站点配置的状态与关键字段。
 * 只读页面——改采集源仍在配置文件里改（站点勘察 P0 回填选择器后把 enabled 置 true）。
 */

// 连通性检测：抓一次目标 URL，报告状态/Server 头/字节数，快速判断是否被反爬拦
$check = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_url') {
    $url = trim((string) ($_POST['url'] ?? ''));
    $charset = trim((string) ($_POST['charset'] ?? '')) ?: null;
    if (!preg_match('#^https?://#i', $url)) {
        $check = ['url' => $url, 'error' => '请填写以 http(s):// 开头的完整地址'];
    } else {
        try {
            $f = new \Cj\Fetcher\Fetcher('probe', ['min_delay' => 0, 'max_delay' => 0]);
            $res = $f->get($url, $charset);
            $body = (string) ($res['body'] ?? '');
            $sv = $f->serverHeader();
            $cf = $sv !== null && stripos($sv, 'cloudflare') !== false;
            $check = [
                'url' => $url,
                'status' => $res['status'],
                'server' => $sv,
                'bytes' => strlen($body),
                'cloudflare' => $cf,
                'ok' => $res['status'] === 200 && $body !== '',
                'error' => null,
            ];
        } catch (Throwable $e) {
            $check = ['url' => $url, 'error' => $e->getMessage()];
        }
    }
}

$sitesDir = CJ_APP_ROOT . '/config/sites';
$sites = [];
foreach (glob($sitesDir . '/*.php') ?: [] as $file) {
    $cfg = require $file;
    $cfg['_file'] = basename($file);
    $sites[] = $cfg;
}
// 站点中文名映射（仅展示用）
$labels = [
    'oulang' => '欧浪网', 'ouhua' => '欧华网', 'huarenjie' => '华人街', 'xihua' => '西华网',
];
$contactModeLabel = ['plain' => '明文', 'click' => '点击展开', 'login_wall' => '登录墙（不采）'];

$pageTitle = '采集源';
$renderBody = function () use ($sites, $labels, $contactModeLabel, $check) {
    ?>
    <div class="card">
        <h2>连通性检测</h2>
        <p class="muted">粘贴任意列表页/详情页地址，服务器抓一次并报告状态与 Server 头，快速判断该源是否可采（被 Cloudflare 等反爬拦截会显示出来）。</p>
        <form method="post">
            <input type="hidden" name="action" value="check_url">
            <input type="text" name="url" placeholder="https://…" style="width:60%;min-width:280px"
                   value="<?= isset($check['url']) ? cj_e($check['url']) : '' ?>">
            <input type="text" name="charset" placeholder="编码(可空,如 gbk)" style="width:130px">
            <button class="btn btn-primary" type="submit">检测</button>
        </form>
        <?php if ($check !== null): ?>
            <?php if (!empty($check['error'])): ?>
                <p class="status-failed">出错：<?= cj_e($check['error']) ?></p>
            <?php else: ?>
                <p class="<?= $check['ok'] ? 'status-ok' : 'status-failed' ?>">
                    HTTP <?= (int) $check['status'] ?> · <?= (int) $check['bytes'] ?> 字节
                    <?php if ($check['server'] !== null): ?> · 响应头 <?= cj_e($check['server']) ?><?php endif; ?>
                    <?php if ($check['cloudflare']): ?> · <strong>⚠ Cloudflare 拦截（此源服务器端不可采）</strong>
                    <?php elseif ($check['ok']): ?> · <strong>✅ 可正常抓取</strong><?php endif; ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>采集源（<?= count($sites) ?>）</h2>
        <?php if ($sites === []): ?>
            <p class="muted">未找到采集源配置（app/cj/config/sites/*.php）。</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>站点</th><th>状态</th><th>列表页</th><th>联系方式</th>
                    <th>渲染</th><th>频率(秒)</th><th>分类</th><th>配置文件</th>
                </tr>
                <?php foreach ($sites as $s): ?>
                    <?php
                    $enabled = !empty($s['enabled']);
                    $rl = $s['rate_limit'] ?? [];
                    $rate = isset($rl['min_delay'], $rl['max_delay']) ? "{$rl['min_delay']}–{$rl['max_delay']}" : '—';
                    ?>
                    <tr>
                        <td>
                            <span class="pill"><?= cj_e($s['site'] ?? '?') ?></span>
                            <?= cj_e($labels[$s['site'] ?? ''] ?? '') ?>
                        </td>
                        <td>
                            <?php if ($enabled): ?>
                                <span class="status-ok">已启用</span>
                            <?php else: ?>
                                <span class="muted">停用</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted" style="max-width:320px;word-break:break-all">
                            <?= cj_e($s['list_url'] ?? '—') ?>
                        </td>
                        <td><?= cj_e($contactModeLabel[$s['contact_mode'] ?? ''] ?? ($s['contact_mode'] ?? '—')) ?></td>
                        <td><?= cj_e(($s['render'] ?? 'php') === 'headless' ? 'headless(JS)' : 'php') ?></td>
                        <td><?= cj_e($rate) ?></td>
                        <td class="muted"><?= cj_e($s['category'] ?? '—') ?></td>
                        <td class="muted"><?= cj_e($s['_file'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        <p class="muted">
            采集源在 <code>app/cj/config/sites/*.php</code> 中定义（站点改版只改配置、不动代码）。
            新站点勘察（确认列表页/详情页选择器、联系方式获取方式、是否 JS 渲染、robots）后，
            回填选择器并把 <code>enabled</code> 置为 <code>true</code> 即生效。
            采集由 <code>app/cj/bin/crawl.php</code>（cron 或首页「一键采集」）触发，
            运行情况见<a href="<?= cj_e(cj_url('dashboard.php')) ?>">运行看板</a>。
        </p>
    </div>
    <?php
};

require __DIR__ . '/layout.php';
