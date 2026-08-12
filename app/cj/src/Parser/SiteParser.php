<?php

declare(strict_types=1);

namespace Cj\Parser;

use DOMDocument;
use DOMXPath;

/**
 * 配置驱动的站点解析器：按 site_config 的选择器把 HTML 解析为统一数据模型（文档 §2.1）。
 * 站点改版时只改 site_config，不动本类——采集器长期可维护的关键（§4.3）。
 */
final class SiteParser
{
    private array $config;

    public function __construct(array $siteConfig)
    {
        $this->config = $siteConfig;
    }

    /**
     * 详情页正文补全（不依赖任何选择器，源站改版也不会断）。
     *
     * 为什么需要：欧浪网列表页的 .category-detail-desc 是源站自己截断的预览
     * （以「...」结尾），而电话/微信通常写在帖子正文末尾，正好被截掉——
     * 结果是 contact_key 为空 → confidence=low → import_ready=0 → 永远导不进主库。
     * 详情页才有完整正文。
     *
     * 做法：剥掉 script/style/nav/header/footer/aside 后，取文本量最大的块级节点当正文，
     * 再顺带收集所有 tel: 链接（最可靠的电话来源）。
     * 返回 ['text' => ?string, 'tel' => ?string]。
     */
    public function extractDetailText(string $html): array
    {
        $xpath = $this->xpath($html);

        // tel: 链接优先——这是明确标注的电话，不需要猜
        $tel = null;
        foreach ($xpath->query('//a[starts-with(translate(@href,"TEL","tel"),"tel:")]') ?: [] as $a) {
            $v = trim(rawurldecode(substr($a->getAttribute('href'), 4)));
            if ($v !== '') {
                $tel = $v;
                break;
            }
        }

        // 噪音节点整体移除，避免把导航/页脚文字当正文
        foreach ($xpath->query('//script|//style|//noscript|//nav|//header|//footer|//aside|//form') ?: [] as $n) {
            $n->parentNode?->removeChild($n);
        }

        // 选正文块：父节点的文本必然包含子节点，直接取「最长」只会选中整页外层容器。
        // 用经典做法——先求最大文本量，再在「文本量 ≥ 最大值 60%」的候选里取最短的那个，
        // 即仍然装着绝大部分正文、但已经不含外层导航/推荐位的最内层节点。
        $cands = [];
        $maxLen = 0;
        foreach ($xpath->query('//body//div|//body//article|//body//section|//body//main') ?: [] as $n) {
            $t = $this->normalizeSpace($n->textContent);
            $len = mb_strlen($t);
            if ($len >= 30) {
                $cands[] = [$len, $t];
                $maxLen = max($maxLen, $len);
            }
        }
        if ($cands === []) {
            return ['text' => null, 'tel' => $tel];
        }
        $floor = (int) ($maxLen * 0.6);
        $best = null;
        $bestLen = PHP_INT_MAX;
        foreach ($cands as [$len, $t]) {
            if ($len >= $floor && $len < $bestLen) {
                $bestLen = $len;
                $best = $t;
            }
        }
        return ['text' => $best, 'tel' => $tel];
    }

