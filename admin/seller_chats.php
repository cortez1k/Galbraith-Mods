<?php
$db = getDB();
// Каждый чат — это уникальная связка (продавец, покупатель, товар). Тянем последнее
// сообщение и число непрочитанных для превью списка, аналогично списку заказов.
$sql = "SELECT sm.seller_id, sm.buyer_id, sm.product_id,
        s.name AS seller_name, b.name AS buyer_name, p.title AS product_title,
        MAX(sm.created_at) AS last_at,
        COUNT(*) AS messages_count
        FROM seller_messages sm
        LEFT JOIN users s ON sm.seller_id = s.id
        LEFT JOIN users b ON sm.buyer_id = b.id
        LEFT JOIN trader_products p ON sm.product_id = p.id
        GROUP BY sm.seller_id, sm.buyer_id, sm.product_id
        ORDER BY last_at DESC";
$chats = $db->query($sql)->fetchAll();

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $needle = mb_strtolower($search);
    $chats = array_values(array_filter($chats, function ($c) use ($needle) {
        return mb_strpos(mb_strtolower($c['seller_name'] ?? ''), $needle) !== false
            || mb_strpos(mb_strtolower($c['buyer_name'] ?? ''), $needle) !== false
            || mb_strpos(mb_strtolower($c['product_title'] ?? ''), $needle) !== false;
    }));
}

$pageTitle = 'Чаты: вопросы продавцам';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:8px;">💬 Чаты: вопросы продавцам</h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;">
    Переписка покупателей с продавцами по товарам (вопросы до покупки), отдельно от чатов заказов.
</p>

<form method="GET" style="margin-bottom:20px;max-width:360px;">
    <input type="text" name="q" class="input" value="<?= e($search) ?>" placeholder="Поиск по продавцу, покупателю, товару">
</form>

<?php if (!$chats): ?>
    <p style="color:var(--g-text-secondary);">Чатов пока нет<?= $search !== '' ? ' по этому запросу' : '' ?>.</p>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Продавец</th><th>Покупатель</th><th>Товар</th><th>Сообщений</th><th>Последнее</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($chats as $c): ?>
        <tr>
            <td><?= e($c['seller_name'] ?: '—') ?></td>
            <td><?= e($c['buyer_name'] ?: '—') ?></td>
            <td><?= e($c['product_title'] ?: 'Товар удалён') ?></td>
            <td><?= (int)$c['messages_count'] ?></td>
            <td><?= e($c['last_at']) ?></td>
            <td><a href="/admin/seller-chats/view?seller_id=<?= (int)$c['seller_id'] ?>&buyer_id=<?= (int)$c['buyer_id'] ?>&product_id=<?= (int)$c['product_id'] ?>" class="btn btn-secondary btn-sm">Открыть</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
