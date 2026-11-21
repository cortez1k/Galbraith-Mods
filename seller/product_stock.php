<?php
$productId = (int)($_GET['id'] ?? 0);
// Админ может открыть склад любого товара (для обзора автовыдачи), обычный
// продавец — только свой собственный.
if (hasRole('admin')) {
    $stmt = getDB()->prepare('SELECT * FROM trader_products WHERE id = ?');
    $stmt->execute([$productId]);
} else {
    $stmt = getDB()->prepare('SELECT * FROM trader_products WHERE id = ? AND user_id = ?');
    $stmt->execute([$productId, $_SESSION['user_id']]);
}
$product = $stmt->fetch();
if (!$product) redirect('/seller/products');
$isAdminViewing = hasRole('admin') && (int)$product['user_id'] !== (int)$_SESSION['user_id'];

$stockItems = getStockItems($productId);
$unusedCount = getStockCount($productId);
$hasConstantPosition = getAutoDeliveryContent($productId) !== '';
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle = 'Автовыдача: ' . $product['title'];
include __DIR__ . '/../templates/header.php';
?>

<a href="/seller/products" class="btn btn-secondary" style="margin-bottom:16px;">← Мои товары</a>
<h1 style="font-size:22px;font-weight:700;margin-bottom:8px;">Автовыдача: <?= e($product['title']) ?></h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;">
    Загрузите склад из нескольких позиций (ключи, аккаунты, коды, ссылки — по одной в строке). Каждая позиция выдаётся ровно одному покупателю и больше не используется повторно.
</p>

<?php if ($isAdminViewing): ?>
<div class="alert alert-info" style="margin-bottom:20px;">👁️ Вы смотрите склад чужого товара как администратор — только просмотр, редактирование недоступно.</div>
<?php endif; ?>

<?php if ($flash): ?><div style="background:rgba(232,28,90,0.1);border:1px solid rgba(232,28,90,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<div class="card" style="padding:20px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
        <strong>Автовыдача: <?= $product['auto_delivery'] ? '✅ включена' : '⏸️ выключена' ?></strong>
        <div style="font-size:13px;color:var(--g-text-secondary);margin-top:4px;">
            <?= $hasConstantPosition ? 'Постоянная позиция активна (склад не расходуется)' : 'На складе: ' . $unusedCount . ' шт.' ?>
            <?php if (!$hasConstantPosition && $unusedCount === 0 && $product['auto_delivery']): ?> <span style="color:#FF3B30;">— товар закончился, выдача остановится!</span><?php endif; ?>
        </div>
    </div>
    <?php if (!$isAdminViewing): ?>
    <form method="POST" action="/seller/products/<?= $productId ?>/stock/toggle">
        <button type="submit" class="btn <?= $product['auto_delivery'] ? 'btn-secondary' : 'btn-primary' ?>"><?= $product['auto_delivery'] ? 'Выключить автовыдачу' : 'Включить автовыдачу' ?></button>
    </form>
    <?php endif; ?>
</div>

<?php if (!$isAdminViewing): ?>
<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:8px;">Сообщение при получении</h3>
    <p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:12px;">Этот текст отправляется покупателю <strong>всегда</strong>, при каждой автовыдаче — отдельным сообщением, до самого товара (инструкция, приветствие, гарантийные условия и т. п.). Сам товар (ключ/аккаунт/код) уйдёт следующим сообщением, как обычно.</p>
    <form method="POST" action="/seller/products/<?= $productId ?>/stock/message">
        <textarea name="auto_delivery_message" class="input" rows="4" placeholder="Например: Спасибо за покупку! Инструкция по активации: ..."><?= e($product['auto_delivery_message'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-primary" style="margin-top:10px;">Сохранить сообщение</button>
    </form>
</div>

<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:8px;">Склад: добавить позиции</h3>
    <p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:12px;">По одной позиции на строку. Каждая уйдёт одному покупателю и исчезнет со склада.</p>
    <form method="POST" action="/seller/products/<?= $productId ?>/stock/add">
        <textarea name="items" class="input" rows="6" placeholder="KEY-AAAA-1111&#10;KEY-BBBB-2222&#10;KEY-CCCC-3333" <?= $hasConstantPosition ? 'disabled' : '' ?>></textarea>
        <button type="submit" class="btn btn-primary" style="margin-top:10px;" <?= $hasConstantPosition ? 'disabled' : '' ?>>Добавить на склад</button>
    </form>
    <?php if ($hasConstantPosition): ?><p style="font-size:12px;color:#ff9f0a;margin-top:8px;">Пока активна постоянная позиция ниже — склад не используется. Очистите её, чтобы вернуться к обычному складу.</p><?php endif; ?>
</div>

<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:6px;">Постоянная позиция (необязательно)</h3>
    <p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:12px;">Для случаев, когда всем покупателям нужно выдавать одно и то же значение без ограничения по количеству — например, инвайт-ссылку. Если заполнено — используется вместо склада.</p>
    <form method="POST" action="/seller/products/<?= $productId ?>/stock/position" style="display:flex;gap:10px;flex-wrap:wrap;">
        <input name="position" class="input" style="flex:1;min-width:220px;" value="<?= e(getAutoDeliveryContent($productId)) ?>" placeholder="Например: https://discord.gg/invite">
        <button type="submit" class="btn btn-secondary">Сохранить</button>
        <?php if ($hasConstantPosition): ?><button type="submit" form="clearPositionForm" class="btn btn-danger">Очистить</button><?php endif; ?>
    </form>
    <?php if ($hasConstantPosition): ?><form id="clearPositionForm" method="POST" action="/seller/products/<?= $productId ?>/stock/position" style="display:none;"><input type="hidden" name="position" value=""></form><?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="padding:20px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:14px;">Позиции склада</h3>
    <?php if (!$stockItems): ?>
        <p style="color:var(--g-text-secondary);font-size:14px;">Склад пуст</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="admin-table">
        <thead><tr><th>Содержимое</th><th>Статус</th><th>Заказ</th><?php if (!$isAdminViewing): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($stockItems as $item): ?>
            <tr>
                <td style="font-family:var(--g-font-mono);font-size:13px;"><?= e($item['content']) ?></td>
                <td><?= $item['is_used'] ? '✅ Выдано ' . date('d.m.Y H:i', strtotime($item['used_at'])) : '📦 В наличии' ?></td>
                <td><?= $item['order_id'] ? '<a href="/orders/' . $item['order_id'] . '" style="color:var(--g-accent-light);">#' . $item['order_id'] . '</a>' : '—' ?></td>
                <?php if (!$isAdminViewing): ?><td><?php if (!$item['is_used']): ?><form method="POST" action="/seller/products/<?= $productId ?>/stock/<?= $item['id'] ?>/delete"><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Удалить позицию?')">Удалить</button></form><?php endif; ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
