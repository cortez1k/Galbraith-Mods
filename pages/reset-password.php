<?php
$token = $_GET['token'] ?? '';
$pageTitle = 'Новый пароль';
$error = $_SESSION['reset_error'] ?? null; unset($_SESSION['reset_error']);
include __DIR__ . '/../templates/header.php';
?>

<div style="max-width:420px;margin:0 auto;padding:40px 16px;">
    <h1 style="font-size:22px;font-weight:700;margin-bottom:20px;text-align:center;">Новый пароль</h1>
    <?php if ($error): ?>
        <div style="background:rgba(255,80,80,0.1);border:1px solid rgba(255,80,80,0.3);color:#ff5050;border-radius:var(--g-radius-sm);padding:12px;margin-bottom:16px;font-size:14px;"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="/reset-password/<?= e($token) ?>" style="display:grid;gap:14px;">
        <input type="password" name="password" class="input" placeholder="Новый пароль" required minlength="6">
        <input type="password" name="password_confirm" class="input" placeholder="Повторите пароль" required minlength="6">
        <button type="submit" class="btn btn-primary" style="width:100%;">Сохранить новый пароль</button>
    </form>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
