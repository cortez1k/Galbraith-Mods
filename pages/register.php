<?php
$pageTitle = 'Регистрация';
if (!empty($_SESSION['user_id'])) { redirect('/profile'); }
$error = $_SESSION['register_error'] ?? null; unset($_SESSION['register_error']);
$success = !empty($_SESSION['register_success']); unset($_SESSION['register_success']);
include __DIR__ . '/../templates/header.php';
?>

<div style="max-width:420px;margin:0 auto;padding:40px 16px;">
    <div class="logo-icon" style="width:56px;height:56px;font-size:24px;margin:0 auto 20px;">G</div>
    <h1 style="font-size:24px;font-weight:700;margin-bottom:8px;text-align:center;">Регистрация</h1>

    <?php if ($success): ?>
        <div style="background:rgba(52,199,89,0.1);border:1px solid rgba(52,199,89,0.3);color:#34C759;border-radius:var(--g-radius-sm);padding:16px;margin-bottom:16px;font-size:14px;text-align:center;">
            ✅ Регистрация почти завершена! Мы отправили письмо со ссылкой для подтверждения на указанный email — перейдите по ней, чтобы активировать аккаунт (ссылка действительна 24 часа).
        </div>
        <a href="/login" class="btn btn-primary" style="width:100%;">Перейти ко входу</a>
    <?php else: ?>
        <?php if ($error): ?>
            <div style="background:rgba(255,80,80,0.1);border:1px solid rgba(255,80,80,0.3);color:#ff5050;border-radius:var(--g-radius-sm);padding:12px;margin-bottom:16px;font-size:14px;"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="/register" style="display:grid;gap:14px;">
            <?= csrfField() ?>
            <div>
                <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Логин (3–20 символов: латиница, цифры, _)</label>
                <input type="text" name="login" class="input" required pattern="[a-zA-Z0-9_]{3,20}" value="<?= e($_POST['login'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Email</label>
                <input type="email" name="email" class="input" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Пароль</label>
                <input type="password" name="password" class="input" required minlength="6">
            </div>
            <div>
                <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Повтор пароля</label>
                <input type="password" name="password_confirm" class="input" required minlength="6">
            </div>
            <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px;color:var(--g-text-secondary);line-height:1.5;">
                <input type="checkbox" name="personal_data_consent" value="1" required style="margin-top:3px;">
                <span>Я согласен на обработку моих персональных данных в соответствии с <a href="/personal-data-consent" target="_blank" style="color:var(--g-accent-light);">Согласием</a> и <a href="/privacy-policy" target="_blank" style="color:var(--g-accent-light);">Политикой конфиденциальности</a>.</span>
            </label>
            <button type="submit" class="btn btn-primary" style="width:100%;">Зарегистрироваться</button>
        </form>
        <p style="text-align:center;font-size:14px;color:var(--g-text-secondary);margin-top:16px;">Уже есть аккаунт? <a href="/login" style="color:var(--g-accent-light);">Войти</a></p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
