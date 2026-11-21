<?php
$id = (int)($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT p.*, u.name as seller_name FROM trader_products p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) redirect('/admin/moderation');
$history = getProductHistory($id);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle = 'Модерация: ' . $product['title'];
include __DIR__ . '/../templates/admin_header.php';
?>

<?php if ($flash): ?><div style="background:rgba(255,80,80,0.1);border:1px solid rgba(255,80,80,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <span>Статус: <strong><?= e(productStatusLabel($product['status'])) ?></strong></span>
    <span>· Версия <?= $product['version'] ?></span>
    <span>· <a href="/product/<?= e($product['slug']) ?>" target="_blank" style="color:var(--g-accent-light);">Предпросмотр →</a></span>
</div>

<?php if ($product['status'] === 'on_moderation'): ?>
<div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
    <form method="POST" action="/admin/moderation/<?= $id ?>/approve"><button type="submit" class="btn btn-primary" onclick="return confirm('Одобрить товар?')">✅ Одобрить</button></form>
    <form method="POST" action="/admin/moderation/<?= $id ?>/reject" style="display:flex;gap:8px;">
        <input type="text" name="comment" class="input" placeholder="Причина отклонения (обязательно)" style="width:320px;" required>
        <button type="submit" class="btn btn-danger">❌ Отклонить</button>
    </form>
</div>
<?php endif; ?>

<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:14px;">Редактировать перед публикацией</h3>
    <form method="POST" action="/admin/moderation/<?= $id ?>/edit" style="display:grid;gap:12px;max-width:600px;">
        <div><label style="font-size:12px;color:var(--g-text-secondary);">Название</label><input type="text" name="title" class="input" value="<?= e($product['title']) ?>"></div>
        <div><label style="font-size:12px;color:var(--g-text-secondary);">Описание</label><textarea name="description" class="input" rows="4"><?= e($product['description']) ?></textarea></div>
        <div><label style="font-size:12px;color:var(--g-text-secondary);">Категория</label>
            <select name="category_id" class="select">
                <option value="">Без категории</option>
                <?php foreach (getCategories() as $cat): ?><option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label style="font-size:12px;color:var(--g-text-secondary);">Цена</label><input type="number" step="0.01" name="price" class="input" value="<?= e($product['price']) ?>"></div>
        <div><label style="font-size:12px;color:var(--g-text-secondary);">Ссылка на файл</label><input type="url" name="file_url" class="input" value="<?= e($product['file_url']) ?>"></div>
        <div><label style="font-size:12px;color:var(--g-text-secondary);">YouTube</label><input type="url" name="youtube_url" class="input" value="<?= e($product['youtube_url']) ?>"></div>
        <div style="display:flex;gap:12px;">
            <div style="flex:1;"><label style="font-size:12px;color:var(--g-text-secondary);">Версия игры</label><input type="text" name="game_version" class="input" value="<?= e($product['game_version']) ?>"></div>
            <div style="flex:1;"><label style="font-size:12px;color:var(--g-text-secondary);">Размер</label><input type="text" name="file_size" class="input" value="<?= e($product['file_size']) ?>"></div>
        </div>
        <button type="submit" class="btn btn-secondary" style="width:fit-content;">Сохранить правки (без уведомления продавца)</button>
    </form>
</div>

<?php if ((float)$product['price'] <= 0): ?>
<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:6px;">Задания для получения (бесплатный товар)</h3>
    <p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:14px;">Глобальные задания (из «Бонусных заданий») действуют для всех бесплатных товаров всегда. Здесь можно добавить дополнительные задания именно для этого товара.</p>
    <?php $allTasks = getDB()->query('SELECT * FROM tasks WHERE is_active = 1 ORDER BY sort_order')->fetchAll(); $assignedIds = array_column(getProductTasks($id), 'id'); ?>
    <?php if (!$allTasks): ?>
        <p style="font-size:13px;color:var(--g-text-secondary);">Активных заданий в системе пока нет.</p>
    <?php else: ?>
    <form method="POST" action="/admin/moderation/<?= $id ?>/tasks">
        <div style="display:grid;gap:8px;margin-bottom:14px;">
            <?php foreach ($allTasks as $t): ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;">
                    <input type="checkbox" name="task_ids[]" value="<?= $t['id'] ?>" <?= in_array($t['id'], $assignedIds) ? 'checked' : '' ?>>
                    <?= e($t['title']) ?> <span style="color:var(--g-text-secondary);font-size:12px;">(<?= e($t['url']) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-secondary">Сохранить задания для товара</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="padding:20px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:14px;">История изменений</h3>
    <?php if (!$history): ?>
        <p style="color:var(--g-text-secondary);font-size:14px;">Изменений пока не было</p>
    <?php else: ?>
        <?php foreach ($history as $h): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--g-border);">
            <div style="font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;"><?= e($h['user_name'] ?? 'Система') ?> · <?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></div>
            <?php foreach ($h['changes_decoded'] as $field => $diff): ?>
                <div style="font-size:13px;"><strong><?= e($field) ?>:</strong> <span style="color:#FF3B30;text-decoration:line-through;"><?= e(mb_substr((string)$diff['old'], 0, 60)) ?></span> → <span style="color:#34C759;"><?= e(mb_substr((string)$diff['new'], 0, 60)) ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
