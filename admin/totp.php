<?php
$db = getDB();
$stmt = $db->prepare('SELECT totp_secret FROM users WHERE id = ?');
$stmt->execute([$_SESSION['admin_id']]);
$user = $stmt->fetch();
$has2FA = !empty($user['totp_secret']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'enable') {
        $secret = generateTOTPSecret();
        $db->prepare('UPDATE users SET totp_secret = ? WHERE id = ?')->execute([$secret, $_SESSION['admin_id']]);
        $has2FA = true;
        $message = '2FA включён. Отсканируйте QR-код в Google Authenticator.';
    } elseif ($_POST['action'] === 'disable') {
        $code = $_POST['verify_code'] ?? '';
        if (verifyTOTPCode($user['totp_secret'], $code)) {
            $db->prepare('UPDATE users SET totp_secret = NULL WHERE id = ?')->execute([$_SESSION['admin_id']]);
            $has2FA = false;
            $message = '2FA отключён.';
        } else {
            $error = 'Неверный код. 2FA не отключён.';
        }
    }
    // Refresh user data
    $stmt = $db->prepare('SELECT totp_secret FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $user = $stmt->fetch();
}

$pageTitle = 'Двухфакторная аутентификация';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">🔐 Двухфакторная аутентификация</h1>

<?php if ($message): ?>
<div style="background:rgba(52,199,89,0.1);color:#34C759;padding:14px 18px;border-radius:var(--g-radius-sm);margin-bottom:16px;font-size:14px;"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div style="background:rgba(255,59,48,0.1);color:#FF3B30;padding:14px 18px;border-radius:var(--g-radius-sm);margin-bottom:16px;font-size:14px;"><?= e($error) ?></div>
<?php endif; ?>

<div style="max-width:500px;">
    <?php if (!$has2FA): ?>
        <p style="color:var(--g-text-secondary);font-size:14px;line-height:1.6;margin-bottom:20px;">
            Двухфакторная аутентификация (2FA) защищает админку даже если пароль скомпрометирован. 
            После включения потребуется код из приложения <strong>Google Authenticator</strong> при каждом входе.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="enable">
            <button type="submit" class="btn btn-primary">Включить 2FA</button>
        </form>
    <?php else: ?>
        <p style="color:var(--g-text-secondary);font-size:14px;line-height:1.6;margin-bottom:20px;">
            2FA <strong style="color:#34C759;">включён</strong>. Отсканируйте QR-код в Google Authenticator, Authy или любом TOTP-приложении.
        </p>

        <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:24px;text-align:center;margin-bottom:20px;">
            <img src="<?= e(getTOTPQRUrl($user['totp_secret'], 'Galbraith Admin')) ?>" style="width:200px;height:200px;border-radius:8px;" alt="QR Code">
            <div style="margin-top:12px;font-family:monospace;font-size:16px;letter-spacing:2px;"><?= chunk_split(e($user['totp_secret']), 4, ' ') ?></div>
            <p style="font-size:12px;color:var(--g-text-secondary);margin-top:8px;">Если не сканируется — введите ключ вручную</p>
        </div>

        <div style="border-top:1px solid var(--g-border);padding-top:20px;">
            <p style="font-size:14px;color:var(--g-text-secondary);margin-bottom:12px;">Для отключения 2FA введите текущий код:</p>
            <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;">
                <input type="hidden" name="action" value="disable">
                <input type="text" name="verify_code" class="input" placeholder="000000" maxlength="6" required style="width:120px;text-align:center;letter-spacing:4px;" inputmode="numeric" pattern="[0-9]*">
                <button type="submit" class="btn btn-danger">Отключить 2FA</button>
            </form>
        </div>
    <?php endif; ?>

    <div style="margin-top:24px;">
        <a href="/admin" class="btn btn-secondary">← Назад в админку</a>
    </div>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
