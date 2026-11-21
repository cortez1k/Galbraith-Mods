<?php
require_once __DIR__ . '/smtp_mailer.php';

// =====================================================================
// Registration
// =====================================================================

function isValidLogin($login) {
    return (bool)preg_match('/^[a-zA-Z0-9_]{3,20}$/', $login);
}

function loginTaken($login) {
    // Обычное "=" в SQLite регистрозависимое — "Admin" и "admin" считались
    // разными логинами, из-за чего можно было зарегистрировать почти
    // неотличимый дубль существующего никнейма (в том числе для имперсонации).
    $stmt = getDB()->prepare('SELECT id FROM users WHERE LOWER(login) = LOWER(?)');
    $stmt->execute([$login]);
    return (bool)$stmt->fetch();
}

function emailTaken($email) {
    $stmt = getDB()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return (bool)$stmt->fetch();
}

/** Registers a new user with email + password. Returns ['user_id' => ...] or ['error' => ...]. */
function registerWithEmail($login, $email, $password, $passwordConfirm, $consentGiven = false) {
    $login = trim($login);
    $email = trim(strtolower($email));

    if (!isValidLogin($login)) {
        return ['error' => 'Логин должен быть от 3 до 20 символов: латиница, цифры, подчёркивание'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Некорректный email'];
    }
    if (strlen($password) < 6) {
        return ['error' => 'Пароль должен быть не короче 6 символов'];
    }
    if ($password !== $passwordConfirm) {
        return ['error' => 'Пароли не совпадают'];
    }
    if (!$consentGiven) {
        return ['error' => 'Необходимо дать согласие на обработку персональных данных'];
    }
    if (loginTaken($login)) {
        return ['error' => 'Этот логин уже занят'];
    }
    if (emailTaken($email)) {
        return ['error' => 'Пользователь с таким email уже зарегистрирован'];
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO users (name, email, password, login, role, personal_data_consent_at, personal_data_consent_version) VALUES (?, ?, ?, ?, 'user', CURRENT_TIMESTAMP, '1.0')");
    $stmt->execute([$login, $email, password_hash($password, PASSWORD_DEFAULT), $login]);
    $userId = $db->lastInsertId();

    if (function_exists('attachReferralIfPending')) {
        attachReferralIfPending($userId);
    }

    sendVerificationEmail($userId);
    return ['user_id' => $userId];
}

// =====================================================================
// Email verification
// =====================================================================

function sendVerificationEmail($userId) {
    $db = getDB();

    // Защита от всплеска писем: если непогашенный токен создан меньше минуты назад,
    // не шлём новое письмо повторно. Быстрые повторные отправки почти одинаковых
    // писем — один из явных признаков "робота" для антиспам-фильтра Яндекса.
    $recent = $db->prepare("SELECT created_at FROM email_verifications WHERE user_id = ? AND used = 0 ORDER BY id DESC LIMIT 1");
    $recent->execute([$userId]);
    $last = $recent->fetch();
    if ($last && (time() - strtotime($last['created_at'])) < 60) {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)')
        ->execute([$userId, $token, date('Y-m-d H:i:s', time() + 24 * 3600)]);

    $stmt = $db->prepare('SELECT email, name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u) return;

    $link = rtrim(SITE_URL, '/') . '/verify-email/' . $token;
    $body = "<p>Здравствуйте, " . htmlspecialchars($u['name']) . "!</p>"
        . "<p>Подтвердите ваш email на Galbraith, перейдя по ссылке (действительна 24 часа):</p>"
        . "<p><a href=\"$link\">$link</a></p>";
    sendSystemEmail($u['email'], 'Подтвердите ваш email на Galbraith', $body);
}

/** Returns ['ok'=>true] or ['error'=>...]. */
function verifyEmailToken($token) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM email_verifications WHERE token = ? AND used = 0');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) return ['error' => 'Ссылка недействительна или уже использована'];
    if (strtotime($row['expires_at']) < time()) return ['error' => 'Срок действия ссылки истёк. Запросите новое письмо.'];

    $db->prepare('UPDATE email_verifications SET used = 1 WHERE id = ?')->execute([$row['id']]);
    $db->prepare('UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$row['user_id']]);
    return ['ok' => true, 'user_id' => $row['user_id']];
}

function isEmailVerified($user) {
    return !empty($user['email_verified_at']);
}

// =====================================================================
// Login with email/login + password
// =====================================================================

/** Returns ['user_id'=>...] or ['error'=>...]. */
function loginWithEmail($identifier, $password) {
    $identifier = trim($identifier);
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? OR login = ?');
    $stmt->execute([strtolower($identifier), $identifier]);
    $user = $stmt->fetch();

    if (!$user || !$user['password'] || !password_verify($password, $user['password'])) {
        return ['error' => 'Неверный email/логин или пароль'];
    }
    if (in_array($user['role'], ['admin', 'moderator'], true)) {
        return ['error' => 'Используйте страницу /admin/login для входа в панель управления'];
    }
    if (!isEmailVerified($user)) {
        return ['error' => 'Подтвердите email, перейдя по ссылке в письме', 'unverified_user_id' => $user['id']];
    }
    if (isBanned($user)) {
        return ['error' => 'Ваш аккаунт заблокирован' . ($user['ban_reason'] ? ': ' . $user['ban_reason'] : '')];
    }
    return ['user_id' => $user['id']];
}

// =====================================================================
// Password reset
// =====================================================================

function requestPasswordReset($email) {
    $email = trim(strtolower($email));
    $stmt = getDB()->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    // Always return success-shaped response to avoid leaking which emails are registered.
    if (!$user) return ['ok' => true];

    $token = bin2hex(random_bytes(32));
    getDB()->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
        ->execute([$user['id'], $token, date('Y-m-d H:i:s', time() + 3600)]);

    $link = rtrim(SITE_URL, '/') . '/reset-password/' . $token;
    $body = "<p>Здравствуйте, " . htmlspecialchars($user['name']) . "!</p>"
        . "<p>Вы запросили восстановление пароля на Galbraith. Ссылка действительна 1 час:</p>"
        . "<p><a href=\"$link\">$link</a></p>"
        . "<p>Если это были не вы — просто проигнорируйте это письмо.</p>";
    sendSystemEmail($email, 'Восстановление пароля на Galbraith', $body);
    return ['ok' => true];
}

/** Returns ['ok'=>true] or ['error'=>...]. */
function resetPasswordWithToken($token, $newPassword, $newPasswordConfirm) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) return ['error' => 'Ссылка недействительна или уже использована'];
    if (strtotime($row['expires_at']) < time()) return ['error' => 'Срок действия ссылки истёк. Запросите новую.'];
    if (strlen($newPassword) < 6) return ['error' => 'Пароль должен быть не короче 6 символов'];
    if ($newPassword !== $newPasswordConfirm) return ['error' => 'Пароли не совпадают'];

    $db->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')->execute([$row['id']]);
    $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $row['user_id']]);
    return ['ok' => true];
}

// =====================================================================
// Linking email/password to an OAuth-only account
// =====================================================================

function linkEmailPassword($userId, $email, $password, $passwordConfirm) {
    $email = trim(strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => 'Некорректный email'];
    if (strlen($password) < 6) return ['error' => 'Пароль должен быть не короче 6 символов'];
    if ($password !== $passwordConfirm) return ['error' => 'Пароли не совпадают'];

    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) return ['error' => 'Этот email уже используется другим аккаунтом'];

    $db->prepare('UPDATE users SET email = ?, password = ?, email_verified_at = NULL WHERE id = ?')
        ->execute([$email, password_hash($password, PASSWORD_DEFAULT), $userId]);
    sendVerificationEmail($userId);
    return ['ok' => true];
}
