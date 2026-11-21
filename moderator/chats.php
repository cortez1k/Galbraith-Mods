<?php
$stmt = getDB()->query("SELECT o.*, p.title as product_title, b.name as buyer_name, s.name as seller_name
    FROM orders o LEFT JOIN trader_products p ON o.product_id = p.id
    LEFT JOIN users b ON o.buyer_id = b.id LEFT JOIN users s ON o.seller_id = s.id
    ORDER BY o.updated_at DESC LIMIT 200");
$orders = $stmt->fetchAll();
$pageTitle = 'Чаты заказов';
include __DIR__ . '/../templates/moderator_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Чаты заказов (только просмотр)</h1>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>#</th><th>Товар</th><th>Покупатель</th><th>Продавец</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td>#<?= $o['id'] ?></td>
            <td><?= e($o['product_title']) ?></td>
            <td><?= e($o['buyer_name']) ?></td>
            <td><?= e($o['seller_name']) ?></td>
            <td><span class="status status-<?= $o['status'] === 'completed' ? 'closed' : ($o['status'] === 'awaiting_confirmation' ? 'new' : 'progress') ?>"><?= e(orderStatusLabel($o['status'])) ?></span></td>
            <td><a href="/moderator/chats/<?= $o['id'] ?>" class="btn btn-secondary btn-sm">Открыть</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/moderator_footer.php'; ?>
