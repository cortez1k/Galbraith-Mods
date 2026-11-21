<?php
$user = currentUser();
$favorites = getFavorites($user['id']);
$pageTitle = 'Избранное';
include __DIR__ . '/../templates/header.php';
?>

<a href="/profile" class="btn btn-secondary" style="margin-bottom:16px;">← Личный кабинет</a>
<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Избранное</h1>

<?php if (!$favorites): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Пока пусто. <a href="/marketplace" style="color:var(--g-accent-light);">Перейти на торговую площадку →</a></div>
<?php else: ?>
<div class="grid">
    <?php foreach ($favorites as $p): ?>
    <div class="card" style="position:relative;">
        <a href="/product/<?= e($p['slug']) ?>" style="text-decoration:none;color:inherit;display:block;">
            <div class="card-img" style="background:linear-gradient(135deg,#1a1a2e,#16213e);height:160px;display:flex;align-items:center;justify-content:center;border-radius:var(--g-radius) var(--g-radius) 0 0;overflow:hidden;">
                <?php if ($p['cover_path']): ?><img src="<?= e(UPLOAD_URL . $p['cover_path']) ?>" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?>
            </div>
            <div class="card-body" style="padding:14px;">
                <div class="card-title" style="font-weight:600;font-size:15px;margin-bottom:4px;"><?= e($p['title']) ?></div>
                <div class="card-meta" style="font-size:12px;color:var(--g-text-secondary);margin-bottom:8px;"><?= e($p['seller_name']) ?></div>
                <div class="card-price" style="font-weight:700;color:var(--g-accent-light);"><?= $p['price'] > 0 ? number_format($p['price'], 0, ',', ' ') . ' ₽' : 'Бесплатно' ?></div>
            </div>
        </a>
        <form method="POST" action="/favorites/<?= $p['id'] ?>/toggle" style="padding:0 14px 14px;">
            <input type="hidden" name="back" value="/profile/favorites">
            <button type="submit" class="btn btn-secondary btn-sm" style="width:100%;">Убрать из избранного</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
