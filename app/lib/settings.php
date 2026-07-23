<?php
declare(strict_types=1);

/**
 * 参数表读取（zhaopin_settings），单请求内缓存。
 * 机密永远不放这里（放 app/config/config.php）。
 */
function zp_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $rows = zp_db()->query('SELECT skey, svalue FROM ' . zp_table('settings'))->fetchAll();
        foreach ($rows as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache[$key] ?? $default;
}

function zp_setting_int(string $key, int $default): int
{
    $v = zp_setting($key);
    return is_numeric($v) ? (int) $v : $default;
}

/** 字段名混淆映射（P0 静态版）：逻辑字段名 → 表单 name。 */
function zp_field_map(): array
{
    $map = json_decode(zp_setting('field_name_map', '') ?? '', true);
    $defaults = [
        'type' => 'f_01', 'content' => 'f_02', 'contact_name' => 'f_03',
        'phone' => 'f_04', 'wechat' => 'f_05', 'region_id' => 'f_06',
        'category_id' => 'f_07', 'new_category' => 'f_08', 'captcha' => 'f_09',
    ];
    return is_array($map) ? array_merge($defaults, $map) : $defaults;
}
