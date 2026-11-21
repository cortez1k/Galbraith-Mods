<?php
$pageTitle = 'Подтверждение email';
$result = $_SESSION['verify_result'] ?? ['error' => 'Что-то пошло не так'];
unset($_SESSION['verify_result']);
include __DIR__ . '/../templates/header.php';
?>

<div style="max-width:420px;margin:0 auto;padding:60px 16px;text-align:center;">
    <?php if (!empty($result['ok'])): ?>
        <div style="font-size:48px;margin-bottom:16px;">✅</div>
        <h1 style="font-size:22px;font-weight:700;margin-bottom:10px;">Email подтверждён!</h1>
        <p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:24px;">Теперь вы можете пользоваться всеми возможностями сайта.</p>
        <a href="/profile" class="btn btn-primary">В личный кабинет</a>
    <?php else: ?>
        <div style="font-size:48px;margin-bottom:16px;">⚠️</div>
        <h1 style="font-size:22px;font-weight:700;margin-bottom:10px;">Не удалось подтвердить email</h1>
        <p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:24px;"><?= e($result['error']) ?></p>
        <a href="/login" class="btn btn-primary">На страницу входа</a>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
