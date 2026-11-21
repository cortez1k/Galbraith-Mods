<?php
$tasks = getAllTasks();
$conditionLabels = [
    'orders_completed' => 'Завершённых заказов',
    'positive_reviews' => 'Положительных отзывов',
    'active_products' => 'Активных товаров',
    'referrals_count' => 'Привлечённых пользователей',
    'revenue_total' => 'Суммарной выручки (₽)',
    'downloads_admin_materials' => 'Скачиваний материалов админа',
    'purchases_admin_materials' => 'Покупок материалов админа',
];
$pageTitle = 'Задания для бонусов';
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:600;">Задания для получения бонусов</h1>
    <a href="/admin/bonus-tasks/create" class="btn btn-primary">+ Новое задание</a>
</div>

<?php if (!$tasks): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Заданий пока нет</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Название</th><th>Условие</th><th>Бонус</th><th>Многократное</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
        <tr>
            <td><?= e($t['title']) ?></td>
            <td><?= e($conditionLabels[$t['condition_type']] ?? $t['condition_type']) ?> ≥ <?= $t['condition_value'] ?></td>
            <td><?= $t['bonus_amount'] ?></td>
            <td><?= $t['is_repeatable'] ? 'Да' : 'Нет' ?></td>
            <td><?= $t['is_active'] ? '✅ Активно' : '⏸️ Отключено' ?></td>
            <td>
                <a href="/admin/bonus-tasks/<?= $t['id'] ?>/edit" class="btn btn-secondary btn-sm">Изменить</a>
                <form method="POST" action="/admin/bonus-tasks/<?= $t['id'] ?>/delete" style="display:inline;"><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Удалить задание?')">Удалить</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
