<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $cover = uploadFile($_FILES['cover'] ?? null, 'covers');
    $screenshots = [];
    if (!empty($_FILES['screenshots']['tmp_name'][0])) {
        foreach ($_FILES['screenshots']['tmp_name'] as $i => $tmp) {
            if ($_FILES['screenshots']['error'][$i] === UPLOAD_ERR_OK) {
                $f = ['tmp_name' => $tmp, 'name' => $_FILES['screenshots']['name'][$i], 'error' => 0];
                $s = uploadFile($f, 'screenshots');
                if ($s) $screenshots[] = $s;
            }
        }
    }
    $publishAt = null;
    if (!empty($_POST['publish_at'])) {
        $ts = strtotime($_POST['publish_at']);
        if ($ts && $ts > time()) $publishAt = date('Y-m-d H:i:s', $ts);
    }
    $stmt = $db->prepare('INSERT INTO materials (category_id, title, slug, description, access_type, price, discount_price, file_url, youtube_url, cover_path, screenshots, game_version, file_size, publish_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $_POST['category_id'] ?? 1,
        $_POST['title'] ?? '',
        $_POST['slug'] ?: slugify($_POST['title'] ?? ''),
        $_POST['description'] ?? '',
        $_POST['access_type'] ?? 'free',
        $_POST['price'] ?: null,
        $_POST['discount_price'] ?: null,
        $_POST['file_url'] ?? '',
        $_POST['youtube_url'] ?: null,
        $cover,
        $screenshots ? json_encode($screenshots) : null,
        $_POST['game_version'] ?: null,
        $_POST['file_size'] ?: null,
        $publishAt,
    ]);
    redirect('/admin/materials');
}
$pageTitle = 'Новый материал';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Новый материал</h1>

<form method="POST" enctype="multipart/form-data">
    <div style="max-width:700px;display:grid;gap:16px;">
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Название *</label><input type="text" name="title" class="input" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Slug</label><input type="text" name="slug" class="input"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Описание *</label><textarea name="description" class="input" rows="6" required></textarea></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Категория *</label>
            <select name="category_id" class="select" required>
                <?php foreach (getCategories() as $cat): ?><option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Тип</label>
                <select name="access_type" class="select"><option value="free">Бесплатно</option><option value="paid">Платно</option></select>
            </div>
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Цена (₽)</label><input type="number" name="price" class="input" step="0.01"></div>
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Цена со скидкой</label><input type="number" name="discount_price" class="input" step="0.01"></div>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Ссылка на файл *</label><input type="url" name="file_url" class="input" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">YouTube URL</label><input type="url" name="youtube_url" class="input"></div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Версия игры</label><input type="text" name="game_version" class="input"></div>
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Размер файла</label><input type="text" name="file_size" class="input" placeholder="например, 2.4 ГБ"></div>
        </div>
        <div style="padding:14px;border:1px solid var(--g-border);border-radius:var(--g-radius-sm);background:var(--g-accent-dim);">
            <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">🚀 Отложенная публикация (премьера)</label>
            <input type="datetime-local" name="publish_at" class="input">
            <div style="font-size:12px;color:var(--g-text-secondary);margin-top:6px;">Если указать дату и время в будущем — материал не будет виден в каталоге до этого момента. На странице материала будет показана «Премьера» со счётчиком обратного отсчёта. Оставьте пустым, чтобы опубликовать сразу.</div>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Обложка *</label><input type="file" name="cover" class="input" accept="image/*" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Скриншоты</label><input type="file" name="screenshots[]" class="input" accept="image/*" multiple></div>
    </div>
    <div style="margin-top:24px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="/admin/materials" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
