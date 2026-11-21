<?php
$slug = $_GET['slug'] ?? '';
$product = getTraderProductBySlug($slug);
if (!$product) { redirect('/marketplace'); }

$user = currentUser();
$isOwner = $user && (int)$user['id'] === (int)$product['user_id'];
$isStaff = hasRole(['admin', 'moderator']);
if (($product['status'] !== 'approved' || !$product['is_active']) && !$isOwner && !$isStaff) {
    redirect('/marketplace');
}

getDB()->prepare('UPDATE trader_products SET views_count = views_count + 1 WHERE id = ?')->execute([$product['id']]);

$rating = getSellerRating($product['user_id']);
$reviews = getSellerReviews($product['user_id']);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$bonusBalance = $user ? getBonusBalance($user['id']) : 0;
$maxBonusPercent = getBuyerBonusMaxPercent();
$maxUsableBonuses = $user ? min($bonusBalance, getBuyerBonusMaxPoints()) : 0;
$canDiscount = $user && bonusSystemEnabled() && $maxUsableBonuses > 0;

$isFree = (float)$product['price'] <= 0;
$requiredTasks = $isFree ? getRequiredTasksForProduct($product['id']) : [];
$completedCookie = json_decode($_COOKIE['completed_tasks'] ?? '[]', true) ?: [];
$requiredIds = array_column($requiredTasks, 'id');
$allTasksDone = !$requiredIds || count(array_intersect($requiredIds, $completedCookie)) === count($requiredIds);

$isFavorited = $user ? isFavorited($user['id'], $product['id']) : false;
$isSubscribed = $user ? isSubscribed($user['id'], $product['user_id']) : false;

$pageTitle = $product['title'];
include __DIR__ . '/../templates/header.php';
?>

<a href="/marketplace" class="btn btn-secondary" style="margin-bottom:16px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    К торговой площадке
</a>

<?php if ($flash): ?><div style="background:rgba(232,28,90,0.1);border:1px solid rgba(232,28,90,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>
<?php if ($product['status'] !== 'approved'): ?>
<div style="background:rgba(255,159,10,0.1);border:1px solid rgba(255,159,10,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;">
    ⚠️ Статус: <strong><?= e(productStatusLabel($product['status'])) ?></strong>. Эта страница видна только вам и администрации, товар ещё не опубликован на витрине.
    <?php if ($product['moderation_comment']): ?><br>Причина: <?= e($product['moderation_comment']) ?><?php endif; ?>
</div>
<?php elseif (!$product['is_active']): ?>
<div style="background:rgba(255,159,10,0.1);border:1px solid rgba(255,159,10,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;">
    🙈 Товар временно скрыт с витрины и недоступен для покупки. Эта страница видна только вам и администрации.
    <?php if ($isOwner): ?> Вы можете снова показать его на странице <a href="/seller/products" style="color:var(--g-accent-light);">«Мои товары»</a>.<?php endif; ?>
</div>
<?php endif; ?>

