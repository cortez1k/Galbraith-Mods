<?php
$stmt = getDB()->query("SELECT p.*, u.name as seller_name FROM trader_products p LEFT JOIN users u ON p.user_id = u.id
    WHERE p.status = 'approved' ORDER BY p.is_pinned DESC, p.is_active DESC, p.sort_order ASC, p.created_at DESC");
$products = $stmt->fetchAll();
$pinnedCount = getPinnedCount();
$maxPinned = (int)setting('max_pinned_products', 5);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle = 'Управление маркетплейсом';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:8px;">Управление маркетплейсом</h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;">Закреплено: <?= $pinnedCount ?> / <?= $maxPinned ?>. Закреплённые товары всегда отображаются первыми. Скрытые товары не отображаются на витрине и недоступны для покупки, но остаются у продавца.</p>

<?php if ($flash): ?><div style="background:rgba(255,80,80,0.1);border:1px solid rgba(255,80,80,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>#</th><th>Товар</th><th>Продавец</th><th>Видимость</th><th>Порядок</th><th></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
        <tr style="<?= $p['is_pinned'] ? 'background:rgba(255,159,10,0.06);' : (!$p['is_active'] ? 'opacity:0.55;' : '') ?>">
            <td><?= $p['id'] ?></td>
            <td><?= e($p['title']) ?> <?= $p['is_pinned'] ? '📌' : '' ?></td>
            <td><?= e($p['seller_name']) ?></td>
            <td><?= $p['is_active'] ? '👁 Виден' : '🙈 Скрыт' ?></td>
            <td>
                <?php if ($p['is_active'] && !$p['is_pinned']): ?>
                <form method="POST" action="/admin/marketplace/<?= $p['id'] ?>/move" style="display:inline;"><input type="hidden" name="direction" value="up"><button type="submit" class="btn btn-secondary btn-sm">↑</button></form>
                <form method="POST" action="/admin/marketplace/<?= $p['id'] ?>/move" style="display:inline;"><input type="hidden" name="direction" value="down"><button type="submit" class="btn btn-secondary btn-sm">↓</button></form>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <?php if ($p['is_active']): ?>
                    <?php if ($p['is_pinned']): ?>
                        <form method="POST" action="/admin/marketplace/<?= $p['id'] ?>/unpin" style="display:inline;"><button type="submit" class="btn btn-secondary btn-sm">Открепить</button></form>
                    <?php else: ?>
                        <form method="POST" action="/admin/marketplace/<?= $p['id'] ?>/pin" style="display:inline;"><button type="submit" class="btn btn-primary btn-sm" <?= $pinnedCount >= $maxPinned ? 'disabled' : '' ?>>Закрепить</button></form>
                    <?php endif; ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td>
                <form method="POST" action="/admin/marketplace/<?= $p['id'] ?>/toggle" style="display:inline;">
                    <button type="submit" class="btn btn-secondary btn-sm"><?= $p['is_active'] ? '🙈 Скрыть' : '👁 Показать' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
