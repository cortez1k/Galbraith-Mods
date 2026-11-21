<?php
$db = getDB();
$sql = "SELECT p.id, p.title, p.slug, p.price, p.auto_delivery, p.auto_delivery_content, p.auto_delivery_message,
        u.name AS seller_name, u.id AS seller_id,
        (SELECT COUNT(*) FROM product_stock ps WHERE ps.product_id = p.id AND ps.is_used = 0) AS in_stock,
        (SELECT COUNT(*) FROM product_stock ps WHERE ps.product_id = p.id AND ps.is_used = 1) AS delivered
        FROM trader_products p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.auto_delivery = 1
        ORDER BY (CASE WHEN p.auto_delivery_content != '' THEN 1 ELSE 0 END) ASC, in_stock ASC, p.title ASC";
$products = $db->query($sql)->fetchAll();

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $needle = mb_strtolower($search);
    $products = array_values(array_filter($products, function ($p) use ($needle) {
        return mb_strpos(mb_strtolower($p['title'] ?? ''), $needle) !== false
            || mb_strpos(mb_strtolower($p['seller_name'] ?? ''), $needle) !== false;
    }));
}

$totalInStock = array_sum(array_column($products, 'in_stock'));
$totalDelivered = array_sum(array_column($products, 'delivered'));
$outOfStock = count(array_filter($products, function ($p) { return $p['auto_delivery_content'] === '' && (int)$p['in_stock'] === 0; }));

$pageTitle = 'Товары с автовыдачей';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:8px;">⚡ Товары с автовыдачей</h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;">
    Обзор всех товаров с включённой автовыдачей у всех продавцов сразу — остаток на складе, что уже выдано, у кого скоро закончится.
</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px;">
    <div class="card" style="padding:16px;"><div style="font-size:12px;color:var(--g-text-secondary);margin-bottom:4px;">Товаров с автовыдачей</div><div style="font-size:22px;font-weight:700;"><?= count($products) ?></div></div>
    <div class="card" style="padding:16px;"><div style="font-size:12px;color:var(--g-text-secondary);margin-bottom:4px;">Позиций в наличии</div><div style="font-size:22px;font-weight:700;color:var(--g-success);"><?= $totalInStock ?></div></div>
    <div class="card" style="padding:16px;"><div style="font-size:12px;color:var(--g-text-secondary);margin-bottom:4px;">Уже выдано</div><div style="font-size:22px;font-weight:700;"><?= $totalDelivered ?></div></div>
    <div class="card" style="padding:16px;<?= $outOfStock ? 'border-color:rgba(240,71,92,.4);' : '' ?>"><div style="font-size:12px;color:var(--g-text-secondary);margin-bottom:4px;">Закончился склад</div><div style="font-size:22px;font-weight:700;<?= $outOfStock ? 'color:var(--g-danger);' : '' ?>"><?= $outOfStock ?></div></div>
</div>

<form method="GET" style="margin-bottom:20px;max-width:360px;">
    <input type="text" name="q" class="input" value="<?= e($search) ?>" placeholder="Поиск по товару или продавцу">
</form>

<?php if (!$products): ?>
    <p style="color:var(--g-text-secondary);">Товаров с автовыдачей пока нет<?= $search !== '' ? ' по этому запросу' : '' ?>.</p>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Товар</th><th>Продавец</th><th>Режим</th><th>В наличии</th><th>Выдано</th><th>Сообщение при получении</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): $isConstant = $p['auto_delivery_content'] !== ''; $low = !$isConstant && (int)$p['in_stock'] === 0; ?>
        <tr style="<?= $low ? 'background:rgba(240,71,92,.06);' : '' ?>">
            <td><a href="/product/<?= e($p['slug']) ?>" target="_blank" style="color:var(--g-accent-light);"><?= e($p['title']) ?></a></td>
            <td><?= e($p['seller_name'] ?: '—') ?></td>
            <td><?= $isConstant ? '♾️ Постоянная позиция' : '📦 Склад' ?></td>
            <td><?= $isConstant ? '—' : ($low ? '<strong style="color:var(--g-danger);">0 — закончился</strong>' : $p['in_stock']) ?></td>
            <td><?= $isConstant ? '—' : $p['delivered'] ?></td>
            <td style="max-width:220px;font-size:12px;color:var(--g-text-secondary);"><?= $p['auto_delivery_message'] ? e(mb_strimwidth($p['auto_delivery_message'], 0, 60, '…')) : '—' ?></td>
            <td><a href="/seller/products/<?= $p['id'] ?>/stock" target="_blank" class="btn btn-secondary btn-sm">Склад</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
