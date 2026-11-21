<?php
$db = getDB();
$search = $_GET['search'] ?? '';
$catFilter = $_GET['category'] ?? '';
$sql = 'SELECT m.*, c.name as category_name FROM materials m LEFT JOIN categories c ON m.category_id = c.id WHERE 1=1';
$params = [];
if ($search) { $sql .= ' AND m.title LIKE ?'; $params[] = '%'.$search.'%'; }
if ($catFilter) { $sql .= ' AND m.category_id = ?'; $params[] = $catFilter; }
$sql .= ' ORDER BY m.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();
$pageTitle = 'Материалы';
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <h1 style="font-size:22px;font-weight:600;">Материалы</h1>
    <a href="/admin/materials/create" class="btn btn-primary">+ Добавить</a>
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <input type="text" name="search" value="<?= e($search) ?>" class="input" placeholder="Поиск..." style="flex:1;min-width:200px;">
    <select name="category" class="select" style="min-width:160px;" onchange="this.form.submit()">
        <option value="">Все категории</option>
        <?php foreach (getCategories() as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Название</th><th>Категория</th><th>Тип</th><th>Цена</th><th>Статус</th><th>Скачиваний</th><th>Дата</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($materials as $m): $isPremiere = !empty($m['publish_at']) && strtotime($m['publish_at']) > time(); ?>
            <tr>
                <td><?= $m['id'] ?></td>
                <td><a href="/mod/<?= e($m['slug']) ?>" target="_blank" style="color:var(--g-accent-light);"><?= e($m['title']) ?></a></td>
                <td><?= e($m['category_name'] ?? '—') ?></td>
                <td><?= $m['access_type'] === 'free' ? 'Бесплатно' : 'Платно' ?></td>
                <td><?= formatPrice($m) ?></td>
                <td><?php if ($isPremiere): ?><span class="card-badge badge-premiere" style="position:static;display:inline-block;">🚀 Премьера <?= date('d.m.Y H:i', strtotime($m['publish_at'])) ?></span><?php else: ?><span style="color:var(--g-success);font-weight:600;font-size:12.5px;">● Опубликован</span><?php endif; ?></td>
                <td><?= number_format($m['downloads_count'], 0, ',', ' ') ?></td>
                <td><?= date('d.m.Y', strtotime($m['created_at'])) ?></td>
                <td>
                    <a href="/admin/materials/<?= $m['id'] ?>/edit" class="btn btn-secondary btn-sm">Изменить</a>
                    <a href="/admin/materials/<?= $m['id'] ?>/delete" class="btn btn-secondary btn-sm btn-danger" onclick="return confirm('Удалить?')">Удалить</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
