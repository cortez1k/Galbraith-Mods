<?php
$db = getDB();
$sql = "SELECT p.*, s.name AS seller_name, tp.title AS product_title
        FROM promo_codes p
        LEFT JOIN users s ON p.seller_id = s.id
        LEFT JOIN trader_products tp ON p.product_id = tp.id
        ORDER BY p.created_at DESC";
$promos = $db->query($sql)->fetchAll();
$promoSavings = [];
foreach ($db->query('SELECT promo_id, SUM(discount_amount) as total FROM promo_code_usages GROUP BY promo_id')->fetchAll() as $row) {
    $promoSavings[$row['promo_id']] = (float)$row['total'];
}
$sellers = $db->query("SELECT id, name FROM users WHERE role = 'trader' ORDER BY name")->fetchAll();
$pageTitle = 'Промокоды';
include __DIR__ . '/../templates/admin_header.php';
?>
<h1 style="font-size:22px;font-weight:700;margin-bottom:20px">🏷️ Промокоды</h1>

<div class="card" style="padding:20px;margin-bottom:24px;max-width:700px">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:12px">Создать промокод</h3>
    <form method="POST" action="/admin/promos/create" style="display:grid;gap:10px">
        <input name="code" class="input" placeholder="GALBRAITH10" maxlength="50" required>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <select name="discount_type" class="select"><option value="percent">Процентная скидка</option><option value="fixed">Фиксированная скидка, ₽</option></select>
            <input type="number" step="0.01" name="discount_value" class="input" placeholder="Скидка" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <input type="number" name="max_uses" class="input" min="0" placeholder="Макс. использований (0 = без лимита)">
            <input type="number" step="0.01" name="min_order_amount" class="input" min="0" placeholder="Мин. сумма заказа, ₽ (0 = без ограничения)">
        </div>
        <input type="datetime-local" name="expires_at" class="input">
        <div>
            <label style="font-size:13px;color:var(--g-text-secondary);display:block;margin-bottom:4px;">Привязка (необязательно)</label>
            <select name="seller_id" class="select" style="margin-bottom:8px;">
                <option value="">Действует у всех продавцов</option>
                <?php foreach ($sellers as $s): ?><option value="<?= $s['id'] ?>">Только у продавца: <?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
            <input type="text" name="product_id" class="input" placeholder="ID конкретного товара (необязательно, посмотреть в /seller/products)">
            <p style="font-size:12px;color:var(--g-text-secondary);margin-top:4px;">Если указан товар — промокод сработает только на него. Если указан только продавец — на любой его товар.</p>
        </div>
        <p style="font-size:12px;color:var(--g-text-secondary);margin:0;">Один пользователь может использовать каждый промокод только один раз — это работает всегда, отдельной настройки не требует.</p>
        <button class="btn btn-primary" style="width:fit-content">Создать</button>
    </form>
</div>

<div style="overflow:auto">
<table class="admin-table">
    <thead><tr><th>Код</th><th>Скидка</th><th>Привязка</th><th>Мин. сумма</th><th>Использовано</th><th>Сэкономлено</th><th>Срок</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($promos as $p): ?>
        <tr>
            <td><strong><?= e($p['code']) ?></strong></td>
            <td><?= e($p['discount_value']) ?><?= $p['discount_type'] === 'percent' ? '%' : ' ₽' ?></td>
            <td style="font-size:12px;"><?php if ($p['product_id']): ?>Товар: <?= e($p['product_title'] ?: '#' . $p['product_id']) ?><?php elseif ($p['seller_id']): ?>Продавец: <?= e($p['seller_name'] ?: '#' . $p['seller_id']) ?><?php else: ?>Все<?php endif; ?></td>
            <td><?= $p['min_order_amount'] > 0 ? number_format($p['min_order_amount'], 0, ',', ' ') . ' ₽' : '—' ?></td>
            <td><?= $p['used_count'] ?> / <?= $p['max_uses'] ?: '∞' ?></td>
            <td><a href="/admin/promos/<?= $p['id'] ?>/usages" style="color:var(--g-accent-light);"><?= number_format($promoSavings[$p['id']] ?? 0, 2, ',', ' ') ?> ₽ →</a></td>
            <td><?= $p['expires_at'] ? date('d.m.Y H:i', strtotime($p['expires_at'])) : 'Без срока' ?></td>
            <td><?= $p['is_active'] ? '🟢 Активен' : '⚪ Выключен' ?></td>
            <td style="display:flex;gap:6px;">
                <form method="POST" action="/admin/promos/<?= $p['id'] ?>/toggle"><button class="btn btn-secondary btn-sm"><?= $p['is_active'] ? 'Выключить' : 'Включить' ?></button></form>
                <form method="POST" action="/admin/promos/<?= $p['id'] ?>/delete" onsubmit="return confirm('Удалить промокод «<?= e($p['code']) ?>» безвозвратно?');"><button class="btn btn-danger btn-sm">Удалить</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
