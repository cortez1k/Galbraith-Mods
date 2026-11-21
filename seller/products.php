<?php
$user = currentUser();
$products = getProductsBySeller($user['id']);
$pageTitle = 'Мои товары';
include __DIR__ . '/../templates/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:700;">Мои товары</h1>
    <div style="display:flex;gap:10px;">
        <a href="/seller/templates" class="btn btn-secondary">Шаблоны товаров</a>
        <a href="/seller/products/create" class="btn btn-primary">+ Добавить</a>
    </div>
</div>

<?php if (function_exists('traderProductLimit')): $limit = PHP_INT_MAX; ?>
<p style="color:var(--g-text-secondary);font-size:13px;margin-bottom:16px;">Количество товаров не ограничено.</p>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">У вас пока нет товаров</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Название</th><th>Категория</th><th>Цена</th><th>Статус</th><th>Скачиваний</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
        <tr>
            <td><a href="/product/<?= e($p['slug']) ?>" target="_blank" style="color:var(--g-accent-light);"><?= e($p['title']) ?></a></td>
            <td><?= e($p['category_name'] ?? '—') ?></td>
            <td><?= $p['price'] > 0 ? number_format($p['price'], 0, ',', ' ') . ' ₽' : 'Бесплатно' ?></td>
            <td>
                <?php $badges = ['draft' => '📝 Черновик', 'on_moderation' => '⏳ На модерации', 'approved' => '✅ Одобрен', 'rejected' => '❌ Отклонён']; ?>
                <?= $badges[$p['status']] ?? e($p['status']) ?>
                <?php if ($p['status'] === 'approved' && !$p['is_active']): ?><br><span style="font-size:12px;color:var(--g-text-secondary);">🙈 Скрыт с площадки</span><?php endif; ?>
                <?php if ($p['status'] === 'rejected' && $p['moderation_comment']): ?><br><span style="font-size:12px;color:var(--g-text-secondary);"><?= e($p['moderation_comment']) ?></span><?php endif; ?>
            </td>
            <td><?= number_format($p['downloads_count'], 0, ',', ' ') ?></td>
            <td>
                <a href="/seller/products/<?= $p['id'] ?>/edit" class="btn btn-secondary btn-sm">Изменить</a>
                <?php if ($p['status'] === 'approved'): ?>
                    <form method="POST" action="/seller/products/<?= $p['id'] ?>/toggle" style="display:inline;">
                        <button type="submit" class="btn btn-secondary btn-sm"><?= $p['is_active'] ? '🙈 Скрыть' : '👁 Показать' ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($p['price'] > 0): ?>
                    <a href="/seller/products/<?= $p['id'] ?>/stock" class="btn btn-secondary btn-sm"><?= $p['auto_delivery'] ? '🤖 Автовыдача (' . getStockCount($p['id']) . ')' : 'Настроить автовыдачу' ?></a>
                <?php endif; ?>
                <?php if (in_array($p['status'], ['draft', 'rejected'])): ?>
                    <form method="POST" action="/seller/products/<?= $p['id'] ?>/submit" style="display:inline;"><button type="submit" class="btn btn-primary btn-sm">На модерацию</button></form>
                <?php endif; ?>
                <a href="/seller/products/<?= $p['id'] ?>/delete" class="btn btn-secondary btn-sm btn-danger" onclick="return confirm('Удалить товар?')">Удалить</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
