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
     * 从正文里挖电话（目标站多数帖子没有独立联系方式字段，号码直接写在正文，
     * 如「电话微信同号685093496！」「…电话688002153」）。
     * 抓不到联系方式 → contact_key 为空 → confidence=low → import_ready=0，
     * 帖子永远进不了主库，所以这个兜底是整条链路能否出数的关键。
     *
     * 只认西班牙号码格式：9 位、首位 6/7/8/9，可带 +34 / 0034 前缀，
     * 中间允许空格 . - ( ) 分隔。前后必须不是数字，避免从长数字串里截 9 位。
     * 返回原文写法（保留 +34），归一化交给 phone()/phoneMain()。
     */
    public static function phoneFromText(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $re = '/(?<![0-9])(?:(?:\+|00)\s?34[\s.\-]?)?([679][\s.\-]?(?:[0-9][\s.\-]?){8})(?![0-9])/u';
        if (!preg_match_all($re, $text, $m, PREG_SET_ORDER)) {
            return null;
        }
        foreach ($m as $hit) {
            $digits = preg_replace('/\D+/', '', $hit[1]) ?? '';
            if (strlen($digits) !== 9) {
                continue;
            }
            // 明显不是电话的整串（如 111111111 / 123456789）跳过
            if (preg_match('/^(\d)\1{8}$/', $digits) || $digits === '123456789') {
                continue;
            }
            return trim($hit[0]);
        }
        return null;
    }

    /**
     * 从正文里挖微信号：「微信 maoge4349」「wx:abc_123」「V信：xxx」等。
     * 只取带标记词的，避免把正文里随便一个英文词当成微信号。
     */
    public static function wechatFromText(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $re = '/(?:微信|微信号|威信|薇信|weixin|wechat|wx|vx|v信)\s*(?:同号|号码|号)?\s*[:：=]?\s*'
            . '([A-Za-z][A-Za-z0-9_-]{4,29})/iu';
        if (!preg_match($re, $text, $m)) {
            return null;
        }
        $id = trim($m[1]);
        // 「微信同号」后面跟的是电话，不是微信号；这类由 phoneFromText 处理
        return preg_match('/^\d+$/', $id) ? null : $id;
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
