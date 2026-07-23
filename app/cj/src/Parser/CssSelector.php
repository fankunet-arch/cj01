<?php

declare(strict_types=1);

namespace Cj\Parser;

/**
 * 极简 CSS 选择器 → XPath 转换器，覆盖 site_config 常用形态：
 *   tag / .class / #id / tag.class / tag#id / 后代组合（空格）。
 * 装了 symfony/css-selector（Composer）时优先用官方实现，此类为零依赖兜底。
 */
final class CssSelector
{
    public static function toXPath(string $css): string
    {
        if (class_exists(\Symfony\Component\CssSelector\CssSelectorConverter::class)) {
            return (new \Symfony\Component\CssSelector\CssSelectorConverter())->toXPath($css);
        }

        $parts = preg_split('/\s+/', trim($css)) ?: [];
        $xpath = '';
        foreach ($parts as $part) {
            $xpath .= '//' . self::simple($part);
        }
        return $xpath !== '' ? $xpath : '//*';
    }

    private static function simple(string $sel): string
    {
        if (!preg_match('/^([a-zA-Z][a-zA-Z0-9]*)?(?:([.#])([\w-]+))?$/', $sel, $m)) {
            return '*';   // 不支持的形态退化为通配（建议装 symfony/css-selector）
        }
        $tag = $m[1] !== '' ? $m[1] : '*';
        if (!isset($m[2]) || $m[2] === '') {
            return $tag;
        }
        if ($m[2] === '#') {
            return sprintf("%s[@id='%s']", $tag, $m[3]);
        }
        return sprintf("%s[contains(concat(' ',normalize-space(@class),' '),' %s ')]", $tag, $m[3]);
    }
}
