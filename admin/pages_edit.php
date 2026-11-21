<?php
$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare('SELECT * FROM pages WHERE id = ?');
$stmt->execute([$id]);
$page = $stmt->fetch();
if (!$page) redirect('/admin/pages');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare('UPDATE pages SET title=?, slug=?, content=?, is_active=?, show_in_menu=?, menu_order=?, meta_title=?, meta_description=?, meta_keywords=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $stmt->execute([
        $_POST['title'], $_POST['slug'], $_POST['content'],
        isset($_POST['is_active']) ? 1 : 0, isset($_POST['show_in_menu']) ? 1 : 0,
        $_POST['menu_order'] ?? 0, $_POST['meta_title'] ?: null, $_POST['meta_description'] ?: null, $_POST['meta_keywords'] ?: null, $id
    ]);
    redirect('/admin/pages');
}

$pageTitle = 'Редактировать страницу';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Редактировать страницу</h1>

<form method="POST">
    <div style="max-width:700px;display:grid;gap:16px;">
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Название *</label><input type="text" name="title" class="input" value="<?= e($page['title']) ?>" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Slug *</label><input type="text" name="slug" class="input" value="<?= e($page['slug']) ?>" required></div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;font-size:14px;"><input type="checkbox" name="is_active" value="1" <?= $page['is_active'] ? 'checked' : '' ?>> Активна</label>
            <label style="display:flex;align-items:center;gap:6px;font-size:14px;"><input type="checkbox" name="show_in_menu" value="1" <?= $page['show_in_menu'] ? 'checked' : '' ?>> Показывать в меню</label>
            <div style="display:flex;align-items:center;gap:6px;"><span style="font-size:14px;color:var(--g-text-secondary);">Порядок:</span><input type="number" name="menu_order" class="input" value="<?= $page['menu_order'] ?>" style="width:80px;"></div>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Содержимое (HTML) *</label><textarea name="content" class="input" rows="12" required><?= e($page['content']) ?></textarea></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Meta title</label><input type="text" name="meta_title" class="input" value="<?= e($page['meta_title']) ?>"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Meta description</label><textarea name="meta_description" class="input" rows="2"><?= e($page['meta_description']) ?></textarea></div>
    </div>
    <div style="margin-top:24px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="/admin/pages" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
