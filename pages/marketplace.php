<?php
$tab = ($_GET['tab'] ?? 'paid') === 'free' ? 'free' : 'paid';
$search = $_GET['search'] ?? '';
$catFilter = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'random';
$maxPrice = $_GET['max_price'] ?? '';
$minRating = $_GET['min_rating'] ?? '';

$products = getTraderProducts(null, $catFilter ?: null, $search ?: null, $sort, $maxPrice !== '' ? $maxPrice : null, $minRating !== '' ? (int)$minRating : null, $tab);

$pageTitle = 'Торговая площадка';
include __DIR__ . '/../templates/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div>
        <h1 style="font-size:24px;font-weight:700;margin-bottom:6px;">Торговая площадка</h1>
        <p style="color:var(--g-text-secondary);font-size:14px;">Сборки и моды от независимых торговцев, покупка через гарант</p>
    </div>
</div>

<div style="background:linear-gradient(135deg,rgba(232,28,90,0.12),rgba(232,28,90,0.03));border:1px solid rgba(232,28,90,0.25);border-radius:var(--g-radius);padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <span style="font-size:20px;">💼</span>
    <div style="flex:1;font-size:14px;color:var(--g-text-secondary);"><?= e(setting('become_trader_text', 'Хотите стать торговцем? Напишите мне в ВК!')) ?></div>
</div>

<div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--g-border);">
    <a href="/marketplace?tab=paid" style="padding:10px 18px;font-weight:600;text-decoration:none;color:<?= $tab==='paid'?'var(--g-accent-light)':'var(--g-text-secondary)' ?>;border-bottom:2px solid <?= $tab==='paid'?'var(--g-accent)':'transparent' ?>;">Платные товары</a>
    <a href="/marketplace?tab=free" style="padding:10px 18px;font-weight:600;text-decoration:none;color:<?= $tab==='free'?'var(--g-accent-light)':'var(--g-text-secondary)' ?>;border-bottom:2px solid <?= $tab==='free'?'var(--g-accent)':'transparent' ?>;">Бесплатные товары</a>
</div>

<form method="GET" style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <input type="text" name="search" value="<?= e($search) ?>" class="input" placeholder="Поиск товаров..." style="flex:1;min-width:200px;">
    <select name="category" class="select" onchange="this.form.submit()">
        <option value="">Все категории</option>
        <?php foreach (getCategories() as $cat): ?>
            <option value="<?= e($cat['slug']) ?>" <?= $catFilter === $cat['slug'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($tab === 'paid'): ?>
    <input type="number" name="max_price" value="<?= e($maxPrice) ?>" class="input" placeholder="Цена до, ₽" style="width:140px;">
    <?php endif; ?>
    <select name="min_rating" class="select" onchange="this.form.submit()">
        <option value="">Любой рейтинг</option>
        <?php foreach ([4,3,2,1] as $r): ?>
            <option value="<?= $r ?>" <?= (string)$minRating === (string)$r ? 'selected' : '' ?>>от <?= $r ?>★</option>
        <?php endforeach; ?>
    </select>
    <select name="sort" class="select" onchange="this.form.submit()">
        <option value="random" <?= $sort === 'random' ? 'selected' : '' ?>>Случайный порядок</option>
        <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Сначала новые</option>
        <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>По дате добавления</option>
        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>По популярности</option>
        <?php if ($tab === 'paid'): ?>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Сначала дешевле</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Сначала дороже</option>
        <?php endif; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Применить</button>
</form>

<?php if (!$products): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Товары не найдены</div>
<?php else: ?>
<div class="grid">
    <?php foreach ($products as $p): ?>
    <a href="/product/<?= e($p['slug']) ?>" class="card" style="text-decoration:none;color:inherit;display:block;position:relative;">
        <?php if ($p['is_pinned']): ?><span style="position:absolute;top:8px;left:8px;z-index:2;background:#FF9F0A;color:#000;font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;">📌 Закреплено</span><?php endif; ?>
        <?php if (!empty($p['is_top_seller'])): ?><span style="position:absolute;top:8px;right:8px;z-index:2;background:linear-gradient(135deg,#E81C5A,#FF5C8A);color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;">⭐ Топ-продавец</span><?php endif; ?>
        <div class="card-img" style="background:linear-gradient(135deg,#1a1a2e,#16213e);height:160px;display:flex;align-items:center;justify-content:center;border-radius:var(--g-radius) var(--g-radius) 0 0;overflow:hidden;">
            <?php if ($p['cover_path']): ?>
                <img src="<?= e(UPLOAD_URL . $p['cover_path']) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--g-accent)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding:14px;">
            <div class="card-title" style="font-weight:600;font-size:15px;margin-bottom:4px;"><?= e($p['title']) ?></div>
            <div class="card-meta" style="font-size:12px;color:var(--g-text-secondary);margin-bottom:8px;">
                <?= e($p['seller_name'] ?? 'Продавец') ?>
                <?php if ($p['seller_rating']): ?> · ⭐ <?= number_format($p['seller_rating'], 1) ?> (<?= $p['seller_reviews_count'] ?>)<?php endif; ?>
            </div>
            <div class="card-price" style="font-weight:700;color:var(--g-accent-light);"><?= $p['price'] > 0 ? number_format($p['price'], 0, ',', ' ') . ' ₽' : 'Бесплатно' ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
