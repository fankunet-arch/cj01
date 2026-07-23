<?php
declare(strict_types=1);

/** 当前 UTC 时间（库内统一存 UTC，见 docs/03 §1）。 */
function zp_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** 客户端 IP（字符串）。 */
function zp_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** IP → VARBINARY(16)（INET6 兼容 IPv4/IPv6）。 */
function zp_ip_bin(?string $ip = null): string
{
    $bin = @inet_pton($ip ?? zp_client_ip());
    return $bin === false ? "\0\0\0\0" : $bin;
}

/** 对外随机码：不可枚举，去掉易混字符（0O1lI）。 */
function zp_public_code(int $len = 10): string
{
    $alphabet = '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/** 电话规整：仅留数字与前导 +，判重用。 */
function zp_phone_norm(string $phone): string
{
    $norm = preg_replace('/[^0-9+]/', '', $phone) ?? '';
    return substr($norm, 0, 20);
}

/** 内容规整哈希：去空白统一小写后 SHA-256，同内容判重用。 */
function zp_content_hash(string $content): string
{
    return hash('sha256', mb_strtolower(preg_replace('/\s+/u', '', $content) ?? ''));
}

/** 电话遮号展示："612345678" → "612 ·· ··"。 */
function zp_phone_mask(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    return ($digits !== '' ? substr($digits, 0, 3) : '···') . ' ·· ··';
}

/** UTC 时间 → 马德里时区 DateTime。 */
function zp_madrid(string $utc): DateTime
{
    $dt = new DateTime($utc, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Europe/Madrid'));
    return $dt;
}

/** 相对时间文案（按马德里时区）："刚刚 / N分钟前 / N小时前 / 昨天 / N天前"。 */
function zp_time_ago(string $utc): string
{
    $diff = time() - (new DateTime($utc, new DateTimeZone('UTC')))->getTimestamp();
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return intdiv($diff, 60) . '分钟前';
    if ($diff < 86400) return intdiv($diff, 3600) . '小时前';
    $days = intdiv($diff, 86400);
    if ($days === 1) return '昨天';
    return $days . '天前';
}

/** 马德里时区的"今日/昨天/更早"分组标签。 */
function zp_day_bucket(string $utc): string
{
    $tz = new DateTimeZone('Europe/Madrid');
    $d = zp_madrid($utc)->format('Y-m-d');
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    $yesterday = (new DateTime('yesterday', $tz))->format('Y-m-d');
    if ($d === $today) return '今日';
    if ($d === $yesterday) return '昨天';
    return '更早';
}

/** 站点根地址（去掉误配的末尾斜杠，防止拼出 //path 导致 OAuth redirect_uri 不匹配）。 */
function zp_base_url(): string
{
    return rtrim((string) zp_config('site.base_url', ''), '/');
}

/** 站内回跳地址校验：只允许本站相对路径，防开放重定向。 */
function zp_safe_back(?string $url, string $fallback): string
{
    $url = (string) $url;
    if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//') || str_contains($url, "\\")) {
        return $fallback;
    }
    return $url;
}
