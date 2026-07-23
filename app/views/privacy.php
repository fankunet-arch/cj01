<?php
/**
 * 隐私政策页：把 app/content/privacy.md 渲染成 HTML。
 * 轻量转换（仅支持本文档用到的语法：#标题/引用/列表/加粗/分隔线），先转义再标记。
 * @var string $md
 */
function zp_md_html(string $md): string
{
    $out = [];
    $inList = false;
    $closeList = function () use (&$inList, &$out) {
        if ($inList) { $out[] = '</ul>'; $inList = false; }
    };
    $inline = function (string $s): string {
        $s = zp_e($s);
        return preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $s) ?? $s;
    };
    foreach (preg_split('/\r?\n/', $md) ?: [] as $line) {
        $t = rtrim($line);
        if (preg_match('/^(#{1,3})\s+(.*)$/', $t, $m)) {
            $closeList();
            $level = strlen($m[1]) + 1; // # → h2，页面 h1 留给页头
            $out[] = "<h$level>" . $inline($m[2]) . "</h$level>";
        } elseif (preg_match('/^>\s?(.*)$/', $t, $m)) {
            $closeList();
            $out[] = '<blockquote>' . $inline($m[1]) . '</blockquote>';
        } elseif (preg_match('/^-\s+(.*)$/', $t, $m)) {
            if (!$inList) { $out[] = '<ul>'; $inList = true; }
            $out[] = '<li>' . $inline($m[1]) . '</li>';
        } elseif (preg_match('/^-{3,}$/', $t)) {
            $closeList();
            $out[] = '<hr>';
        } elseif ($t === '') {
            $closeList();
        } else {
            $closeList();
            $out[] = '<p>' . $inline($t) . '</p>';
        }
    }
    $closeList();
    return implode("\n", $out);
}
?>
  <nav class="nav">
    <a class="back" href="/"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>返回</a>
    <a class="brand" href="/"><span class="zh">西华<span class="hl">招聘</span></span></a>
    <span class="spacer"></span>
    <?php require __DIR__ . '/_navuser.php'; ?>
    <span class="dom">zhaopin.es</span>
  </nav>

  <div class="shell">
    <article class="privacy-doc">
      <h1>隐私政策与 Cookie · Política de Privacidad y Cookies</h1>
      <?= zp_md_html($md) ?>
    </article>
  </div>
