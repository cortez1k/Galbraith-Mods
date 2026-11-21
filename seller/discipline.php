<?php
$user = currentUser();
$penalties = getActivePenalties($user['id']);
$maxActive = (int)setting('penalty_max_active', 5);
$pageTitle = 'Дисциплина';
include __DIR__ . '/../templates/header.php';
?>

<a href="/seller/bonuses" class="btn btn-secondary" style="margin-bottom:16px;">← Мои бонусы</a>
<h1 style="font-size:22px;font-weight:700;margin-bottom:8px;">Дисциплина</h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;">
    Каждое активное взыскание увеличивает вашу комиссию на <?= (float)setting('penalty_commission_step', 1) ?>% на <?= (int)setting('penalty_expiry_days', 30) ?> дней.
</p>

<?php if (count($penalties) >= $maxActive): ?>
<div style="background:rgba(255,59,48,0.1);border:1px solid rgba(255,59,48,0.3);border-radius:var(--g-radius-sm);padding:16px 20px;margin-bottom:20px;">
    ⚠️ <strong>У вас <?= count($penalties) ?> активных взысканий.</strong> При достижении лимита администрация может принять меры вплоть до удаления с площадки.
</div>
<?php endif; ?>

<?php if (!$penalties): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Активных взысканий нет 👍</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($penalties as $p): ?>
    <div class="card" style="padding:16px 18px;">
        <div style="font-weight:600;margin-bottom:4px;"><?= e($p['reason']) ?></div>
        <?php if ($p['comment']): ?><p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;"><?= e($p['comment']) ?></p><?php endif; ?>
        <div style="font-size:12px;color:var(--g-text-secondary);">Вынесено: <?= date('d.m.Y', strtotime($p['issued_at'])) ?> · Истекает: <?= date('d.m.Y', strtotime($p['expires_at'])) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
