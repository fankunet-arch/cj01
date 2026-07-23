<?php
declare(strict_types=1);

/**
 * 服务端渲染：模板一律在 app/views/（URL 不可达），由处理器调用输出完整 HTML。
 */
function zp_render(string $view, array $data = []): void
{
    $file = ZP_APP_PATH . '/views/' . $view . '.php';
    if (!is_file($file)) {
        error_log('[zhaopin] view missing: ' . $view);
        http_response_code(500);
        exit('服务暂时不可用，请稍后再试');
    }
    extract($data, EXTR_SKIP);
    require ZP_APP_PATH . '/views/layout/header.php';
    require $file;
    require ZP_APP_PATH . '/views/layout/footer.php';
}

/**
 * HTML 转义速记。
 */
function zp_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
