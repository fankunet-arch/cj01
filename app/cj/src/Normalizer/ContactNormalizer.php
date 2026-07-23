<?php

declare(strict_types=1);

namespace Cj\Normalizer;

/**
 * 电话/微信归一化（文档 §3.3 信号 A）。
 * 电话和微信是去重的黄金主键。
 */
final class ContactNormalizer
{
    /**
     * 电话归一化：去空格/连字符/括号，去国际区号前缀（+34 / 0034），
     * 提取纯数字末 9 位（西班牙号码 9 位），生成 phone_norm。
     */
    public static function phone(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        // 去国际前缀：0034xxxxxxxxx / 34xxxxxxxxx（+34 的 + 已被上一步去掉）
        if (str_starts_with($digits, '0034')) {
            $digits = substr($digits, 4);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '34')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) < 9) {
            return $digits !== '' ? $digits : null;   // 短于 9 位原样保留，弱信号
        }
        return substr($digits, -9);
    }

    /** 微信归一化：转小写，去空格。 */
    public static function wechat(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $w = mb_strtolower(trim($raw));
        $w = preg_replace('/\s+/u', '', $w) ?? $w;
        return $w !== '' ? $w : null;
    }

    /**
     * contact_key = phone_norm|wechat_norm（任一命中即视为潜在同源）。
     * 双空时返回 null（进入降级去重路径，文档 §3.4）。
     */
    public static function contactKey(?string $phoneNorm, ?string $wechatNorm): ?string
    {
        if ($phoneNorm === null && $wechatNorm === null) {
            return null;
        }
        return ($phoneNorm ?? '') . '|' . ($wechatNorm ?? '');
    }
}
