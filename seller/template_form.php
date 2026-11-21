<?php
$templateId = $_GET['id'] ?? null;
$template = $templateId ? getTraderProductTemplateById($templateId, $_SESSION['user_id']) : null;
if ($templateId && !$template) { redirect('/seller/templates'); }

// Быстрое создание шаблона на основе уже существующего товара продавца —
// подставляем его поля как стартовые значения формы шаблона.
if (!$template && !empty($_GET['from_product'])) {
    $srcProduct = getTraderProductById((int)$_GET['from_product']);
    if ($srcProduct && (int)$srcProduct['user_id'] === (int)$_SESSION['user_id']) {
        $template = $srcProduct;
        $template['name'] = $srcProduct['title'];
    }
}

$pageTitle = $template && $templateId ? 'Редактировать шаблон' : 'Новый шаблон товара';
include __DIR__ . '/../templates/header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:4px;"><?= e($pageTitle) ?></h1>
<p style="color:var(--g-text-secondary);font-size:13px;margin-bottom:20px;">Заполните только те поля, которые обычно повторяются у ваших товаров. Пустые поля просто не подставятся при создании товара из этого шаблона.</p>

<form method="POST" action="/seller/templates/<?= $templateId ? $templateId . '/edit' : 'create' ?>" enctype="multipart/form-data">
    <div style="max-width:700px;display:grid;gap:16px;">
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Название шаблона *</label><input type="text" name="name" class="input" value="<?= e($template['name'] ?? '') ?>" placeholder="например, «Приватная сборка»" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Заготовка названия товара</label><input type="text" name="title" class="input" value="<?= e($template['title'] ?? '') ?>" placeholder="можно оставить пустым и ввести при создании товара"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Описание</label><textarea name="description" class="input" rows="6"><?= e($template['description'] ?? '') ?></textarea></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Категория</label>
            <select name="category_id" class="select">
                <option value="">Без категории</option>
                <?php foreach (getCategories() as $cat): ?><option value="<?= $cat['id'] ?>" <?= (($template['category_id'] ?? null) == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Цена (₽, 0 — бесплатно)</label><input type="number" name="price" class="input" step="0.01" value="<?= e($template['price'] ?? 0) ?>"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Ссылка на файл (облако)</label><input type="url" name="file_url" class="input" value="<?= e($template['file_url'] ?? '') ?>"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">YouTube URL</label><input type="url" name="youtube_url" class="input" value="<?= e($template['youtube_url'] ?? '') ?>"></div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Версия игры</label><input type="text" name="game_version" class="input" value="<?= e($template['game_version'] ?? '') ?>"></div>
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Размер файла</label><input type="text" name="file_size" class="input" value="<?= e($template['file_size'] ?? '') ?>" placeholder="например, 2.4 ГБ"></div>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Обложка по умолчанию</label><input type="file" name="cover" class="input" accept="image/*">
            <?php if (!empty($template['cover_path'])): ?><div style="margin-top:8px;"><img src="<?= e(UPLOAD_URL . $template['cover_path']) ?>" style="width:90px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--g-border);"></div><?php endif; ?>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Скриншоты по умолчанию</label>
            <?php $existingShots = $template ? (json_decode($template['screenshots'] ?? '[]', true) ?: []) : []; if ($existingShots): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                <?php foreach ($existingShots as $shot): ?><img src="<?= e(UPLOAD_URL . $shot) ?>" style="width:90px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--g-border);"><?php endforeach; ?>
            </div>
            <p style="font-size:12px;color:var(--g-text-secondary);margin-bottom:8px;">Уже загружено выше. Выбор новых файлов ниже добавит их дополнительно к существующим.</p>
            <?php endif; ?>
            <input type="file" name="screenshots[]" class="input" accept="image/*" multiple></div>
    </div>
    <div style="margin-top:24px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Сохранить шаблон</button>
        <a href="/seller/templates" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php include __DIR__ . '/../templates/footer.php'; ?>
