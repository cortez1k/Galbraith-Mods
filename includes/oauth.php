<?php
require_once __DIR__ . '/functions.php';

/**
 * OAuth login: Google (standard OAuth 2.0) and VK ID (OAuth 2.1 + PKCE).
 *
 * Setup:
 *  - Google: https://console.cloud.google.com/apis/credentials
 *            Authorized redirect URI: SITE_URL . '/auth/google/callback'
 *            Fill GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET in config.php
 *  - VK ID:  https://id.vk.com -> Мои приложения -> Создать
 *            Доверенный redirect URI: SITE_URL . '/auth/vk/callback'
 *            Fill VK_CLIENT_ID (App ID) / VK_CLIENT_SECRET in config.php
 *            Note: VK ID's exact endpoint contract has changed more than once;
 *            if login fails, check https://id.vk.com developer docs for the
 *            current token endpoint before assuming the site code is wrong.
 */

function oauthRedirectUri($provider) {
    return SITE_URL . '/auth/' . $provider . '/callback';
}

// ---------------------------------------------------------------------
// Google
// ---------------------------------------------------------------------

function googleLoginUrl() {
    if (!GOOGLE_CLIENT_ID) {
        return null;
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state_google'] = $state;
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => oauthRedirectUri('google'),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function handleGoogleCallback() {
    if (empty($_GET['code'])) {
        return ['error' => $_GET['error'] ?? 'Авторизация отменена'];
    }
    $state = $_GET['state'] ?? '';
    if (!$state || $state !== ($_SESSION['oauth_state_google'] ?? null)) {
        return ['error' => 'Неверный state, попробуйте войти ещё раз'];
    }
    unset($_SESSION['oauth_state_google']);

    $tokenResp = httpPostForm('https://oauth2.googleapis.com/token', [
        'code' => $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => oauthRedirectUri('google'),
        'grant_type' => 'authorization_code',
    ]);
    $token = json_decode($tokenResp, true);
    if (empty($token['access_token'])) {
        return ['error' => 'Не удалось получить токен Google'];
    }

    $infoResp = httpGet('https://www.googleapis.com/oauth2/v3/userinfo', [
        'Authorization: Bearer ' . $token['access_token'],
    ]);
    $info = json_decode($infoResp, true);
    if (empty($info['sub'])) {
        return ['error' => 'Не удалось получить данные профиля Google'];
    }

    $userId = findOrCreateOAuthUser('google', $info['sub'], [
        'email' => $info['email'] ?? null,
        'name' => $info['name'] ?? ($info['given_name'] ?? 'Пользователь'),
        'avatar' => $info['picture'] ?? null,
    ]);
    return ['user_id' => $userId];
}

// ---------------------------------------------------------------------
// VK ID (OAuth 2.1 + PKCE)
// ---------------------------------------------------------------------

function vkLoginUrl() {
    if (!VK_CLIENT_ID) {
        return null;
    }
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_vk_verifier'] = $verifier;
    $_SESSION['oauth_state_vk'] = $state;

    $params = [
        'client_id' => VK_CLIENT_ID,
        'redirect_uri' => oauthRedirectUri('vk'),
        'response_type' => 'code',
        'scope' => 'email',
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ];
    return 'https://id.vk.com/authorize?' . http_build_query($params);
}

function handleVkCallback() {
    if (empty($_GET['code'])) {
        return ['error' => $_GET['error'] ?? 'Авторизация отменена'];
    }
    $state = $_GET['state'] ?? '';
    if (!$state || $state !== ($_SESSION['oauth_state_vk'] ?? null)) {
        return ['error' => 'Неверный state, попробуйте войти ещё раз'];
    }
    $verifier = $_SESSION['oauth_vk_verifier'] ?? '';
    unset($_SESSION['oauth_state_vk'], $_SESSION['oauth_vk_verifier']);
    if (!$verifier) {
        return ['error' => 'Истекла сессия авторизации VK, попробуйте снова'];
    }

    $params = [
        'grant_type' => 'authorization_code',
        'code' => $_GET['code'],
        'code_verifier' => $verifier,
        'client_id' => VK_CLIENT_ID,
        'redirect_uri' => oauthRedirectUri('vk'),
        'state' => $state,
    ];
    if (!empty($_GET['device_id'])) {
        $params['device_id'] = $_GET['device_id'];
    }
    if (VK_CLIENT_SECRET) {
        $params['client_secret'] = VK_CLIENT_SECRET;
    }
    $tokenResp = httpPostForm('https://id.vk.com/oauth2/auth', $params);
    $token = json_decode($tokenResp, true);
    if (empty($token['access_token'])) {
        return ['error' => 'Не удалось получить токен VK ID'];
    }

    $infoResp = httpPostForm('https://id.vk.com/oauth2/user_info', [
        'access_token' => $token['access_token'],
        'client_id' => VK_CLIENT_ID,
    ]);
    $info = json_decode($infoResp, true);
    $profile = $info['user'] ?? $info;
    $vkUserId = $profile['user_id'] ?? $token['user_id'] ?? null;
    if (!$vkUserId) {
        return ['error' => 'Не удалось получить данные профиля VK'];
    }

    $name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
    $userId = findOrCreateOAuthUser('vk', (string)$vkUserId, [
        'email' => $token['email'] ?? $profile['email'] ?? null,
        'name' => $name ?: 'Пользователь ВК',
        'avatar' => $profile['avatar'] ?? null,
    ]);
    return ['user_id' => $userId];
}

// ---------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------

function findOrCreateOAuthUser($provider, $providerId, $profile) {
    $db = getDB();
    $col = $provider === 'google' ? 'google_id' : 'vk_id';

    $stmt = $db->prepare("SELECT * FROM users WHERE $col = ?");
    $stmt->execute([$providerId]);
    $user = $stmt->fetch();
    if ($user) {
        return $user['id'];
    }

    $email = $profile['email'] ?: ($providerId . '@' . $provider . '.placeholder');
    // If an account with this email already exists (e.g. created by another provider), link it.
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    if ($existing) {
        $db->prepare("UPDATE users SET $col = ? WHERE id = ?")->execute([$providerId, $existing['id']]);
        return $existing['id'];
    }

    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, $col, avatar) VALUES (?, ?, ?, 'user', ?, ?)");
    $stmt->execute([$profile['name'] ?: 'Пользователь', $email, $randomPassword, $providerId, $profile['avatar'] ?? null]);
    $newUserId = $db->lastInsertId();
    if (function_exists('attachReferralIfPending')) {
        attachReferralIfPending($newUserId);
    }
    return $newUserId;
}

function httpPostForm($url, $params) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) error_log('httpPostForm failed: ' . curl_error($ch) . ' url=' . $url);
    curl_close($ch);
    return $resp ?: '{}';
}

function httpGet($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) error_log('httpGet failed: ' . curl_error($ch) . ' url=' . $url);
    curl_close($ch);
    return $resp ?: '{}';
}
