<?php
/**
 * 站点勘察辅助（P0）：在服务器上抓取指定 URL，存下真实 HTML，并可即时测选择器。
 * 不入库、不去重，纯粹用于确认列表页/详情页结构，回填 site_config。
 *
 * 用法：
 *   # 抓列表页，存 HTML 到 app/cj/logs/
 *   php app/cj/bin/probe.php --url="https://infohuaxin.com/showclass.asp?class1=13"
 *
 *   # 抓页面并测“列表页→详情链接”选择器，打印命中的前若干条链接
 *   php app/cj/bin/probe.php --url="https://…" --links=".news_list a"
 *
 *   # 抓详情页并测各字段选择器，打印取到的文本
 *   php app/cj/bin/probe.php --url="https://…/detail.asp?id=1" --text=".title,.content,.tel"
 *
 *   # 指定站点配置（复用其频率/编码），或手动指定编码
 *   php app/cj/bin/probe.php --url="…" --site=oulang
 *   php app/cj/bin/probe.php --url="…" --charset=gbk
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require dirname(__DIR__) . '/bootstrap.php';

use Cj\Fetcher\Fetcher;
use Cj\Parser\CssSelector;

$opt = getopt('', ['url:', 'site::', 'charset::', 'links::', 'text::']);
if (empty($opt['url'])) {
    fwrite(STDERR, "用法：php probe.php --url=\"<页面地址>\" [--site=oulang] [--charset=gbk] [--links=\"选择器\"] [--text=\"选择器,选择器\"]\n");
    exit(1);
}
$url = (string) $opt['url'];

// 频率/编码：优先取站点配置
$rate = ['min_delay' => 1, 'max_delay' => 2];
$charset = $opt['charset'] ?? null;
if (!empty($opt['site'])) {
    $file = CJ_APP_ROOT . '/config/sites/' . preg_replace('/[^a-z0-9_]/i', '', (string) $opt['site']) . '.php';
    if (is_file($file)) {
        $sc = require $file;
        $rate = $sc['rate_limit'] ?? $rate;
        $charset = $charset ?? $sc['charset'] ?? null;
    }
}

$fetcher = new Fetcher('probe', $rate);
$res = $fetcher->get($url, $charset);
echo "HTTP {$res['status']}  " . ($charset ? "(编码 $charset→UTF-8)  " : '') . strlen((string) $res['body']) . " 字节\n";
if ($res['status'] !== 200 || $res['body'] === null) {
    fwrite(STDERR, "抓取失败。若为乱码/空白，多半是编码问题，试加 --charset=gbk。\n");
    exit(1);
}

// 存档 HTML
$dir = cj_config('log_dir') ?: CJ_APP_ROOT . '/logs';
@mkdir($dir, 0750, true);
$host = preg_replace('/[^a-z0-9.]/i', '_', parse_url($url, PHP_URL_HOST) ?? 'page');
$out = $dir . '/probe-' . $host . '-' . date('His') . '.html';
file_put_contents($out, $res['body']);
echo "HTML 已存：$out\n";
echo "（把这个文件发给我，或复制其中列表/详情部分，我据实填选择器）\n";

$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML('<?xml encoding="UTF-8">' . $res['body'], LIBXML_NOWARNING | LIBXML_NOERROR);
libxml_clear_errors();
$xp = new DOMXPath($doc);

// 测“详情链接”选择器
if (!empty($opt['links'])) {
    $q = CssSelector::toXPath((string) $opt['links']);
    $nodes = $xp->query($q);
    $n = $nodes ? $nodes->length : 0;
    echo "\n[链接选择器] {$opt['links']}  →  命中 $n 个\n";
    for ($i = 0; $i < min($n, 10); $i++) {
        $a = $nodes->item($i);
        $href = $a->getAttribute('href');
        $txt = trim(preg_replace('/\s+/u', ' ', $a->textContent) ?? '');
        echo '  - ' . mb_substr($txt, 0, 30) . '  →  ' . $href . "\n";
    }
}

// 测字段选择器
if (!empty($opt['text'])) {
    echo "\n[字段选择器]\n";
    foreach (explode(',', (string) $opt['text']) as $sel) {
        $sel = trim($sel);
        if ($sel === '') {
            continue;
        }
        $nodes = $xp->query(CssSelector::toXPath($sel));
        $val = ($nodes && $nodes->length) ? trim(preg_replace('/\s+/u', ' ', $nodes->item(0)->textContent) ?? '') : '（未命中）';
        echo "  $sel  →  " . mb_substr($val, 0, 60) . "\n";
    }
}

echo "\n提示：先用 --links 定位详情链接，再抓一个详情页用 --text 逐个确认字段选择器，\n";
echo "     确认后把选择器填进 app/cj/config/sites/<站点>.php 并把 enabled 改为 true。\n";
