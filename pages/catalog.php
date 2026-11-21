<?php
$categorySlug = $_GET['category'] ?? null;
$search = $_GET['search'] ?? null;
$sort = $_GET['sort'] ?? 'new';
$materials = getMaterials(null, $categorySlug, $search, $sort);
$pageTitle = 'Каталог';
include __DIR__ . '/../templates/header.php';
?>

<h2 class="section-title">Каталог</h2>

<form method="GET" action="/catalog" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <input type="text" name="search" value="<?= e($search) ?>" class="input" placeholder="Поиск по названию..." style="flex:1;min-width:200px;">
    <select name="category" class="select" style="min-width:160px;" onchange="this.form.submit()">
        <option value="">Все категории</option>
        <?php foreach (getCategories() as $cat): ?>
            <option value="<?= e($cat['slug']) ?>" <?= $categorySlug === $cat['slug'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="sort" class="select" style="min-width:160px;" onchange="this.form.submit()">
        <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Сначала новые</option>
        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>По популярности</option>
    </select>
</form>

<div class="grid">
    <?php foreach ($materials as $m): $isPrem = !empty($m['is_premiere']); $isNew = !$isPrem && !empty($m['is_new']); ?>
    <a href="/mod/<?= e($m['slug']) ?>" class="card<?= $isPrem ? ' card-premiere' : '' ?>">
        <div class="card-img">
            <?php if ($m['cover_path']): ?>
                <img src="<?= e(UPLOAD_URL . $m['cover_path']) ?>" alt="<?= e($m['title']) ?>">
            <?php else: ?>
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--g-accent)" stroke-width="1.2" opacity="0.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            <?php endif; ?>
            <?php if ($isPrem): ?><span class="badge-premiere-tag">🚀 ПРЕМЬЕРА</span><?php elseif ($isNew): ?><span class="badge-new-tag">🆕 НОВИНКА</span><?php endif; ?>
            <span class="card-badge <?= $m['access_type'] === 'free' ? 'badge-free' : 'badge-paid' ?>">
                <?= $m['access_type'] === 'free' ? 'FREE' : formatPrice($m) ?>
            </span>
        </div>
        <div class="card-body">
            <div class="card-title"><?= e($m['title']) ?></div>
            <div class="card-desc"><?= e($m['description']) ?></div>
            <div class="card-meta">
                <?php if ($isPrem): ?>
                    <span class="countdown-mini" data-target="<?= e(date('c', strtotime($m['publish_at']))) ?>">скоро</span>
                <?php else: ?>
                    <span><?= e($m['category_name'] ?? 'Без категории') ?> • <?= e($m['file_size'] ?: '—') ?></span>
                <?php endif; ?>
                <?php if (!empty($m['rating_count'])): ?>
                    <span class="rating-mini"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><?= number_format($m['rating_avg'], 1) ?></span>
                <?php elseif ($m['access_type'] === 'paid'): ?>
                    <span class="card-price"><?= formatPrice($m) ?></span>
                <?php endif; ?>
                <?php if (!empty($m['rating_count']) && $m['access_type'] === 'paid'): ?>
                    <span class="card-price"><?= formatPrice($m) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
