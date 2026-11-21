<?php
$user = currentUser();
$db = getDB();
$stats = [
    'products' => $db->prepare('SELECT COUNT(*) FROM trader_products WHERE user_id = ?'),
    'orders' => $db->prepare('SELECT COUNT(*) FROM orders WHERE seller_id = ?'),
    'balance' => getUserBalance($user['id']),
];
$stats['products']->execute([$user['id']]); $productsCount = $stats['products']->fetchColumn();
$stats['orders']->execute([$user['id']]); $ordersCount = $stats['orders']->fetchColumn();
$rating = getSellerRating($user['id']);

$pageTitle = 'Панель продавца';
include __DIR__ . '/../templates/header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Панель продавца</h1>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:700;color:var(--g-accent-light);"><?= $productsCount ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Товаров</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:700;color:var(--g-accent-light);"><?= $ordersCount ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Заказов</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:700;color:var(--g-accent-light);"><?= number_format($stats['balance'], 0, ',', ' ') ?> ₽</div><div style="font-size:13px;color:var(--g-text-secondary);">Баланс</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:700;color:var(--g-accent-light);"><?= $rating['cnt'] > 0 ? number_format($rating['avg_rating'], 1) . ' ⭐' : '—' ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Рейтинг</div></div>
</div>

<?php if (!sellerHasPayoutDetails($user)): ?>
<div style="background:rgba(255,159,10,0.1);border:1px solid rgba(255,159,10,0.3);border-radius:var(--g-radius-sm);padding:14px 18px;margin-bottom:24px;font-size:14px;">
    ⚠️ Заполните <a href="/seller/settings" style="color:#FF9F0A;font-weight:600;">реквизиты для выплат</a>, чтобы выставлять платные товары.
</div>
<?php endif; ?>

<div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/seller/products/create" class="btn btn-primary">+ Добавить товар</a>
    <a href="/seller/products" class="btn btn-secondary">Мои товары</a>
    <a href="/seller/templates" class="btn btn-secondary">Шаблоны товаров</a>
    <a href="/seller/orders" class="btn btn-secondary">Мои продажи</a>
    <a href="/seller/finances" class="btn btn-secondary">Финансы</a>
    <a href="/seller/settings" class="btn btn-secondary">Реквизиты</a>
    <a href="/seller/reviews" class="btn btn-secondary">Отзывы</a>
    <a href="/seller/messages" class="btn btn-secondary">Сообщения</a>
    <a href="/seller/bonuses" class="btn btn-secondary">🎁 Мои бонусы</a>
    <a href="/seller/referrals" class="btn btn-secondary">🔗 Рефералы</a>
    <a href="/seller/discipline" class="btn btn-secondary">⚖️ Дисциплина</a>
</div>

<?php if (function_exists('isTopSeller') && isTopSeller($user['id'])): ?>
<div style="margin-top:20px;background:linear-gradient(135deg,rgba(232,28,90,0.15),rgba(232,28,90,0.05));border:1px solid rgba(232,28,90,0.3);border-radius:var(--g-radius-sm);padding:14px 18px;font-size:14px;">
    ⭐ У вас активен статус <strong>«Топ-продавец»</strong> — товары поднимаются в выдаче маркетплейса.
</div>
<?php endif; ?>
<?php if (function_exists('countActivePenalties') && countActivePenalties($user['id']) > 0): ?>
<div style="margin-top:12px;background:rgba(255,159,10,0.08);border:1px solid rgba(255,159,10,0.25);border-radius:var(--g-radius-sm);padding:14px 18px;font-size:14px;">
    ⚠️ У вас <?= countActivePenalties($user['id']) ?> активных дисциплинарных взысканий. <a href="/seller/discipline" style="color:#FF9F0A;font-weight:600;">Подробнее →</a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
