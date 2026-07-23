<?php
declare(strict_types=1);

/**
 * JSON 输出：动作/取号类接口统一走这里返回给前端原生 JS。
 */
function zp_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 尚未实现的动作接口占位（骨架期用，P0 实装后逐个替换）。
 */
function zp_json_todo(string $feature): void
{
    zp_json(['ok' => false, 'error' => 'not_implemented', 'feature' => $feature], 501);
}
