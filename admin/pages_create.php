<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO pages (title, slug, content, is_active, show_in_menu, menu_order, meta_title, meta_description, meta_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $_POST['title'], $_POST['slug'] ?: slugify($_POST['title']), $_POST['content'],
        isset($_POST['is_active']) ? 1 : 0, isset($_POST['show_in_menu']) ? 1 : 0,
        $_POST['menu_order'] ?? 0, $_POST['meta_title'] ?: null, $_POST['meta_description'] ?: null, $_POST['meta_keywords'] ?: null
    ]);
    redirect('/admin/pages');
}
$pageTitle = 'Новая страница';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Новая страница</h1>

<form method="POST">
    <div style="max-width:700px;display:grid;gap:16px;">
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Название *</label><input type="text" name="title" class="input" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Slug</label><input type="text" name="slug" class="input"></div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;font-size:14px;"><input type="checkbox" name="is_active" value="1" checked> Активна</label>
            <label style="display:flex;align-items:center;gap:6px;font-size:14px;"><input type="checkbox" name="show_in_menu" value="1"> Показывать в меню</label>
            <div style="display:flex;align-items:center;gap:6px;"><span style="font-size:14px;color:var(--g-text-secondary);">Порядок:</span><input type="number" name="menu_order" class="input" value="0" style="width:80px;"></div>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Содержимое (HTML) *</label><textarea name="content" class="input" rows="12" required></textarea></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Meta title</label><input type="text" name="meta_title" class="input"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Meta description</label><textarea name="meta_description" class="input" rows="2"></textarea></div>
    </div>
    <div style="margin-top:24px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="/admin/pages" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
