<?php
$user = currentUser();
$reviews = getSellerReviews($user['id']);
$pageTitle = 'Отзывы';
include __DIR__ . '/../templates/header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Отзывы обо мне</h1>

<?php if (!$reviews): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Отзывов пока нет</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px;">
    <?php foreach ($reviews as $r): ?>
    <div class="card" style="padding:18px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <strong><?= e($r['buyer_name']) ?></strong>
            <span><?= str_repeat('⭐', $r['rating']) ?></span>
        </div>
        <?php if ($r['comment']): ?><p style="font-size:14px;color:var(--g-text-secondary);margin-bottom:10px;"><?= e($r['comment']) ?></p><?php endif; ?>
        <?php if ($r['response']): ?>
            <div style="padding:10px;background:var(--g-bg);border-radius:6px;font-size:13px;"><strong>Ваш ответ:</strong> <?= e($r['response']) ?></div>
        <?php else: ?>
            <form method="POST" action="/seller/reviews/<?= $r['id'] ?>/reply" style="display:flex;gap:8px;margin-top:8px;">
                <input type="text" name="response" class="input" placeholder="Ответить..." style="flex:1;" required>
                <button type="submit" class="btn btn-secondary btn-sm">Ответить</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
