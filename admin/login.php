<?php
if (checkAdminSession()) redirect('/admin');
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в админку — Galbraith</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0D0D0F; color: #F0F0F5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-box { background: #16161A; border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 40px; width: 100%; max-width: 360px; }
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 24px; text-align: center; }
        .input { width: 100%; background: #1E1E24; border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 12px 14px; color: #F0F0F5; font-size: 14px; margin-bottom: 12px; outline: none; }
        .input:focus { border-color: #E81C5A; }
        .btn { width: 100%; padding: 12px; background: #E81C5A; color: #fff; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
        .btn:hover { background: #FF5C8A; }
        .error { color: #FF3B30; font-size: 13px; margin-bottom: 12px; }
        .hint { color: #8E8E93; font-size: 12px; margin-top: 16px; text-align: center; }
        .hint a { color: #FF5C8A; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🔒 Galbraith Admin</h1>
        <?php if (!empty($loginError)): ?><div class="error"><?= e($loginError) ?></div><?php endif; ?>
        <form method="POST" action="/admin/login">
            <input type="hidden" name="action" value="login">
            <input type="email" name="email" class="input" placeholder="Email" required value="admin@galbraith-yt.ru">
            <input type="password" name="password" class="input" placeholder="Пароль" required>
            <input type="text" name="totp_code" class="input" placeholder="Код 2FA (если включен)" maxlength="6" inputmode="numeric" pattern="[0-9]*">
            <button type="submit" class="btn">Войти</button>
        </form>
        <p class="hint">Сессия привязана к IP и браузеру.<br>Бездействие > 15 мин — автовыход.</p>
    </div>
</body>
</html>
