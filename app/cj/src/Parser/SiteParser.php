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
        $xpath = $this->xpath($html);
        $sel = $this->config['detail'];
        $loginWall = ($this->config['contact_mode'] ?? 'plain') === 'login_wall';

        return [
            'title'          => $this->text($xpath, $sel['title'] ?? null),
            'company'        => $this->text($xpath, $sel['company'] ?? null),
            'salary_raw'     => $this->text($xpath, $sel['salary'] ?? null),
            'description'    => $this->text($xpath, $sel['desc'] ?? null),
            // 登录墙内容不采集也不绕过，联系方式置空（文档 §2.2 降级策略）
            'contact_phone'  => $loginWall ? null : $this->text($xpath, $sel['phone'] ?? null),
            'contact_wechat' => $loginWall ? null : $this->text($xpath, $sel['wechat'] ?? null),
            'contact_name'   => $this->text($xpath, $sel['contact_name'] ?? null),
            'city'           => $this->text($xpath, $sel['city'] ?? null),
            'district'       => $this->text($xpath, $sel['district'] ?? null),
            'publish_date'   => $this->date($this->text($xpath, $sel['date'] ?? null)),
            'category'       => $this->config['category'] ?? null,
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        // 强制按 UTF-8 解析（Fetcher 已做编码转换）
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return new DOMXPath($doc);
    }

    private function text(DOMXPath $xpath, ?string $css): ?string
    {
        if ($css === null || $css === '') {
            return null;
        }
        $nodes = $xpath->query(CssSelector::toXPath($css));
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