<div style="display:flex;gap:24px;margin-bottom:24px;flex-wrap:wrap;">
    <div class="material-cover" style="width:320px;height:200px;background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:var(--g-radius);flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid var(--g-border);overflow:hidden;position:relative;">
        <?php if ($product['cover_path']): ?>
            <img src="<?= e(UPLOAD_URL . $product['cover_path']) ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--g-accent)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        <?php endif; ?>
        <?php if ($user): ?>
        <form method="POST" action="/favorites/<?= $product['id'] ?>/toggle" style="position:absolute;top:8px;right:8px;">
            <input type="hidden" name="back" value="/product/<?= e($product['slug']) ?>">
            <button type="submit" class="icon-btn" style="background:rgba(0,0,0,0.5);border:none;" title="<?= $isFavorited ? 'Убрать из избранного' : 'В избранное' ?>">
                <?= $isFavorited ? '❤️' : '🤍' ?>
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div style="flex:1;min-width:280px;">
        <span class="card-badge <?= $product['price'] > 0 ? 'badge-paid' : 'badge-free' ?>" style="position:static;display:inline-block;margin-bottom:10px;">
            <?= $product['price'] > 0 ? number_format($product['price'], 0, ',', ' ') . ' ₽' : 'FREE' ?>
        </span>
        <?php if (!empty($product['auto_delivery'])): ?>
            <span style="display:inline-block;margin-bottom:10px;margin-left:8px;padding:4px 10px;border-radius:20px;background:rgba(52,199,89,0.15);color:#34C759;font-size:12px;font-weight:700;">⚡ Мгновенная выдача</span>
        <?php endif; ?>
        <h1 style="font-size:24px;font-weight:600;margin-bottom:12px;"><?= e($product['title']) ?></h1>

        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding:12px 14px;background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius-sm);flex-wrap:wrap;">
            <img src="<?= e(avatarUrl($product['seller_avatar'], $product['user_id'])) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
            <div style="flex:1;">
                <div style="font-weight:600;font-size:14px;"><a href="/trader/<?= $product['user_id'] ?>" style="color:inherit;">Продавец: <?= e($product['seller_name']) ?></a></div>
                <div style="font-size:13px;color:var(--g-text-secondary);">
                    <?= $rating['cnt'] > 0 ? '⭐ ' . number_format($rating['avg_rating'], 1) . ' (' . $rating['cnt'] . ' отзывов)' : 'Пока нет отзывов' ?>
                </div>
            </div>
            <?php if ($user && !$isOwner): ?>
            <form method="POST" action="/subscribe/<?= $product['user_id'] ?>/toggle">
                <input type="hidden" name="back" value="/product/<?= e($product['slug']) ?>">
                <button type="submit" class="btn btn-secondary btn-sm"><?= $isSubscribed ? '✓ Вы подписаны' : '+ Подписаться' ?></button>
            </form>
            <?php endif; ?>
        </div>

        <div style="display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:14px;margin-bottom:20px;">
            <span style="color:var(--g-text-secondary);">Версия игры</span><span style="font-weight:500;"><?= e($product['game_version'] ?: '—') ?></span>
            <span style="color:var(--g-text-secondary);">Размер</span><span style="font-weight:500;"><?= e($product['file_size'] ?: '—') ?></span>
            <span style="color:var(--g-text-secondary);">Завершённых заказов</span><span style="font-weight:500;"><?= number_format((int)($product['completed_orders_count'] ?? 0), 0, ',', ' ') ?></span>
        </div>

        <?php if ($product['price'] > 0): ?>
            <?php if (!$user): ?>
                <a href="/login" class="btn btn-primary">Войдите, чтобы купить за <?= number_format($product['price'], 0, ',', ' ') ?> ₽</a>
            <?php elseif ($isOwner): ?>
                <span style="color:var(--g-text-secondary);font-size:14px;">Это ваш товар</span>
            <?php else: ?>
                <button class="btn btn-primary" onclick="document.getElementById('buyModal').classList.add('active')">
                    Купить за <?= number_format($product['price'], 0, ',', ' ') ?> ₽
                </button>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($isOwner): ?>
                <span style="color:var(--g-text-secondary);font-size:14px;">Это ваш товар</span>
            <?php elseif ($allTasksDone): ?>
                <form method="POST" action="/product/<?= $product['id'] ?>/download">
                    <button type="submit" class="btn btn-primary">Скачать бесплатно</button>
                </form>
            <?php else: ?>
                <button class="btn btn-primary" onclick="showToast && showToast('Сначала выполните все задания ниже')">Скачать бесплатно</button>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($user && !$isOwner): ?>
            <a class="btn btn-secondary" style="margin-left:8px;" href="/chat?product_id=<?= (int)$product['id'] ?>&buyer_id=<?= (int)$user['id'] ?>">💬 Открыть чат с продавцом</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isFree && $requiredTasks): ?>
<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">Задания для скачивания</h3>
    <?php foreach ($requiredTasks as $index => $task): $isDone = in_array($task['id'], $completedCookie); ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--g-border);">
            <span><?= $isDone ? '✅' : ($index + 1 . '.') ?> <?= e($task['title']) ?></span>
            <?php if (!$isDone): ?>
                <a href="<?= e($task['url']) ?>" target="_blank" class="btn btn-primary btn-sm" onclick="completeTask(<?= $task['id'] ?>)">Выполнить</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<script>
