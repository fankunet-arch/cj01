<?php

declare(strict_types=1);

namespace Cj\Normalizer;

/**
 * 文本归一化与切分：SimHash 的输入预处理（文档 §3.3 信号 B）。
 * 分词采用 2-gram 字符切分（零依赖，冷启动够用；日后可换 scws / jieba-php）。
 */
final class TextNormalizer
{
    /** 归一化：全角转半角、去标点/空白、转小写。 */
    public static function normalize(string $text): string
    {
        $text = mb_convert_kana($text, 'asKV', 'UTF-8');   // 全角 → 半角
        $text = mb_strtolower($text);
        // 只保留中日韩文字、字母、数字
        $text = preg_replace('/[^\p{Han}\p{L}\p{N}]+/u', '', $text) ?? $text;
        return $text;
    }

    /**
     * 2-gram 切分，返回 token => 词频。
     * 中文短文本上 2-gram 与轻量分词效果接近且无依赖。
     */
    public static function bigrams(string $normalized): array
    {
        $len = mb_strlen($normalized);
        if ($len === 0) {
            return [];
        }
        if ($len === 1) {
            return [$normalized => 1];
        }
        $tokens = [];
        for ($i = 0; $i < $len - 1; $i++) {
            $gram = mb_substr($normalized, $i, 2);
            $tokens[$gram] = ($tokens[$gram] ?? 0) + 1;
        }
        return $tokens;
    }
}
