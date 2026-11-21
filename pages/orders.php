<?php
$user = currentUser();
$statusFilter = $_GET['status'] ?? '';
$orders = getOrdersForBuyer($user['id'], $statusFilter ?: null);
$pageTitle = 'Мои заказы';
include __DIR__ . '/../templates/header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Мои заказы</h1>

<?php if (!$orders): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">У вас пока нет заказов. <a href="/marketplace" style="color:var(--g-accent-light);">Перейти на торговую площадку →</a></div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($orders as $o): ?>
    <a href="/orders/<?= $o['id'] ?>" class="card" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:inherit;flex-wrap:wrap;gap:8px;">
        <div>
            <div style="font-weight:600;"><?= e($o['product_title']) ?></div>
            <div style="font-size:13px;color:var(--g-text-secondary);">Продавец: <?= e($o['seller_name']) ?> · Заказ #<?= $o['id'] ?> от <?= date('d.m.Y', strtotime($o['created_at'])) ?></div>
        </div>
        <div style="text-align:right;">
            <div style="font-weight:700;"><?= number_format($o['final_amount'], 0, ',', ' ') ?> ₽</div>
            <span class="status status-<?= $o['status'] === 'completed' ? 'closed' : ($o['status'] === 'awaiting_confirmation' ? 'new' : 'progress') ?>"><?= e(orderStatusLabel($o['status'])) ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