    private function normalizeSpace(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /** 列表页 → 详情页绝对 URL 列表（去重、保序）。 */
    public function parseListPage(string $html, string $pageUrl): array
    {
        $xpath = $this->xpath($html);
        $query = CssSelector::toXPath($this->config['list_selector']);
        $urls = [];
        foreach ($xpath->query($query) ?: [] as $node) {
            $href = $node->getAttribute('href');
            if ($href === '') {
                continue;
            }
            $abs = $this->absoluteUrl($href, $pageUrl);
            if ($abs !== null) {
                $urls[$abs] = true;
            }
        }
        return array_keys($urls);
    }

    /** 详情页 → 统一数据模型原始字段（归一化在 Normalizer 层做）。 */
    public function parseDetailPage(string $html): array
    {
        return $this->extractFields($this->xpath($html), null);
    }

    /**
     * 列表页内联解析（mode=list_inline）：每个 item 容器 → 一条统一模型记录（含 source_url）。
     * 适用于列表页已内联全部字段的站点，无需再抓详情页（请求更少、更礼貌）。
     */
    public function parseListItems(string $html, string $pageUrl): array
    {
        $xpath = $this->xpath($html);
        $itemQuery = CssSelector::toXPath($this->config['list_item_selector'] ?? $this->config['list_selector']);
        $linkSel = $this->config['link_selector'] ?? null;

        $out = [];
        $seen = [];
        foreach ($xpath->query($itemQuery) ?: [] as $node) {
            $rec = $this->extractFields($xpath, $node);

            $url = null;
            if ($linkSel !== null) {
                $a = $xpath->query('.' . CssSelector::toXPath($linkSel), $node);
                if ($a !== false && $a->length > 0) {
                    $url = $this->absoluteUrl($a->item(0)->getAttribute('href'), $pageUrl);
                }
            }
            if ($url === null || $rec['title'] === null || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $rec['source_url'] = $url;
            $out[] = $rec;
        }
        return $out;
    }

    /** 从上下文节点（null=整篇文档）按 detail 选择器抽取统一字段。 */
    private function extractFields(DOMXPath $xpath, ?\DOMNode $ctx): array
    {
        $sel = $this->config['detail'];
        $loginWall = ($this->config['contact_mode'] ?? 'plain') === 'login_wall';

        return [
            'title'          => $this->textIn($xpath, $ctx, $sel['title'] ?? null),
            'company'        => $this->textIn($xpath, $ctx, $sel['company'] ?? null),
            'salary_raw'     => $this->textIn($xpath, $ctx, $sel['salary'] ?? null),
            'description'    => $this->textIn($xpath, $ctx, $sel['desc'] ?? null),
            // 登录墙内容不采集也不绕过，联系方式置空（文档 §2.2 降级策略）
            'contact_phone'  => $loginWall ? null : $this->textIn($xpath, $ctx, $sel['phone'] ?? null),
            'contact_wechat' => $loginWall ? null : $this->textIn($xpath, $ctx, $sel['wechat'] ?? null),
            'contact_name'   => $this->cleanContactName($this->textIn($xpath, $ctx, $sel['contact_name'] ?? null)),
            'city'           => $this->textIn($xpath, $ctx, $sel['city'] ?? null),
            'district'       => $this->textIn($xpath, $ctx, $sel['district'] ?? null),
            'publish_date'   => $this->date($this->textIn($xpath, $ctx, $sel['date'] ?? null)),
            'category'       => $this->config['category'] ?? null,
        ];
    }

    /** 去掉“联络人：/联系人：”标签前缀，截断到 · 或 联络电话 之前。 */
    private function cleanContactName(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = preg_replace('/^.*?(?:联络人|联系人)\s*[:：]\s*/u', '', $s) ?? $s;
        $s = preg_split('/\s*[·|]\s*|联络电话|联系电话/u', $s)[0] ?? $s;
        $s = trim($s);
        return $s !== '' ? $s : null;
    }

    private function xpath(string $html): DOMXPath
    {
        // Fetcher 已把正文转成 UTF-8，但页面原 <meta charset=gb2312> 等仍在，
        // 会让 libxml 按原编码二次解码 → 整个 DOM 乱码/为空。把 meta 里的 charset
        // 统一改成 UTF-8，确保按 UTF-8 解析（对 GBK/gb2312 老站尤为关键）。
        $html = preg_replace('#(<meta[^>]*charset=["\']?)[\w-]+#i', '${1}UTF-8', $html, 1) ?? $html;

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return new DOMXPath($doc);
    }

    /** 取选择器命中的首个节点文本；$ctx 非空时在该节点内相对查找（.//）。 */
    private function textIn(DOMXPath $xpath, ?\DOMNode $ctx, ?string $css): ?string
    {
        if ($css === null || $css === '') {
            return null;
        }
        $q = CssSelector::toXPath($css);
        $nodes = $ctx !== null ? $xpath->query('.' . $q, $ctx) : $xpath->query($q);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $t = trim(preg_replace('/\s+/u', ' ', $nodes->item(0)->textContent) ?? '');
        return $t !== '' ? $t : null;
    }

    /** 常见日期形态 → Y-m-d；解析失败返回 null。 */
    private function date(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        // 2026-07-04 / 2026/07/04 / 2026年7月4日
        if (preg_match('/(\d{4})[\/\-年](\d{1,2})[\/\-月](\d{1,2})/u', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    private function absoluteUrl(string $href, string $base): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, 'javascript:') || str_starts_with($href, '#')) {
            return null;
        }
        $p = parse_url($base);
        if (!isset($p['scheme'], $p['host'])) {
            return null;
        }
        $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($href, '//')) {
            return $p['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $root . $href;
        }
        $dir = isset($p['path']) ? preg_replace('#/[^/]*$#', '/', $p['path']) : '/';
        return $root . $dir . $href;
    }
}
