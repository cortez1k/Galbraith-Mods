<?php
$db = getDB();
$pages = $db->query('SELECT * FROM pages ORDER BY menu_order')->fetchAll();
$pageTitle = 'Страницы';
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:600;">Страницы</h1>
    <a href="/admin/pages/create" class="btn btn-primary">+ Создать</a>
</div>

<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Название</th><th>Slug</th><th>В меню</th><th>Статус</th><th>Порядок</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($pages as $p): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><a href="/page/<?= e($p['slug']) ?>" target="_blank" style="color:var(--g-accent-light);"><?= e($p['title']) ?></a></td>
                <td><?= e($p['slug']) ?></td>
                <td><?= $p['show_in_menu'] ? 'Да' : 'Нет' ?></td>
                <td><?= $p['is_active'] ? 'Активна' : 'Неактивна' ?></td>
                <td><?= $p['menu_order'] ?></td>
                <td>
                    <a href="/admin/pages/<?= $p['id'] ?>/edit" class="btn btn-secondary btn-sm">Изменить</a>
                    <a href="/admin/pages/<?= $p['id'] ?>/delete" class="btn btn-secondary btn-sm btn-danger" onclick="return confirm('Удалить?')">Удалить</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
