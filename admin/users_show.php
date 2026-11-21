<?php
$id = (int)($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) redirect('/admin/users');

$showQr = isset($_GET['show_qr']) && $u['role'] === 'moderator' && $u['totp_secret'];
$pageTitle = 'Пользователь: ' . $u['name'];
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:4px;"><?= e($u['name']) ?></h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:24px;"><?= e($u['email']) ?> · Регистрация: <?= date('d.m.Y', strtotime($u['created_at'])) ?></p>

<?php if ($showQr): ?>
<div class="card" style="padding:24px;text-align:center;margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">🔐 2FA-секрет для нового модератора</h3>
    <img src="<?= e(getTOTPQRUrl($u['totp_secret'], 'Galbraith Moderator: ' . $u['name'])) ?>" style="width:200px;height:200px;border-radius:8px;">
    <div style="margin-top:12px;font-family:monospace;font-size:16px;letter-spacing:2px;"><?= chunk_split(e($u['totp_secret']), 4, ' ') ?></div>
    <p style="font-size:13px;color:var(--g-text-secondary);margin-top:10px;">Передайте этот QR-код или ключ модератору — он понадобится при каждом входе в /admin/login.</p>
</div>
<?php endif; ?>

<div class="card" style="padding:20px;margin-bottom:20px;max-width:480px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:4px;">Роль</h3>
    <p style="font-size:12px;color:var(--g-text-secondary);margin-bottom:12px;">Основная — определяет бейдж и раздел по умолчанию.</p>
    <form method="POST" action="/admin/users/<?= $u['id'] ?>/role" style="display:flex;gap:8px;">
        <select name="role" class="select">
            <?php foreach (ROLES as $r): ?><option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(roleLabel($r)) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
    <?php if ($u['role'] === 'moderator'): ?>
        <a href="/admin/users/<?= $u['id'] ?>?show_qr=1" class="btn btn-secondary btn-sm" style="margin-top:10px;display:inline-block;">Показать QR 2FA</a>
    <?php endif; ?>
</div>

<div class="card" style="padding:20px;margin-bottom:20px;max-width:480px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:4px;">Дополнительные роли</h3>
    <p style="font-size:12px;color:var(--g-text-secondary);margin-bottom:12px;">Можно выдать сразу несколько — например, «Торговец» + «Модератор» одновременно. Доступ (панели, права) даётся по любой из ролей пользователя.</p>
    <?php $extras = extraRoles($u['id']); ?>
    <form method="POST" action="/admin/users/<?= $u['id'] ?>/extra-roles" style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach (ROLES as $r): if ($r === $u['role']) continue; ?>
            <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                <input type="checkbox" name="roles[]" value="<?= $r ?>" <?= in_array($r, $extras, true) ? 'checked' : '' ?>>
                <?= e(roleLabel($r)) ?>
            </label>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary" style="width:fit-content;margin-top:6px;">Сохранить дополнительные роли</button>
    </form>
</div>

<div class="card" style="padding:20px;max-width:480px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;">Реквизиты и комиссия торговца</h3>
    <form method="POST" action="/admin/users/<?= $u['id'] ?>/edit" style="display:grid;gap:12px;">
        <div>
            <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:4px;">Номер карты <?= $u['card_number'] ? '(сохранена, оканчивается на ' . e(substr(decryptSecret($u['card_number']), -4)) . ')' : '' ?></label>
            <input type="text" name="card_number" class="input" placeholder="Оставьте пустым, чтобы не менять">
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:4px;">ФИО</label><input type="text" name="full_name" class="input" value="<?= e($u['full_name'] ?? '') ?>"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:4px;">Контакт</label><input type="text" name="contact_info" class="input" value="<?= e($u['contact_info'] ?? '') ?>"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:4px;">Индивидуальная комиссия %, (пусто = глобальная)</label><input type="number" step="0.01" name="commission_percent" class="input" value="<?= e($u['commission_percent'] ?? '') ?>"></div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
