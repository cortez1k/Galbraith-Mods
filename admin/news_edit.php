<?php
$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare('SELECT * FROM news WHERE id = ?');
$stmt->execute([$id]);
$n = $stmt->fetch();
if (!$n) redirect('/admin/news');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $image = $n['image_url'];
    if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = uploadFile($_FILES['image'], 'screenshots');
    }
    $stmt = $db->prepare('UPDATE news SET title=?, content=?, image_url=?, url=?, is_pinned=?, sort_order=? WHERE id=?');
    $stmt->execute([
        $_POST['title'], $_POST['content'], $image,
        $_POST['url'] ?: null,
        isset($_POST['is_pinned']) ? 1 : 0,
        $_POST['sort_order'] ?? 0, $id
    ]);
    redirect('/admin/news');
}

$pageTitle = 'Редактировать новость';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Редактировать новость</h1>

<form method="POST" enctype="multipart/form-data">
    <div style="max-width:700px;display:grid;gap:16px;">
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Заголовок *</label><input type="text" name="title" class="input" value="<?= e($n['title']) ?>" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Текст (HTML)</label><textarea name="content" class="input" rows="4"><?= e($n['content']) ?></textarea></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Ссылка (URL)</label><input type="url" name="url" class="input" value="<?= e($n['url']) ?>"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Изображение</label><input type="file" name="image" class="input" accept="image/*">
            <?php if ($n['image_url']): ?><div style="margin-top:8px;"><img src="<?= e(UPLOAD_URL . $n['image_url']) ?>" style="height:80px;border-radius:6px;"></div><?php endif; ?>
        </div>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;align-items:center;gap:6px;font-size:14px;"><input type="checkbox" name="is_pinned" value="1" <?= $n['is_pinned'] ? 'checked' : '' ?>> Закрепить</label>
            <div style="display:flex;align-items:center;gap:6px;"><span style="font-size:14px;color:var(--g-text-secondary);">Порядок:</span><input type="number" name="sort_order" class="input" value="<?= $n['sort_order'] ?>" style="width:80px;"></div>
        </div>
    </div>
    <div style="margin-top:24px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="/admin/news" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
