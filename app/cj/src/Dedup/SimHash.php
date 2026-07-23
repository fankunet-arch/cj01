<?php

declare(strict_types=1);

namespace Cj\Dedup;

use Cj\Normalizer\TextNormalizer;

/**
 * 64 位 SimHash 内容指纹（文档 §3.3 信号 B）。
 * 流程：归一化 → 2-gram 切分 → 每 token 64 位哈希加权 → 按位签名。
 *
 * PHP 整数为 64 位有符号；指纹在 PHP 内以“位模式”承载（可能为负数），
 * 入库/出库统一走 toDb() / fromHex() 保真转换，DB 列为 BIGINT UNSIGNED。
 * 查询时请用 LPAD(HEX(simhash),16,'0') 取十六进制再经 fromHex() 还原。
 */
final class SimHash
{
    /** 对 title + company + description 组合文本计算指纹。 */
    public static function ofJobText(?string $title, ?string $company, ?string $description): int
    {
        return self::compute(trim(($title ?? '') . ' ' . ($company ?? '') . ' ' . ($description ?? '')));
    }

    public static function compute(string $text): int
    {
        $tokens = TextNormalizer::bigrams(TextNormalizer::normalize($text));
        if ($tokens === []) {
            return 0;
        }

        $vector = array_fill(0, 64, 0);
        foreach ($tokens as $token => $weight) {
            $hash = self::hash64((string) $token);
            for ($bit = 0; $bit < 64; $bit++) {
                if (($hash >> $bit) & 1) {
                    $vector[$bit] += $weight;
                } else {
                    $vector[$bit] -= $weight;
                }
            }
        }

        $fingerprint = 0;
        for ($bit = 0; $bit < 64; $bit++) {
            if ($vector[$bit] > 0) {
                $fingerprint |= (1 << $bit);
            }
        }
        return $fingerprint;
    }

    /** token → 64 位哈希（md5 原始字节前 8 字节，位模式保真）。 */
    private static function hash64(string $token): int
    {
        return unpack('J', substr(md5($token, true), 0, 8))[1];
    }

    /** 两个 64 位指纹的汉明距离。 */
    public static function hammingDistance(int $a, int $b): int
    {
        $x = $a ^ $b;
        $dist = 0;
        while ($x !== 0) {
            $dist++;
            $x &= $x - 1;   // 清除最低位的 1
        }
        return $dist;
    }

    /** 16 位十六进制字符串（LPAD(HEX(simhash),16,'0')）→ PHP int 位模式。 */
    public static function fromHex(string $hex): int
    {
        return unpack('J', hex2bin(str_pad($hex, 16, '0', STR_PAD_LEFT)))[1];
    }

    /** PHP int 位模式 → 可入 BIGINT UNSIGNED 的无符号十进制字符串。 */
    public static function toDb(int $fingerprint): string
    {
        return sprintf('%u', $fingerprint);
    }
}