function completeTask(id) {
    var completed = [];
    try { completed = JSON.parse(decodeURIComponent(document.cookie.replace(/(?:(?:^|.*;\s*)completed_tasks\s*=\s*([^;]*).*$)|^.*$/, "$1")) || '[]'); } catch(e) { completed = []; }
    if (completed.indexOf(id) === -1) completed.push(id);
    document.cookie = 'completed_tasks=' + encodeURIComponent(JSON.stringify(completed)) + ';path=/;max-age=31536000';
    setTimeout(function(){ location.reload(); }, 800);
}
</script>
<?php endif; ?>

<?php if ($product['description']): ?>
<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">Описание</h3>
    <div style="font-size:14px;line-height:1.6;color:var(--g-text-secondary);white-space:pre-wrap;"><?= e($product['description']) ?></div>
</div>
<?php endif; ?>

<?php $screenshots = json_decode($product['screenshots'] ?? '[]', true) ?: []; if ($screenshots): ?>
<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">Скриншоты</h3>
    <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <?php foreach ($screenshots as $i => $shot): ?>
            <a href="<?= e(UPLOAD_URL . $shot) ?>" target="_blank" style="display:block;width:180px;height:110px;border-radius:var(--g-radius-sm);overflow:hidden;border:1px solid var(--g-border);">
                <img src="<?= e(UPLOAD_URL . $shot) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;" alt="Скриншот <?= $i + 1 ?>">
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php $ytEmbed = youtubeEmbedUrl($product['youtube_url'] ?? ''); if ($ytEmbed): ?>
<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">Видео</h3>
    <div style="position:relative;padding-top:56.25%;border-radius:var(--g-radius-sm);overflow:hidden;">
        <iframe src="<?= e($ytEmbed) ?>" title="Видео товара" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="position:absolute;inset:0;width:100%;height:100%;border:0;"></iframe>
    </div>
</div>
<?php endif; ?>

