<?php
$db = getDB();
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT o.*, p.title as product_title, b.name as buyer_name, s.name as seller_name FROM orders o
        LEFT JOIN trader_products p ON o.product_id = p.id
        LEFT JOIN users b ON o.buyer_id = b.id LEFT JOIN users s ON o.seller_id = s.id WHERE 1=1";
$params = [];
if ($statusFilter) { $sql .= ' AND o.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY o.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
$pageTitle = 'Заказы';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Заказы</h1>

<form method="GET" style="margin-bottom:20px;">
    <select name="status" class="select" style="width:auto;" onchange="this.form.submit()">
        <option value="">Все статусы</option>
        <?php foreach (['awaiting_confirmation','paid','in_progress','completed','disputed','refunded','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(orderStatusLabel($s)) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>#</th><th>Товар</th><th>Покупатель</th><th>Продавец</th><th>Сумма</th><th>Статус</th><th>Дата</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td>#<?= $o['id'] ?></td>
            <td><?= e($o['product_title']) ?></td>
            <td><?= e($o['buyer_name']) ?></td>
            <td><?= e($o['seller_name']) ?></td>
            <td><?= number_format($o['final_amount'], 0, ',', ' ') ?> ₽</td>
            <td><span class="status status-<?= $o['status'] === 'completed' ? 'closed' : ($o['status'] === 'awaiting_confirmation' ? 'new' : 'progress') ?>"><?= e(orderStatusLabel($o['status'])) ?></span></td>
            <td><?= date('d.m.Y', strtotime($o['created_at'])) ?></td>
            <td><a href="/admin/orders/<?= $o['id'] ?>" class="btn btn-secondary btn-sm">Открыть</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
