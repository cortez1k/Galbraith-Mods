<?php
$user = currentUser();
$subs = getSubscriptions($user['id']);
$pageTitle = 'Мои подписки';
include __DIR__ . '/../templates/header.php';
?>

<a href="/profile" class="btn btn-secondary" style="margin-bottom:16px;">← Личный кабинет</a>
<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Мои подписки</h1>

<?php if (!$subs): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Вы пока ни на кого не подписаны</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($subs as $s): ?>
    <div class="card" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <a href="/trader/<?= $s['seller_id'] ?>" style="display:flex;align-items:center;gap:12px;color:inherit;text-decoration:none;">
            <img src="<?= e(avatarUrl($s['seller_avatar'], $s['seller_id'])) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
            <div>
                <div style="font-weight:600;"><?= e($s['seller_name']) ?></div>
                <div style="font-size:13px;color:var(--g-text-secondary);"><?= $s['seller_rating'] ? '⭐ ' . number_format($s['seller_rating'], 1) : 'Пока нет отзывов' ?></div>
            </div>
        </a>
        <form method="POST" action="/subscribe/<?= $s['seller_id'] ?>/toggle">
            <input type="hidden" name="back" value="/profile/subscriptions">
            <button type="submit" class="btn btn-secondary btn-sm">Отписаться</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
