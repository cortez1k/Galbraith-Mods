<?php
$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare('SELECT * FROM materials WHERE id = ?');
$stmt->execute([$id]);
$material = $stmt->fetch();
if (!$material) redirect('/admin/materials');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cover = $material['cover_path'];
    if (!empty($_FILES['cover']['tmp_name']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $cover = uploadFile($_FILES['cover'], 'covers');
    }
    $screenshots = json_decode($material['screenshots'] ?? '[]', true);
    if (!empty($_FILES['screenshots']['tmp_name'][0])) {
        foreach ($_FILES['screenshots']['tmp_name'] as $i => $tmp) {
            if ($_FILES['screenshots']['error'][$i] === UPLOAD_ERR_OK) {
                $f = ['tmp_name' => $tmp, 'name' => $_FILES['screenshots']['name'][$i], 'error' => 0];
                $s = uploadFile($f, 'screenshots');
                if ($s) $screenshots[] = $s;
            }
        }
    }
    $publishAt = $material['publish_at'];
    if (array_key_exists('publish_at', $_POST)) {
        if (!empty($_POST['publish_at'])) {
            $ts = strtotime($_POST['publish_at']);
            $publishAt = ($ts && $ts > time()) ? date('Y-m-d H:i:s', $ts) : null;
        } else {
            $publishAt = null;
        }
    }
    $stmt = $db->prepare('UPDATE materials SET category_id=?, title=?, slug=?, description=?, access_type=?, price=?, discount_price=?, file_url=?, youtube_url=?, cover_path=?, screenshots=?, game_version=?, file_size=?, publish_at=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $stmt->execute([
        $_POST['category_id'], $_POST['title'], $_POST['slug'], $_POST['description'],
        $_POST['access_type'], $_POST['price'] ?: null, $_POST['discount_price'] ?: null,
        $_POST['file_url'], $_POST['youtube_url'] ?: null, $cover,
        $screenshots ? json_encode($screenshots) : null,
        $_POST['game_version'] ?: null, $_POST['file_size'] ?: null, $publishAt, $id
    ]);
    redirect('/admin/materials');
}

$pageTitle = 'Редактировать материал';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Редактировать материал</h1>

<form method="POST" enctype="multipart/form-data">
    <div style="max-width:700px;display:grid;gap:16px;">
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Название *</label><input type="text" name="title" class="input" value="<?= e($material['title']) ?>" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Slug *</label><input type="text" name="slug" class="input" value="<?= e($material['slug']) ?>" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Описание *</label><textarea name="description" class="input" rows="6" required><?= e($material['description']) ?></textarea></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Категория *</label>
            <select name="category_id" class="select" required>
                <?php foreach (getCategories() as $cat): ?><option value="<?= $cat['id'] ?>" <?= $material['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Тип</label>
                <select name="access_type" class="select"><option value="free" <?= $material['access_type']=='free'?'selected':'' ?>>Бесплатно</option><option value="paid" <?= $material['access_type']=='paid'?'selected':'' ?>>Платно</option></select>
            </div>
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Цена (₽)</label><input type="number" name="price" class="input" step="0.01" value="<?= e($material['price']) ?>"></div>
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Цена со скидкой</label><input type="number" name="discount_price" class="input" step="0.01" value="<?= e($material['discount_price']) ?>"></div>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Ссылка на файл *</label><input type="url" name="file_url" class="input" value="<?= e($material['file_url']) ?>" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">YouTube URL</label><input type="url" name="youtube_url" class="input" value="<?= e($material['youtube_url']) ?>"></div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Версия игры</label><input type="text" name="game_version" class="input" value="<?= e($material['game_version']) ?>"></div>
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Размер файла</label><input type="text" name="file_size" class="input" value="<?= e($material['file_size']) ?>"></div>
        </div>
        <div style="padding:14px;border:1px solid var(--g-border);border-radius:var(--g-radius-sm);background:var(--g-accent-dim);">
            <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">🚀 Отложенная публикация (премьера)</label>
            <input type="datetime-local" name="publish_at" class="input" value="<?= $material['publish_at'] ? date('Y-m-d\TH:i', strtotime($material['publish_at'])) : '' ?>">
            <div style="font-size:12px;color:var(--g-text-secondary);margin-top:6px;">
                <?php if ($material['publish_at'] && strtotime($material['publish_at']) > time()): ?>
                    Материал скрыт из каталога до <strong><?= date('d.m.Y H:i', strtotime($material['publish_at'])) ?></strong> и показывается как «Премьера» со счётчиком.
                <?php else: ?>
                    Материал опубликован. Укажите дату и время в будущем, чтобы снова скрыть его до премьеры. Очистите поле, чтобы держать материал опубликованным сразу.
                <?php endif; ?>
            </div>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Обложка</label><input type="file" name="cover" class="input" accept="image/*">
            <?php if ($material['cover_path']): ?><div style="margin-top:8px;"><img src="<?= e(UPLOAD_URL . $material['cover_path']) ?>" style="height:80px;border-radius:6px;"></div><?php endif; ?>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Скриншоты</label><input type="file" name="screenshots[]" class="input" accept="image/*" multiple></div>
    </div>
    <div style="margin-top:24px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="/admin/materials" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
