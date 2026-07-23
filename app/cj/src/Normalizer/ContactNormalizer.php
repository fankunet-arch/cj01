<?php

declare(strict_types=1);

namespace Cj\Normalizer;

/**
 * 电话/微信归一化（文档 §3.3 信号 A）。
 * 电话和微信是去重的黄金主键。
 *
 * 两套电话归一化，用途不同：
 *  - phone()     采集库内跨站去重用：取末 9 位，抹平 +34/0034/裸号差异，跨站命中率高。
 *  - phoneMain() 与 zhaopin 主库交互用（三级去重比对 + 导入写入 zhaopin_posts.phone_norm）：
 *                必须与主站 app/lib/util.php 的 zp_phone_norm() 逐字节一致，否则与主库对不上。
 */
final class ContactNormalizer
{
    /**
     * 主库兼容电话归一化：仅保留数字与 +，截断 20 位。
     * ⚠ 必须与主站 zp_phone_norm() 保持一致：
     *   preg_replace('/[^0-9+]/', '', $phone) 再 substr(0, 20)。
     */
    public static function phoneMain(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }
        return substr(preg_replace('/[^0-9+]/', '', $raw) ?? '', 0, 20);
    }

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
