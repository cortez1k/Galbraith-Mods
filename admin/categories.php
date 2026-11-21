<?php
$db = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
    $stmt->execute([$_POST['name'], slugify($_POST['name'])]);
    redirect('/admin/categories');
}
$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$pageTitle = 'Категории';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Категории</h1>

<form method="POST" style="display:flex;gap:10px;margin-bottom:24px;">
    <input type="text" name="name" class="input" placeholder="Название новой категории" required style="flex:1;">
    <button type="submit" class="btn btn-primary">Добавить</button>
</form>

<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Название</th><th>Slug</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?= $cat['id'] ?></td>
                <td><?= e($cat['name']) ?></td>
                <td><?= e($cat['slug']) ?></td>
                <td>
                    <?php if ($cat['slug'] !== 'bez-kategorii'): ?>
                    <a href="/admin/categories/<?= $cat['id'] ?>/delete" class="btn btn-secondary btn-sm btn-danger" onclick="return confirm('Удалить?')">Удалить</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
