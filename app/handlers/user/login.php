<?php
declare(strict_types=1);

/**
 * 用户登录（Google OAuth，零成本零密码，docs/01 §4.3）。
 * 回跳：入口带 ?back=<站内路径> 时存入 Session，登录成功后跳回原页面
 *（OAuth 往返靠 Session 保持；back 经 zp_safe_back 校验防开放重定向）。
 * 本地调试：config['dev']['fake_user_name'] 非空时 ?dev=1 免 OAuth 直登（生产留空）。
 */
zp_session_start();

// 记录回跳地址（点击登录按钮时所在的页面）
if (isset($_GET['back'])) {
    $_SESSION['login_back'] = zp_safe_back((string) $_GET['back'], '/user/my_posts');
}
$finish = function (): void {
    $back = zp_safe_back($_SESSION['login_back'] ?? null, '/user/my_posts');
    unset($_SESSION['login_back']);
    header('Location: ' . $back);
    exit;
};

if (zp_user() !== null) {
    $finish();
}

$redirectUri = zp_base_url() . '/user/login';
$err = '';

// 本地调试直登
$devName = (string) zp_config('dev.fake_user_name', '');
if ($devName !== '' && ($_GET['dev'] ?? '') === '1') {
    if (zp_user_login(['sub' => 'dev-user-1', 'email' => '', 'name' => $devName]) !== null) {
        $finish();
    }
    $err = '该调试账号已被封禁';
}

// Google 回调
if (isset($_GET['code'])) {
    if (!hash_equals($_SESSION['oauth_state'] ?? '', (string) ($_GET['state'] ?? ''))) {
        $err = '登录状态校验失败，请重试';
    } else {
        try {
            $identity = zp_google_exchange_code((string) $_GET['code'], $redirectUri);
            if (zp_user_login($identity) !== null) {
                $finish();
            }
            $err = '该账号已被封禁';
        } catch (Throwable $e) {
            $err = '登录失败：' . $e->getMessage();
        }
    }
}

$oauthReady = (string) zp_config('google_oauth.client_id', '') !== '';
zp_render('user/login', [
    'title' => '登录', 'page' => 'user',
    'authUrl' => $oauthReady ? zp_google_auth_url($redirectUri) : '',
    'devName' => $devName,
    'err' => $err,
]);
