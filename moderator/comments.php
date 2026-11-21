<?php
$db = getDB();
$comments = $db->query('SELECT c.*, m.title as material_title, m.slug as material_slug FROM comments c LEFT JOIN materials m ON c.material_id = m.id ORDER BY c.created_at DESC')->fetchAll();
$pageTitle = 'Комментарии';
include __DIR__ . '/../templates/moderator_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Комментарии</h1>

<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Автор</th><th>Текст</th><th>Материал</th><th>Дата</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($comments as $c): ?>
            <tr>
                <td>#<?= $c['id'] ?></td>
                <td><?= e($c['author_name']) ?></td>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($c['content']) ?></td>
                <td><a href="/mod/<?= e($c['material_slug'] ?? '') ?>" target="_blank" style="color:var(--g-accent-light);"><?= e($c['material_title'] ?? '—') ?></a></td>
                <td><?= date('d.m.Y H:i', strtotime($c['created_at'])) ?></td>
                <td>
                    <a href="/moderator/comments/<?= $c['id'] ?>/delete" class="btn btn-secondary btn-sm btn-danger" onclick="return confirm('Удалить комментарий?')">Удалить</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../templates/moderator_footer.php'; ?>
