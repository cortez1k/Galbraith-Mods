<?php
$pageTitle = 'Восстановление пароля';
$sent = !empty($_SESSION['forgot_sent']); unset($_SESSION['forgot_sent']);
include __DIR__ . '/../templates/header.php';
?>

<div style="max-width:420px;margin:0 auto;padding:40px 16px;">
    <h1 style="font-size:22px;font-weight:700;margin-bottom:8px;text-align:center;">Восстановление пароля</h1>
    <?php if ($sent): ?>
        <div style="background:rgba(52,199,89,0.1);border:1px solid rgba(52,199,89,0.3);color:#34C759;border-radius:var(--g-radius-sm);padding:16px;margin:16px 0;font-size:14px;text-align:center;">
            Если такой email зарегистрирован — на него отправлена ссылка для сброса пароля (действительна 1 час).
        </div>
        <a href="/login" class="btn btn-secondary" style="width:100%;">На страницу входа</a>
    <?php else: ?>
        <p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;text-align:center;">Введите email, указанный при регистрации — пришлём ссылку для сброса пароля.</p>
        <form method="POST" action="/forgot-password" style="display:grid;gap:14px;">
            <input type="email" name="email" class="input" placeholder="Email" required>
            <button type="submit" class="btn btn-primary" style="width:100%;">Отправить ссылку</button>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
