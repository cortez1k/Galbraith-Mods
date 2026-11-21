<?php
$stmt = getDB()->query("SELECT r.*, b.name as buyer_name, s.name as seller_name FROM reviews r
    LEFT JOIN users b ON r.buyer_id = b.id LEFT JOIN users s ON r.seller_id = s.id ORDER BY r.created_at DESC");
$reviews = $stmt->fetchAll();
$pageTitle = 'Отзывы';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Отзывы</h1>

<?php if (!$reviews): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Отзывов пока нет</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($reviews as $r): ?>
    <div class="card" style="padding:16px;<?= $r['is_hidden'] ? 'opacity:0.5;' : '' ?>">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <div>
                <strong><?= e($r['buyer_name']) ?></strong> → <?= e($r['seller_name']) ?> <?= str_repeat('⭐', $r['rating']) ?>
                <?php if ($r['is_hidden']): ?> <span style="color:#FF3B30;font-size:12px;">(скрыт)</span><?php endif; ?>
                <p style="font-size:14px;color:var(--g-text-secondary);margin-top:6px;"><?= e($r['comment']) ?></p>
            </div>
            <div style="display:flex;gap:6px;">
                <form method="POST" action="/admin/reviews/<?= $r['id'] ?>/toggle"><button type="submit" class="btn btn-secondary btn-sm"><?= $r['is_hidden'] ? 'Показать' : 'Скрыть' ?></button></form>
                <form method="POST" action="/admin/reviews/<?= $r['id'] ?>/delete"><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Удалить отзыв?')">Удалить</button></form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
