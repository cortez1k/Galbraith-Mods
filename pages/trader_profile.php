<?php
$id = (int)($_GET['id'] ?? 0);
$stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND role = 'trader'");
$stmt->execute([$id]);
$trader = $stmt->fetch();
if (!$trader) { redirect('/marketplace'); }

$rating = getSellerRating($id);
$reviews = getSellerReviews($id);
$products = getTraderProducts(null, null, null, 'new', null, null, null);
$products = array_values(array_filter($products, function ($p) use ($id) { return (int)$p['seller_id'] === $id; }));
$subscriberCount = getSubscriberCount($id);

$user = currentUser();
$isSubscribed = $user ? isSubscribed($user['id'], $id) : false;

$pageTitle = $trader['name'];
include __DIR__ . '/../templates/header.php';
?>

<a href="/marketplace" class="btn btn-secondary" style="margin-bottom:16px;">← Торговая площадка</a>

<div class="card" style="padding:24px;display:flex;gap:20px;align-items:center;margin-bottom:24px;flex-wrap:wrap;">
    <img src="<?= e(avatarUrl($trader['avatar'], $trader['id'])) ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;">
    <div style="flex:1;">
        <h1 style="font-size:22px;font-weight:700;margin-bottom:6px;"><?= e($trader['name']) ?></h1>
        <div style="font-size:14px;color:var(--g-text-secondary);">
            <?= $rating['cnt'] > 0 ? '⭐ ' . number_format($rating['avg_rating'], 1) . ' (' . $rating['cnt'] . ' отзывов)' : 'Пока нет отзывов' ?>
            · <?= $subscriberCount ?> подписчиков
            <?php if (!empty($trader['created_at'])): ?> · <?= e(formatMembershipDuration($trader['created_at'])) ?><?php endif; ?>
        </div>
    </div>
    <?php if ($user && (int)$user['id'] !== $id): ?>
    <form method="POST" action="/subscribe/<?= $id ?>/toggle">
        <input type="hidden" name="back" value="/trader/<?= $id ?>">
        <button type="submit" class="btn <?= $isSubscribed ? 'btn-secondary' : 'btn-primary' ?>"><?= $isSubscribed ? '✓ Вы подписаны' : '+ Подписаться' ?></button>
    </form>
    <?php endif; ?>
</div>

<?php if($user && (int)$user['id']===$id): ?>
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:24px"><div class="card" style="padding:16px;text-align:center"><div style="font-size:12px;color:var(--g-text-secondary)">Бонусы торговца</div><strong style="font-size:24px;color:var(--g-accent-light)"><?=rtrim(rtrim(number_format(getTraderBonusBalance($id),2,'.',' '),'0'),'.')?></strong></div><div class="card" style="padding:16px;text-align:center"><div style="font-size:12px;color:var(--g-text-secondary)">Бонусы покупателя</div><strong style="font-size:24px;color:var(--g-accent-light)"><?=getBonusBalance($id)?></strong></div></div>
<?php endif; ?>

<h3 style="font-size:16px;font-weight:600;margin-bottom:14px;">Товары продавца</h3>
<?php if (!$products): ?>
    <p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:24px;">Пока нет товаров</p>
<?php else: ?>
<div class="grid" style="margin-bottom:28px;">
    <?php foreach ($products as $p): ?>
    <a href="/product/<?= e($p['slug']) ?>" class="card" style="text-decoration:none;color:inherit;display:block;">
        <div class="card-img" style="background:linear-gradient(135deg,#1a1a2e,#16213e);height:140px;display:flex;align-items:center;justify-content:center;border-radius:var(--g-radius) var(--g-radius) 0 0;overflow:hidden;">
            <?php if ($p['cover_path']): ?><img src="<?= e(UPLOAD_URL . $p['cover_path']) ?>" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?>
        </div>
        <div class="card-body" style="padding:12px;">
            <div class="card-title" style="font-weight:600;font-size:14px;margin-bottom:4px;"><?= e($p['title']) ?></div>
            <div class="card-price" style="font-weight:700;color:var(--g-accent-light);font-size:14px;"><?= $p['price'] > 0 ? number_format($p['price'], 0, ',', ' ') . ' ₽' : 'Бесплатно' ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h3 style="font-size:16px;font-weight:600;margin-bottom:14px;">Отзывы</h3>
<?php if (!$reviews): ?>
    <p style="color:var(--g-text-secondary);font-size:14px;">Отзывов пока нет</p>
<?php else: ?>
<div class="card" style="padding:20px;">
    <?php foreach ($reviews as $r): ?>
    <div style="padding:14px 0;border-bottom:1px solid var(--g-border);">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><strong style="font-size:14px;"><?= e($r['buyer_name']) ?></strong><span><?= str_repeat('⭐', $r['rating']) ?></span></div>
        <?php if ($r['comment']): ?><p style="font-size:14px;color:var(--g-text-secondary);"><?= e($r['comment']) ?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
