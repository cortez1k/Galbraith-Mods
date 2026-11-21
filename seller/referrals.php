<?php
$user = currentUser();
$link = getReferralLink($user['id']);
$stats = getReferralStats($user['id']);
$history = getReferralHistory($user['id']);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle = 'Реферальная программа';
include __DIR__ . '/../templates/header.php';
?>

<a href="/seller/bonuses" class="btn btn-secondary" style="margin-bottom:16px;">← Мои бонусы</a>
<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Реферальная программа</h1>

<?php if ($flash): ?><div style="background:rgba(232,28,90,0.1);border:1px solid rgba(232,28,90,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<div class="card" style="padding:20px;margin-bottom:24px;">
    <label style="font-size:13px;color:var(--g-text-secondary);display:block;margin-bottom:8px;">Ваша реферальная ссылка</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="text" readonly value="<?= e($link) ?>" class="input" id="refLink" style="flex:1;min-width:220px;">
        <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('refLink').value);showToast && showToast('Скопировано!')">Скопировать</button>
    </div>
    <form method="POST" action="/seller/referrals/update" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap"><input name="referral_code" class="input" value="<?= e($user['referral_code'] ?? ('u'.$user['id'])) ?>" maxlength="32" placeholder="Красивый код"><button class="btn btn-secondary">Сохранить код</button></form>
    <p style="font-size:12px;color:var(--g-text-secondary);margin-top:6px;">Используйте латиницу, цифры, _ или -. Например: galbraith.</p>
    <p style="font-size:13px;color:var(--g-text-secondary);margin-top:10px;">Привязка действует <?= (int)setting('referral_days', 30) ?> дней с момента регистрации по ссылке.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:24px;">
    <div class="card" style="padding:18px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--g-accent-light);"><?= $stats['total'] ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Всего рефералов</div></div>
    <div class="card" style="padding:18px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--g-accent-light);"><?= $stats['active'] ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Активных привязок</div></div>
    <div class="card" style="padding:18px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--g-accent-light);"><?= rtrim(rtrim(number_format($stats['earned'],2,'.',' '),'0'),'.') ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Бонусов заработано</div></div>
</div>

<h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">История начислений</h3>
<?php if (!$history): ?>
    <p style="color:var(--g-text-secondary);font-size:14px;">Начислений пока нет</p>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Дата</th><th>Реферал</th><th>Сумма</th><th>Причина</th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
        <tr>
            <td><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td>
            <td><?= e($h['referred_name']) ?></td>
            <td style="color:#34C759;">+<?= $h['amount'] ?></td>
            <td><?= e($h['reason']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
