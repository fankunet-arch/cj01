<?php
declare(strict_types=1);

/** 会话（登录态承载：PHP 原生 Session，Cookie HttpOnly+SameSite=Lax）。 */
function zp_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== ''),
    ]);
    session_name('zpsess');
    session_start();
}

/** 当前管理员（['id','email','name']）或 null。 */
function zp_admin(): ?array
{
    zp_session_start();
    return $_SESSION['admin'] ?? null;
}

/** 管理后台门卫：未登录跳转 /c/cp/login。 */
function zp_require_admin(): array
{
    $admin = zp_admin();
    if ($admin === null) {
        header('Location: /c/cp/login');
        exit;
    }
    return $admin;
}

/** 超级管理员门卫：非超管一律拒绝。 */
function zp_require_super_admin(): array
{
    $admin = zp_require_admin();
    if ((int) ($admin['role'] ?? 0) !== 2) {
        http_response_code(403);
        exit('无权访问');
    }
    return $admin;
}

/** 一次性提示（POST 后重定向回列表页时用）。 */
function zp_flash_set(string $msg): void
{
    zp_session_start();
    $_SESSION['flash'] = $msg;
}

function zp_flash_get(): string
{
    zp_session_start();
    $msg = (string) ($_SESSION['flash'] ?? '');
    unset($_SESSION['flash']);
    return $msg;
}

/** CSRF 令牌（管理后台表单用）。 */
function zp_csrf_token(): string
{
    zp_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function zp_csrf_check(): void
{
    zp_session_start();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('CSRF 校验失败');
    }
}
