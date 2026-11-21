<?php
$queue = getModerationQueue();
$pageTitle = 'Модерация товаров';
include __DIR__ . '/../templates/admin_header.php';
?>

<?php if (!$queue): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Товаров на модерации нет 👍</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($queue as $p): ?>
    <div class="card" style="padding:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
            <strong><?= e($p['title']) ?></strong> · <?= $p['price'] > 0 ? number_format($p['price'],0,',',' ') . ' ₽' : 'Бесплатно' ?>
            <div style="font-size:13px;color:var(--g-text-secondary);">Продавец: <?= e($p['seller_name']) ?> · Обновлён: <?= date('d.m.Y H:i', strtotime($p['updated_at'])) ?> · версия <?= $p['version'] ?></div>
        </div>
        <a href="/admin/moderation/<?= $p['id'] ?>" class="btn btn-primary btn-sm">Проверить</a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
