<?php
$db = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id'])) {
        $stmt = $db->prepare('UPDATE tasks SET title=?, url=?, is_active=?, sort_order=? WHERE id=?');
        $stmt->execute([$_POST['title'], $_POST['url'], isset($_POST['is_active']) ? 1 : 0, $_POST['sort_order'], $_POST['id']]);
    } else {
        $stmt = $db->prepare('INSERT INTO tasks (title, url, sort_order) VALUES (?, ?, ?)');
        $stmt->execute([$_POST['title'], $_POST['url'], $_POST['sort_order']]);
    }
    redirect('/admin/tasks');
}
$tasks = $db->query('SELECT * FROM tasks ORDER BY sort_order')->fetchAll();
$pageTitle = 'Задания';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Задания для доступа</h1>

<form method="POST" style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
    <input type="text" name="title" class="input" placeholder="Название" required style="flex:1;min-width:200px;">
    <input type="url" name="url" class="input" placeholder="URL" required style="flex:1;min-width:200px;">
    <input type="number" name="sort_order" class="input" placeholder="Порядок" value="0" style="width:100px;">
    <button type="submit" class="btn btn-primary">Добавить</button>
</form>

<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($tasks as $task): ?>
    <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:16px;display:flex;align-items:center;gap:14px;">
        <div style="flex:1;">
            <div style="font-weight:600;"><?= e($task['title']) ?></div>
            <div style="font-size:12px;color:var(--g-text-secondary);"><?= e($task['url']) ?></div>
        </div>
        <form method="POST" style="display:flex;align-items:center;gap:8px;">
            <input type="hidden" name="id" value="<?= $task['id'] ?>">
            <input type="hidden" name="title" value="<?= e($task['title']) ?>">
            <input type="hidden" name="url" value="<?= e($task['url']) ?>">
            <input type="hidden" name="sort_order" value="<?= $task['sort_order'] ?>">
            <label style="display:flex;align-items:center;gap:6px;font-size:14px;">
                <input type="checkbox" name="is_active" value="1" <?= $task['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()"> Активно
            </label>
        </form>
        <a href="/admin/tasks/<?= $task['id'] ?>/delete" class="btn btn-secondary btn-sm btn-danger" onclick="return confirm('Удалить?')">Удалить</a>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
