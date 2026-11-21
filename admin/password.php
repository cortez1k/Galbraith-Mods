<?php
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password'])) {
        $error = 'Неверный текущий пароль';
    } elseif (strlen($new) < 6) {
        $error = 'Новый пароль слишком короткий (минимум 6 символов)';
    } elseif ($new !== $confirm) {
        $error = 'Пароли не совпадают';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $_SESSION['admin_id']]);
        $success = 'Пароль успешно изменён!';
    }
}

$pageTitle = 'Смена пароля';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Смена пароля</h1>

<div style="max-width:400px;">
    <?php if ($error): ?><div style="background:rgba(255,59,48,0.1);color:#FF3B30;padding:12px 16px;border-radius:var(--g-radius-sm);margin-bottom:16px;font-size:14px;"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div style="background:rgba(52,199,89,0.1);color:#34C759;padding:12px 16px;border-radius:var(--g-radius-sm);margin-bottom:16px;font-size:14px;"><?= e($success) ?></div><?php endif; ?>

    <form method="POST">
        <div style="display:grid;gap:12px;">
            <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Текущий пароль</label><input type="password" name="current_password" class="input" required></div>
            <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Новый пароль</label><input type="password" name="new_password" class="input" required minlength="6"></div>
            <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Повторите новый пароль</label><input type="password" name="confirm_password" class="input" required minlength="6"></div>
        </div>
        <div style="margin-top:20px;display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">Изменить пароль</button>
            <a href="/admin" class="btn btn-secondary">Назад</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
