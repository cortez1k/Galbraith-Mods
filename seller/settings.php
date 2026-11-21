<?php
$user = currentUser();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle = 'Реквизиты для выплат';
include __DIR__ . '/../templates/header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Реквизиты для выплат</h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;">Обязательны для создания платных товаров и вывода средств. Номер карты хранится в зашифрованном виде.</p>

<?php if ($flash): ?><div style="background:rgba(232,28,90,0.1);border:1px solid rgba(232,28,90,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<form method="POST" action="/seller/settings" style="max-width:480px;display:grid;gap:16px;">
        <div>
        <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Номер карты</label>
        <?php if ($user['card_number']): ?>
            <p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Сохранена карта: •••• <?= e(substr(decryptSecret($user['card_number']), -4)) ?></p>
        <?php endif; ?>
        <input type="text" name="card_number" class="input" placeholder="<?= $user['card_number'] ? 'Оставьте пустым, чтобы не менять' : '0000 0000 0000 0000' ?>">
    </div>
    <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">ФИО получателя</label><input type="text" name="full_name" class="input" value="<?= e($user['full_name'] ?? '') ?>"></div>
    <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Контакт (телефон/VK)</label><input type="text" name="contact_info" class="input" value="<?= e($user['contact_info'] ?? '') ?>"></div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
</form>

<?php include __DIR__ . '/../templates/footer.php'; ?>
