<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $image = uploadFile($_FILES['image'] ?? null, 'screenshots');
    $stmt = $db->prepare('INSERT INTO news (title, content, image_url, url, is_pinned, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $_POST['title'] ?? '',
        $_POST['content'] ?? '',
        $image,
        $_POST['url'] ?: null,
        isset($_POST['is_pinned']) ? 1 : 0,
        $_POST['sort_order'] ?? 0
    ]);
    redirect('/admin/news');
}
$pageTitle = 'Новая новость';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Новая новость</h1>

<form method="POST" enctype="multipart/form-data">
    <div style="max-width:700px;display:grid;gap:16px;">
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Заголовок *</label><input type="text" name="title" class="input" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Текст (HTML)</label><textarea name="content" class="input" rows="4"></textarea></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Ссылка (URL)</label><input type="url" name="url" class="input" placeholder="https://..."></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Изображение</label><input type="file" name="image" class="input" accept="image/*"></div>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;font-size:14px;"><input type="checkbox" name="is_pinned" value="1"> Закрепить</label>
            <div style="display:flex;align-items:center;gap:6px;"><span style="font-size:14px;color:var(--g-text-secondary);">Порядок:</span><input type="number" name="sort_order" class="input" value="0" style="width:80px;"></div>
        </div>
    </div>
    <div style="margin-top:24px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="/admin/news" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
