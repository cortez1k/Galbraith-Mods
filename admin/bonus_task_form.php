<?php
$taskId = $_GET['id'] ?? null;
$task = null;
if ($taskId) {
    $stmt = getDB()->prepare('SELECT * FROM bonus_tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task) redirect('/admin/bonus-tasks');
}
$conditionTypes = [
    'orders_completed' => 'Количество завершённых заказов',
    'positive_reviews' => 'Количество положительных отзывов',
    'active_products' => 'Количество активных товаров',
    'referrals_count' => 'Количество привлечённых пользователей',
    'revenue_total' => 'Суммарная выручка (₽)',
    'downloads_admin_materials' => 'Скачивания материалов администратора',
    'purchases_admin_materials' => 'Покупки материалов администратора',
];
$pageTitle = $task ? 'Редактировать задание' : 'Новое задание';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;"><?= e($pageTitle) ?></h1>

<form method="POST" action="<?= $task ? '/admin/bonus-tasks/' . $task['id'] . '/edit' : '/admin/bonus-tasks/create' ?>" style="max-width:600px;display:grid;gap:16px;">
    <div><label style="font-size:13px;color:var(--g-text-secondary);">Название *</label><input type="text" name="title" class="input" value="<?= e($task['title'] ?? '') ?>" required></div>
    <div><label style="font-size:13px;color:var(--g-text-secondary);">Описание</label><textarea name="description" class="input" rows="3"><?= e($task['description'] ?? '') ?></textarea></div>
    <div><label style="font-size:13px;color:var(--g-text-secondary);">Тип условия</label>
        <select name="condition_type" class="select">
            <?php foreach ($conditionTypes as $key => $label): ?><option value="<?= $key ?>" <?= ($task['condition_type'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div><label style="font-size:13px;color:var(--g-text-secondary);">Значение условия (порог)</label><input type="number" name="condition_value" class="input" value="<?= e($task['condition_value'] ?? 1) ?>" required></div>
    <div><label style="font-size:13px;color:var(--g-text-secondary);">Бонусов за выполнение</label><input type="number" step="0.01" name="bonus_amount" class="input" value="<?= e($task['bonus_amount'] ?? 1) ?>" required></div>
    <div><label style="font-size:13px;color:var(--g-text-secondary);">Срок выполнения, дней (необязательно)</label><input type="number" name="deadline_days" class="input" value="<?= e($task['deadline_days'] ?? '') ?>"></div>
    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_active" <?= ($task['is_active'] ?? 1) ? 'checked' : '' ?>> Активно</label>
    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_repeatable" <?= !empty($task['is_repeatable']) ? 'checked' : '' ?>> Можно выполнять многократно (прогресс сбрасывается)</label>
    <button type="submit" class="btn btn-primary" style="width:fit-content;">Сохранить</button>
</form>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
