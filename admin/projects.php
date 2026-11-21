<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $stmt = $db->prepare('INSERT INTO projects (title, url, sort_order) VALUES (?, ?, ?)');
        $stmt->execute([$_POST['title'], $_POST['url'], $_POST['sort_order'] ?? 0]);
        redirect('/admin/projects');
    }
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $db->prepare('DELETE FROM projects WHERE id = ?')->execute([$_POST['id']]);
        redirect('/admin/projects');
    }
}

$pageTitle = 'Проекты';
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="max-width: 700px;">
    <h1 style="font-size: 22px; font-weight: 600; margin-bottom: 20px;">Управление проектами</h1>

    <form method="POST" style="margin-bottom: 32px;">
        <div style="max-width: 700px; display: grid; gap: 16px;">
            <div><label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">Название *</label><input type="text" name="title" class="input" required></div>
            <div><label style="display:block; font-size: 13px; color: var(--g-text-secondary); margin-bottom: 6px;">URL *</label><input type="text" name="url" class="input" required></div>
            <div style="display:flex; align-items:center; gap:6px;"><span style="font-size:14px; color:var(--g-text-secondary);">Порядок:</span><input type="number" name="sort_order" class="input" value="0" style="width:80px;"></div>
        </div>
        <div style="margin-top: 24px; display: flex; gap: 10px;">
            <button type="submit" name="action" value="add" class="btn btn-primary">Добавить</button>
            <a href="/admin/projects" class="btn btn-secondary">Отмена</a>
        </div>
    </form>

    <h2 style="font-size: 20px; font-weight: 600; margin: 32px 0 16px;">Список проектов</h2>
    <?php
    $db = getDB();
    $projects = $db->query('SELECT * FROM projects ORDER BY sort_order, id')->fetchAll();
    if (empty($projects)): ?>
        <p style="color: var(--g-text-secondary);">Проекты не добавлены. Используйте форму выше.</p>
    <?php else: ?>
    <table class="admin-table" style="width: 100%;">
        <thead><tr><th>#</th><th>Название</th><th>Ссылка</th><th>Действия</th></tr></thead>
        <tbody>
            <?php foreach ($projects as $p): ?>
            <tr>
                <td><?= $p['sort_order'] ?? $p['id'] ?></td>
                <td><?= e($p['title']) ?></td>
                <td><a href="<?= e($p['url']) ?>" target="_blank" style="color: var(--g-accent-light);">Перейти</a></td>
                <td>
                    <a href="/admin/projects/delete/<?= $p['id'] ?>" class="btn btn-secondary btn-sm" style="color: var(--g-danger);">Удалить</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>

<a href="/admin/projects/delete/<?= $id ?>" onclick="return confirm('Удалить проект?')">Удалить</a>
<?php endif; ?>