<div class="card" style="padding:20px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;">Отзывы о продавце</h3>
    <?php if (!$reviews): ?>
        <p style="color:var(--g-text-secondary);font-size:14px;">Отзывов пока нет</p>
    <?php else: ?>
        <?php foreach ($reviews as $r): ?>
        <div style="padding:14px 0;border-bottom:1px solid var(--g-border);">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <strong style="font-size:14px;"><?= e($r['buyer_name']) ?></strong>
                <span><?= str_repeat('⭐', $r['rating']) ?></span>
            </div>
            <?php if ($r['comment']): ?><p style="font-size:14px;color:var(--g-text-secondary);"><?= e($r['comment']) ?></p><?php endif; ?>
            <?php if ($r['response']): ?><div style="margin-top:8px;padding:10px;background:var(--g-bg);border-radius:6px;font-size:13px;"><strong>Ответ продавца:</strong> <?= e($r['response']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Buy modal -->
<?php if ($product['price'] > 0 && $user && !$isOwner): ?>
<div class="modal-overlay" id="buyModal">
    <div class="modal">
        <h3 style="font-size:18px;font-weight:600;margin-bottom:16px;">Оформление заказа</h3>
        <div style="background:var(--g-bg);border:1px solid var(--g-border);border-radius:12px;padding:14px;margin-bottom:16px"><div style="font-weight:700;margin-bottom:5px"><?=e($product['title'])?></div><div style="font-size:12px;color:var(--g-text-secondary)">Продавец: <?=e($product['seller_name'])?></div><div style="display:flex;justify-content:space-between;margin-top:12px;font-size:13px"><span>Цена</span><strong><?=number_format($product['price'],2,',',' ')?> ₽</strong></div></div>
        <form method="POST" action="/orders/create">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <div style="background:var(--g-bg);border:1px solid var(--g-border);border-radius:var(--g-radius-sm);padding:14px;margin-bottom:16px;font-size:14px;">
                <p style="margin-bottom:6px;">Переведите оплату на реквизиты сайта:</p>
                <p style="font-weight:600;"><?= e(setting('donate_details', 'Реквизиты не указаны')) ?></p>
            </div>
            <div style="margin-bottom:16px;background:rgba(255,159,10,0.08);border:1px solid rgba(255,159,10,0.25);border-radius:var(--g-radius-sm);padding:12px 14px;display:flex;align-items:flex-start;gap:10px;">
                <span style="font-size:18px;flex-shrink:0;">⚠️</span>
                <div style="font-size:13px;color:var(--g-text-secondary);line-height:1.5;">
                    <strong style="color:#FF9F0A;">Donation Alerts:</strong> комиссия платформы <strong><?= e(formatPercent(setting('da_commission_percent','15'))) ?>%</strong>.
                    Если сумма доната меньше стоимости товара с учётом комиссии — потребуется <strong>доплата разницы</strong>.
                </div>
            </div>
            <?php if ($canDiscount): ?>
            <div style="margin-bottom:16px;"><label style="display:block;font-size:14px;margin-bottom:6px;">🎁 Бонусы (1 бонус = 0,5% скидки; 10 бонусов = 5%)</label><input type="number" name="bonuses_to_use" id="bonusesInput" min="0" step="2" max="<?= $maxUsableBonuses ?>" value="<?= $maxUsableBonuses ?>" class="input" style="width:150px;"><div id="discountPreview" style="font-size:13px;color:var(--g-accent-light);margin-top:6px;"></div></div>
            <?php endif; ?>
            <div style="margin-bottom:16px;"><label style="display:block;font-size:14px;margin-bottom:6px;">🏷️ Промокод</label><input type="text" name="promo_code" id="promoCode" class="input" placeholder="Введите промокод, если есть" maxlength="50"><div id="promoPreview" style="font-size:12px;color:var(--g-text-secondary);margin-top:5px;">Скидка промокода посчитается автоматически.</div></div>
            <div id="totalPreview" style="display:flex;justify-content:space-between;padding:12px 0;border-top:1px solid var(--g-border);margin-bottom:4px;font-weight:700"><span>Итого к переводу</span><span id="totalPreviewValue"><?=number_format($product['price'],2,',',' ')?> ₽</span></div>
            <div id="daPreview" style="display:flex;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid var(--g-border);margin-bottom:16px;font-size:13px;color:#FF9F0A;"><span>Через Donation Alerts (с комиссией)</span><span id="daPreviewValue"><?= number_format($product['price'] / (1 - (float)setting('da_commission_percent',15)/100), 2, ',', ' ') ?> ₽</span></div>
            <p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:16px;">После перевода нажмите «Я оплатил» — заказ уйдёт на подтверждение администратору, деньги продавцу поступят только после этого (система гаранта).</p>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">Я оплатил</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('buyModal').classList.remove('active')">Отмена</button>
            </div>
        </form>
    </div>
</div>
<script>
(function(){
    const productId = <?= (int)$product['id'] ?>;
    const bi = document.getElementById('bonusesInput');
    const dp = document.getElementById('discountPreview');
    const promoInput = document.getElementById('promoCode');
    const promoPreview = document.getElementById('promoPreview');
    const totalValue = document.getElementById('totalPreviewValue');
    const daValue = document.getElementById('daPreviewValue');
    let debounceTimer = null;

    function fmt(n){ return Number(n).toFixed(2).replace('.', ',') + ' ₽'; }

    function refresh(){
        const body = new URLSearchParams({
            product_id: productId,
            bonuses_to_use: bi ? (bi.value || 0) : 0,
            promo_code: promoInput ? promoInput.value.trim() : ''
        });
        fetch('/api/order-preview', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body })
            .then(r => r.json())
            .then(data => {
                if (data.error) { return; }
                if (dp) {
                    const b = bi ? Math.max(0, parseInt(bi.value || 0)) : 0;
                    const pct = Math.floor(b / 2);
                    dp.textContent = pct > 0 ? pct + '% скидка = −' + fmt(data.bonus_discount) : '';
                }
                if (promoPreview) {
                    const code = promoInput ? promoInput.value.trim() : '';
                    if (!code) { promoPreview.textContent = 'Скидка промокода посчитается автоматически.'; promoPreview.style.color = 'var(--g-text-secondary)'; }
                    else if (data.promo_valid) { promoPreview.textContent = '✅ Промокод применён: −' + fmt(data.promo_discount); promoPreview.style.color = '#34C759'; }
                    else { promoPreview.textContent = '❌ ' + (data.promo_error || 'Промокод недействителен'); promoPreview.style.color = '#FF3B30'; }
                }
                if (totalValue) totalValue.textContent = fmt(data.final_amount);
                if (daValue) daValue.textContent = fmt(data.da_amount);
            })
            .catch(() => {});
    }

    if (bi) bi.addEventListener('input', refresh);
    if (promoInput) promoInput.addEventListener('input', function(){ clearTimeout(debounceTimer); debounceTimer = setTimeout(refresh, 500); });
    refresh();
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
