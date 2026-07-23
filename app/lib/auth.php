<?php
declare(strict_types=1);

/**
 * 管理员登录：Google OAuth + 邮箱白名单（zhaopin_admins），全站零密码。
 * docs/01 §4.3：Google 验证通过且邮箱在白名单内，双重满足才授权。
 */

/** Google 授权跳转 URL（管理员登录用，redirect 回 /c/cp/login）。 */
function zp_google_auth_url(string $redirectUri): string
{
    zp_session_start();
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => (string) zp_config('google_oauth.client_id'),
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);
}

/**
 * 用授权码换取用户信息。返回 ['sub','email','name'] 或抛异常。
 * id_token 直接从 Google token 端点经 TLS 获取，无需再验签。
 */
function zp_google_exchange_code(string $code, string $redirectUri): array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => (string) zp_config('google_oauth.client_id'),
            'client_secret' => (string) zp_config('google_oauth.client_secret'),
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = is_string($resp) ? json_decode($resp, true) : null;
    if (!is_array($data) || empty($data['id_token'])) {
        throw new RuntimeException('Google 令牌交换失败');
    }
    $parts = explode('.', (string) $data['id_token']);
    $payload = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')) ?: '', true);
    if (!is_array($payload) || empty($payload['sub'])) {
        throw new RuntimeException('id_token 解析失败');
    }
    return [
        'sub'   => (string) $payload['sub'],
        'email' => (string) ($payload['email'] ?? ''),
        'name'  => (string) ($payload['name'] ?? ''),
    ];
}

/**
 * 普通用户登录：按 google_sub 找或建（数据最小化：不存邮箱，见 docs/03 §3.5）。
 * 返回用户数组；被封禁（status=0）返回 null。
 */
function zp_user_login(array $identity): ?array
{
    $db = zp_db();
    $U = zp_table('users');
    $stmt = $db->prepare("SELECT * FROM $U WHERE google_sub = ?");
    $stmt->execute([$identity['sub']]);
    $user = $stmt->fetch();
    if ($user === false) {
        $code = zp_public_code(8);
        $db->prepare("INSERT INTO $U (public_code, google_sub, display_name, status, created_at, last_login_at) VALUES (?, ?, ?, 1, ?, ?)")
           ->execute([$code, $identity['sub'], $identity['name'] ?: null, zp_now(), zp_now()]);
        $stmt->execute([$identity['sub']]);
        $user = $stmt->fetch();
    } else {
        if ((int) $user['status'] !== 1) {
            return null;
        }
        $db->prepare("UPDATE $U SET display_name = ?, last_login_at = ? WHERE id = ?")
           ->execute([$identity['name'] ?: $user['display_name'], zp_now(), $user['id']]);
    }
    zp_session_start();
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int) $user['id'], 'code' => $user['public_code'], 'name' => (string) ($identity['name'] ?: $user['display_name'] ?: '用户')];
    return $_SESSION['user'];
}

/** 当前登录用户（['id','code','name']）或 null。 */
function zp_user(): ?array
{
    zp_session_start();
    return $_SESSION['user'] ?? null;
}

/** 用户后台门卫：未登录跳转登录页（携带原地址，登录后跳回）。 */
function zp_require_user(): array
{
    $user = zp_user();
    if ($user === null) {
        header('Location: /user/login?back=' . urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/user/my_posts')));
        exit;
    }
    return $user;
}

/**
 * 用 Google 身份尝试建立管理员会话：邮箱命中白名单（status=1）才放行。
 * 成功返回 true 并回填 google_sub / display_name。
 */
function zp_admin_login(array $identity): bool
{
    $db = zp_db();
    $stmt = $db->prepare('SELECT id, email, role FROM ' . zp_table('admins') . ' WHERE email = ? AND status = 1');
    $stmt->execute([$identity['email']]);
    $row = $stmt->fetch();
    if ($row === false) {
        return false;
    }
    $db->prepare('UPDATE ' . zp_table('admins') . ' SET google_sub = ?, display_name = ? WHERE id = ?')
       ->execute([$identity['sub'] ?: null, $identity['name'] ?: null, $row['id']]);
    zp_session_start();
    session_regenerate_id(true);
    $_SESSION['admin'] = [
        'id' => (int) $row['id'], 'email' => $row['email'],
        'name' => $identity['name'], 'role' => (int) ($row['role'] ?? 1),
    ];
    return true;
}

/** 当前管理员是否超级管理员（role=2）。 */
function zp_admin_is_super(): bool
{
    return (int) (zp_admin()['role'] ?? 0) === 2;
}

/** 当前管理员是否有权管理置顶券：超管恒可；普管取决于开关设置。 */
function zp_admin_can_coupon(): bool
{
    if (zp_admin() === null) {
        return false;
    }
    return zp_admin_is_super() || zp_setting('normal_admin_can_coupon', '0') === '1';
}
