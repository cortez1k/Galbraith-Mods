<?php
$pageTitle = 'Вход';
if (!empty($_SESSION['user_id'])) { redirect('/profile'); }
$authError = $_SESSION['auth_error'] ?? null;
unset($_SESSION['auth_error']);
$showResend = !empty($_SESSION['unverified_user_id']);
$resetSuccess = !empty($_SESSION['reset_success']); unset($_SESSION['reset_success']);
include __DIR__ . '/../templates/header.php';
?>

<div style="max-width:420px;margin:0 auto;padding:40px 16px;text-align:center;">
    <div class="logo-icon" style="width:56px;height:56px;font-size:24px;margin:0 auto 20px;">G</div>
    <h1 style="font-size:24px;font-weight:700;margin-bottom:20px;">Вход на Galbraith</h1>

    <?php if ($resetSuccess): ?>
        <div style="background:rgba(52,199,89,0.1);border:1px solid rgba(52,199,89,0.3);color:#34C759;border-radius:var(--g-radius-sm);padding:12px;margin-bottom:16px;font-size:14px;">Пароль изменён, теперь можно войти</div>
    <?php endif; ?>
    <?php if ($authError): ?>
        <div style="background:rgba(255,80,80,0.1);border:1px solid rgba(255,80,80,0.3);color:#ff5050;border-radius:var(--g-radius-sm);padding:12px;margin-bottom:16px;font-size:14px;">
            <?= e($authError) ?>
            <?php if ($showResend): ?>
                <form method="POST" action="/resend-verification" style="margin-top:10px;">
                    <button type="submit" class="btn btn-secondary btn-sm">Отправить письмо ещё раз</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/login-email" style="display:grid;gap:10px;text-align:left;margin-bottom:20px;">
        <input type="text" name="identifier" class="input" placeholder="Email или логин" required>
        <input type="password" name="password" class="input" placeholder="Пароль" required>
        <button type="submit" class="btn btn-primary" style="width:100%;">Войти</button>
        <a href="/forgot-password" style="font-size:13px;color:var(--g-text-secondary);text-align:right;">Забыли пароль?</a>
    </form>
    <p style="font-size:14px;color:var(--g-text-secondary);margin-bottom:20px;">Нет аккаунта? <a href="/register" style="color:var(--g-accent-light);">Зарегистрироваться</a></p>

    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;color:var(--g-text-secondary);font-size:13px;">
        <div style="flex:1;height:1px;background:var(--g-border);"></div>или<div style="flex:1;height:1px;background:var(--g-border);"></div>
    </div>

    <div style="display:grid;gap:12px;">
        <a href="/auth/google" class="btn btn-secondary" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;">
            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.47c-.28 1.5-1.13 2.77-2.41 3.62v3h3.9c2.28-2.1 3.56-5.2 3.56-8.81z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.93-2.92l-3.9-3c-1.08.72-2.46 1.15-4.03 1.15-3.1 0-5.73-2.1-6.67-4.92H1.3v3.09C3.26 21.3 7.3 24 12 24z"/><path fill="#FBBC05" d="M5.33 14.31A7.2 7.2 0 0 1 4.96 12c0-.8.14-1.58.37-2.31V6.6H1.3A11.98 11.98 0 0 0 0 12c0 1.93.46 3.76 1.3 5.4l4.03-3.09z"/><path fill="#EA4335" d="M12 4.77c1.76 0 3.35.61 4.6 1.8l3.44-3.44C17.94 1.19 15.24 0 12 0 7.3 0 3.26 2.7 1.3 6.6l4.03 3.09C6.27 6.87 8.9 4.77 12 4.77z"/></svg>
            Войти через Google
        </a>
        <a href="/auth/vk" class="btn btn-secondary" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;">
            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#0077FF" d="M2 8.4c0-3.36 0-5.04.65-6.3A6 6 0 0 1 5.3.65C6.56 0 8.24 0 11.6 0h.8c3.36 0 5.04 0 6.3.65a6 6 0 0 1 2.62 2.62c.68 1.3.68 2.99.68 6.35v.4c0 3.36 0 5.04-.65 6.3a6 6 0 0 1-2.62 2.62c-1.3.68-2.99.68-6.35.68h-.4c-3.36 0-5.04 0-6.3-.65a6 6 0 0 1-2.62-2.62C2 15.05 2 13.36 2 10v-1.6z"/><path fill="#fff" d="M12.8 17.2c-5.4 0-8.5-3.7-8.6-9.85h2.75c.07 4.5 2.03 6.4 3.6 6.8V7.35h2.6v3.9c1.5-.16 3.1-1.93 3.65-3.9h2.6c-.42 2.44-2.2 4.2-3.44 4.94 1.24.6 3.27 2.14 4.03 4.9h-2.86c-.6-1.87-2.06-3.31-4-3.5v3.5h-.32z"/></svg>
            Войти через VK
        </a>
    </div>

    <a href="/" style="display:inline-block;margin-top:24px;font-size:14px;color:var(--g-text-secondary);">← На главную</a>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
