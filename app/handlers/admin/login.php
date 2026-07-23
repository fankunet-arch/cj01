<?php
declare(strict_types=1);

/**
 * 管理员登录（/c/cp/login）：Google OAuth + 白名单，零密码。
 * 本地调试：config['dev']['fake_admin_email'] 非空时提供免 OAuth 直登（生产必须留空）。
 */
zp_session_start();
if (zp_admin() !== null) {
    header('Location: /c/cp/');
    exit;
}

$redirectUri = zp_base_url() . '/c/cp/login';
$err = '';

// 本地调试直登（仅当 config.dev.fake_admin_email 明确配置时可用）
$devEmail = (string) zp_config('dev.fake_admin_email', '');
if ($devEmail !== '' && ($_GET['dev'] ?? '') === '1') {
    if (zp_admin_login(['sub' => 'dev', 'email' => $devEmail, 'name' => '本地调试'])) {
        header('Location: /c/cp/');
        exit;
    }
    $err = '调试邮箱不在白名单（zhaopin_admins）内';
}

// Google 回调
if (isset($_GET['code'])) {
    if (!hash_equals($_SESSION['oauth_state'] ?? '', (string) ($_GET['state'] ?? ''))) {
        $err = '登录状态校验失败，请重试';
    } else {
        try {
            $identity = zp_google_exchange_code((string) $_GET['code'], $redirectUri);
            if (zp_admin_login($identity)) {
                header('Location: /c/cp/');
                exit;
            }
            $err = '该 Google 账号不在管理员白名单内';
        } catch (Throwable $e) {
            $err = '登录失败：' . $e->getMessage();
        }
    }
}

$oauthReady = (string) zp_config('google_oauth.client_id', '') !== '';
zp_render('admin/login', [
    'title' => '管理员登录', 'page' => 'admin',
    'authUrl' => $oauthReady ? zp_google_auth_url($redirectUri) : '',
    'devEmail' => $devEmail,
    'err' => $err,
]);
