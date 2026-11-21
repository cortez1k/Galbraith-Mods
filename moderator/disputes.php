<?php
$stmt = getDB()->prepare("SELECT o.*, p.title as product_title, b.name as buyer_name, s.name as seller_name
    FROM orders o LEFT JOIN trader_products p ON o.product_id = p.id
    LEFT JOIN users b ON o.buyer_id = b.id LEFT JOIN users s ON o.seller_id = s.id
    WHERE o.status = 'disputed' ORDER BY o.dispute_opened_at DESC");
$stmt->execute();
$disputes = $stmt->fetchAll();
$pageTitle = 'Споры';
include __DIR__ . '/../templates/moderator_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Открытые споры</h1>

<?php if (!$disputes): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Открытых споров нет</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px;">
    <?php foreach ($disputes as $o): ?>
    <div class="card" style="padding:18px;">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
            <div>
                <strong><?= e($o['product_title']) ?></strong> — заказ #<?= $o['id'] ?><br>
                <span style="font-size:13px;color:var(--g-text-secondary);">Покупатель: <?= e($o['buyer_name']) ?> · Продавец: <?= e($o['seller_name']) ?> · Открыт: <?= date('d.m.Y H:i', strtotime($o['dispute_opened_at'])) ?></span>
            </div>
            <a href="/moderator/chats/<?= $o['id'] ?>" class="btn btn-secondary btn-sm">Посмотреть переписку</a>
        </div>
        <form method="POST" action="/moderator/disputes/<?= $o['id'] ?>/resolve" style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="submit" name="decision" value="refund" class="btn btn-secondary btn-sm" onclick="return confirm('Вернуть деньги покупателю?')">💸 Возврат покупателю</button>
            <button type="submit" name="decision" value="release" class="btn btn-primary btn-sm" onclick="return confirm('Зачислить деньги продавцу?')">✅ Оставить деньги продавцу</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/moderator_footer.php'; ?>
