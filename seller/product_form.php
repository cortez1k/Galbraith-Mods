<?php
$productId = $_GET['id'] ?? null;
$product = $productId ? getTraderProductById($productId) : null;
if ($productId && (!$product || (int)$product['user_id'] !== (int)$_SESSION['user_id'])) { redirect('/seller/products'); }

// При создании нового товара можно стартовать с заготовки из шаблона —
// подставляем сохранённые значения полей, чтобы не вводить их заново.
$template = null;
$templateId = null;
if (!$product && !empty($_GET['template_id'])) {
    $templateId = (int)$_GET['template_id'];
    $template = getTraderProductTemplateById($templateId, $_SESSION['user_id']);
    if (!$template) { $templateId = null; }
}
$fieldSource = $template ?: $product;
$myTemplates = !$product ? getTraderProductTemplates($_SESSION['user_id']) : [];

$pageTitle = $product ? 'Редактировать товар' : 'Новый товар';
include __DIR__ . '/../templates/header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:4px;"><?= e($pageTitle) ?></h1>
<?php if (!$product && $myTemplates): ?>
<div class="card" style="padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <label style="font-size:13px;color:var(--g-text-secondary);white-space:nowrap;">Заполнить из шаблона:</label>
    <select class="select" style="max-width:280px;" onchange="if(this.value){window.location.href='/seller/products/create?template_id='+this.value;}else{window.location.href='/seller/products/create';}">
        <option value="">— не использовать —</option>
        <?php foreach ($myTemplates as $t): ?><option value="<?= $t['id'] ?>" <?= $templateId == $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
    </select>
    <a href="/seller/templates" class="btn btn-secondary btn-sm">Управлять шаблонами</a>
</div>
<?php endif; ?>
<?php if ($product): ?>
<p style="color:var(--g-text-secondary);font-size:13px;margin-bottom:20px;">
    Статус: <strong><?= e(productStatusLabel($product['status'])) ?></strong>
    <?php if ($product['status'] === 'rejected' && $product['moderation_comment']): ?><br>Причина отклонения: <?= e($product['moderation_comment']) ?><?php endif; ?>
    · версия <?= $product['version'] ?>
    · <a href="/seller/templates/create?from_product=<?= $product['id'] ?>" style="color:var(--g-accent-light);">Сохранить как шаблон</a>
</p>
<?php endif; ?>

<form method="POST" action="/seller/products/<?= $product ? $product['id'] . '/edit' : 'create' ?>" enctype="multipart/form-data">
    <div style="max-width:700px;display:grid;gap:16px;">
        <?php if ($templateId): ?><input type="hidden" name="template_id" value="<?= (int)$templateId ?>"><?php endif; ?>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Название *</label><input type="text" name="title" class="input" value="<?= e($fieldSource['title'] ?? '') ?>" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Slug</label><input type="text" name="slug" class="input" value="<?= e($product['slug'] ?? '') ?>"></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Описание *</label><textarea name="description" class="input" rows="6" required><?= e($fieldSource['description'] ?? '') ?></textarea></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Категория</label>
            <select name="category_id" class="select">
                <option value="">Без категории</option>
                <?php foreach (getCategories() as $cat): ?><option value="<?= $cat['id'] ?>" <?= (($fieldSource['category_id'] ?? null) == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Цена (₽, 0 — бесплатно)</label><input type="number" name="price" class="input" step="0.01" value="<?= e($fieldSource['price'] ?? 0) ?>"></div>
        <div style="padding:12px 14px;border-radius:10px;background:rgba(232,28,90,.08);border:1px solid rgba(232,28,90,.2);font-size:13px;color:var(--g-text-secondary);">⚡ <strong style="color:var(--g-text);">Автовыдача:</strong> после создания товара её можно настроить на <a href="/seller/products" style="color:var(--g-accent-light);font-weight:600;">https://galbraith-yt.ru/seller/products</a>.</div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Ссылка на файл (облако) *</label><input type="url" name="file_url" class="input" value="<?= e($fieldSource['file_url'] ?? '') ?>" required></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">YouTube URL</label><input type="url" name="youtube_url" class="input" value="<?= e($fieldSource['youtube_url'] ?? '') ?>"></div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Версия игры</label><input type="text" name="game_version" class="input" value="<?= e($fieldSource['game_version'] ?? '') ?>"></div>
            <div style="flex:1;min-width:200px;"><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Размер файла</label><input type="text" name="file_size" class="input" value="<?= e($fieldSource['file_size'] ?? '') ?>" placeholder="например, 2.4 ГБ"></div>
        </div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Обложка <?= ($product || ($template && $template['cover_path'])) ? '' : '*' ?></label>
            <?php if ($template && $template['cover_path']): ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <img src="<?= e(UPLOAD_URL . $template['cover_path']) ?>" style="width:90px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--g-border);">
                <span style="font-size:12px;color:var(--g-text-secondary);">Обложка из шаблона. Выберите файл ниже, только если хотите заменить её.</span>
            </div>
            <input type="hidden" name="template_cover" value="<?= e($template['cover_path']) ?>">
            <?php endif; ?>
            <input type="file" name="cover" class="input" accept="image/*" <?= ($product || ($template && $template['cover_path'])) ? '' : 'required' ?>></div>
        <div><label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Скриншоты</label>
            <?php $existingShots = $fieldSource ? (json_decode($fieldSource['screenshots'] ?? '[]', true) ?: []) : []; if ($existingShots): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                <?php foreach ($existingShots as $shot): ?><img src="<?= e(UPLOAD_URL . $shot) ?>" style="width:90px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--g-border);"><?php endforeach; ?>
            </div>
            <p style="font-size:12px;color:var(--g-text-secondary);margin-bottom:8px;">Уже загружено выше<?= $template ? ' (из шаблона)' : '' ?>. Выбор новых файлов ниже добавит их дополнительно к существующим.</p>
            <?php if ($template && $existingShots): ?><input type="hidden" name="template_screenshots" value="<?= e(json_encode($existingShots)) ?>"><?php endif; ?>
            <?php endif; ?>
            <input type="file" name="screenshots[]" class="input" accept="image/*" multiple></div>

        <?php if ($product): ?>
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;"><input type="checkbox" name="is_active" <?= $product['is_active'] ? 'checked' : '' ?>> Товар активен (виден на площадке, если одобрен)</label>
        <?php endif; ?>
    </div>
    <div style="margin-top:24px;display:flex;gap:10px;">
        <?php if (!$product): ?>
            <button type="submit" name="action" value="submit" class="btn btn-primary">Отправить на модерацию</button>
            <button type="submit" name="action" value="draft" class="btn btn-secondary">Сохранить как черновик</button>
        <?php else: ?>
            <button type="submit" class="btn btn-primary">Сохранить<?= in_array($product['status'], ['approved','rejected']) ? ' и отправить на модерацию' : '' ?></button>
        <?php endif; ?>
        <a href="/seller/products" class="btn btn-secondary">Отмена</a>
    </div>
</form>

<?php include __DIR__ . '/../templates/footer.php'; ?>
