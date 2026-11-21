<?php
$db = getDB();
$news = $db->query('SELECT * FROM news ORDER BY is_pinned DESC, sort_order ASC, created_at DESC')->fetchAll();
$pageTitle = 'Новости';
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:600;">Новости</h1>
    <a href="/admin/news/create" class="btn btn-primary">+ Добавить</a>
</div>

<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Заголовок</th><th>Закреп</th><th>Порядок</th><th>Дата</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($news as $n): ?>
            <tr>
                <td><?= $n['id'] ?></td>
                <td><?= e($n['title']) ?></td>
                <td><?= $n['is_pinned'] ? '✅' : '—' ?></td>
                <td><?= $n['sort_order'] ?></td>
                <td><?= date('d.m.Y', strtotime($n['created_at'])) ?></td>
                <td>
                    <a href="/admin/news/<?= $n['id'] ?>/edit" class="btn btn-secondary btn-sm">Изменить</a>
                    <a href="/admin/news/<?= $n['id'] ?>/delete" class="btn btn-secondary btn-sm btn-danger" onclick="return confirm('Удалить?')">Удалить</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
