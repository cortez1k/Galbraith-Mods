<?php
$user = currentUser();
$templates = getTraderProductTemplates($user['id']);
$pageTitle = 'Шаблоны товаров';
include __DIR__ . '/../templates/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:10px;">
    <h1 style="font-size:22px;font-weight:700;">Шаблоны товаров</h1>
    <a href="/seller/templates/create" class="btn btn-primary">+ Создать шаблон</a>
</div>
<p style="color:var(--g-text-secondary);font-size:13px;margin-bottom:20px;">Шаблон хранит часто повторяющиеся поля товара (описание, категорию, версию игры, обложку и т.д.). При создании нового товара выберите шаблон — и форма заполнится автоматически, останется только поправить название и ссылку на файл.</p>

<?php if (!$templates): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">У вас пока нет шаблонов. Создайте первый, чтобы не заполнять одни и те же поля вручную каждый раз.</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Название шаблона</th><th>Заготовка названия товара</th><th>Категория</th><th>Цена</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($templates as $t): ?>
        <tr>
            <td><strong><?= e($t['name']) ?></strong></td>
            <td><?= e($t['title'] ?: '—') ?></td>
            <td><?= e($t['category_name'] ?? '—') ?></td>
            <td><?= $t['price'] > 0 ? number_format($t['price'], 0, ',', ' ') . ' ₽' : 'Бесплатно' ?></td>
            <td style="white-space:nowrap;">
                <a href="/seller/products/create?template_id=<?= $t['id'] ?>" class="btn btn-primary btn-sm">Создать товар</a>
                <a href="/seller/templates/<?= $t['id'] ?>/edit" class="btn btn-secondary btn-sm">Изменить</a>
                <form method="POST" action="/seller/templates/<?= $t['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('Удалить шаблон?');">
                    <button type="submit" class="btn btn-secondary btn-sm btn-danger">Удалить</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
