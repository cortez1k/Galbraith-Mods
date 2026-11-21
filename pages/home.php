<?php
$latest = getMaterials(6);
$news = getNews(10);
$ytVideoId = setting('youtube_last_video_id');
$ytInfo = getYouTubeVideoInfo($ytVideoId);
$pageTitle = 'Главная';
include __DIR__ . '/../templates/header.php';
?>

<?php
$totalCount = count(getMaterials(null, null, null, 'new'));
$freeCount = 0;
foreach (getMaterials(null, null, null, 'new') as $m) { if ($m['access_type'] === 'free') $freeCount++; }
?>
<div class="hero-lumo">
    <div class="hero-lumo-panel">
        <span class="hero-unusual-eyebrow"><i></i> Galbraith · Amazing RP</span>
        <h1>Сборки и моды<br>для <em>AMAZING RP</em></h1>
        <p>Качественный контент для Amazing RP. Выполняй задания — получай доступ к загрузке уже сегодня.</p>
        <div class="hero-unusual-stats">
            <div class="hero-unusual-stat"><b><?= $totalCount ?></b><span>материалов</span></div>
            <div class="hero-unusual-stat"><b><?= $freeCount ?></b><span>бесплатных</span></div>
            <div class="hero-unusual-stat"><b>24ч</b><span>ответ поддержки</span></div>
        </div>
    </div>
    <div class="hero-marquee">
        <div class="hero-marquee-track">
            <span><i>●</i> AMAZING RP <i>●</i> НОВЫЕ СБОРКИ <i>●</i> ПРЕМЬЕРЫ <i>●</i> АХК И КОНФИГИ <i>●</i> AMAZING RP <i>●</i> НОВЫЕ СБОРКИ <i>●</i> ПРЕМЬЕРЫ <i>●</i> АХК И КОНФИГИ</span>
            <span aria-hidden="true"><i>●</i> AMAZING RP <i>●</i> НОВЫЕ СБОРКИ <i>●</i> ПРЕМЬЕРЫ <i>●</i> АХК И КОНФИГИ <i>●</i> AMAZING RP <i>●</i> НОВЫЕ СБОРКИ <i>●</i> ПРЕМЬЕРЫ <i>●</i> АХК И КОНФИГИ</span>
        </div>
    </div>
</div>

<!-- News Carousel -->
<?php if (count($news) > 0 || $ytInfo || true): ?>
<div style="margin-bottom:32px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 class="section-title" style="margin-bottom:0;">Новости</h2>
        <div style="display:flex;gap:8px;">
            <button onclick="scrollNews(-1)" style="width:32px;height:32px;border-radius:8px;background:var(--g-card);border:1px solid var(--g-border);color:var(--g-text);cursor:pointer;display:flex;align-items:center;justify-content:center;">←</button>
            <button onclick="scrollNews(1)" style="width:32px;height:32px;border-radius:8px;background:var(--g-card);border:1px solid var(--g-border);color:var(--g-text);cursor:pointer;display:flex;align-items:center;justify-content:center;">→</button>
        </div>
    </div>

    <div style="display:flex;gap:16px;align-items:stretch;">

        <!-- YouTube — FIXED, not scrolling -->
        <?php if ($ytInfo): ?>
        <div style="flex:0 0 320px;background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);overflow:hidden;display:flex;flex-direction:column;">
            <div style="position:relative;height:180px;background:#000;overflow:hidden;">
                <img src="<?= e($ytInfo['thumbnail_url'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;">
                <div style="position:absolute;top:10px;left:10px;background:rgba(255,0,0,0.9);color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:0.5px;">🔥 НОВЫЙ РОЛИК</div>
                <div style="position:absolute;bottom:10px;right:10px;background:rgba(0,0,0,0.8);color:#fff;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600;">▶ YouTube</div>
            </div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:8px;flex:1;">
                <div style="font-size:14px;font-weight:600;line-height:1.4;"><?= e($ytInfo['title'] ?? 'Новый ролик на канале') ?></div>
                <div style="font-size:13px;color:var(--g-text-secondary);"><?= e($ytInfo['author_name'] ?? 'Galbraith') ?></div>
                <div style="margin-top:auto;padding-top:10px;">
                    <a href="https://www.youtube.com/watch?v=<?= e($ytVideoId) ?>" target="_blank" class="btn btn-primary" style="text-decoration:none;width:100%;">Смотреть на YouTube →</a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div style="flex:0 0 300px;background:linear-gradient(135deg,var(--g-accent),#B8123F);border-radius:var(--g-radius);padding:20px;display:flex;flex-direction:column;gap:10px;min-height:180px;">
            <div style="font-size:12px;font-weight:700;letter-spacing:0.5px;opacity:0.9;">🔥 НОВЫЙ РОЛИК УЖЕ НА КАНАЛЕ!</div>
            <div style="font-size:15px;font-weight:600;line-height:1.4;">Смотри свежий контент на YouTube-канале Galbraith</div>
            <div style="margin-top:auto;">
                <a href="<?= e(setting('social_yt', 'https://www.youtube.com/@galbraith1629')) ?>" target="_blank" class="btn" style="background:#fff;color:var(--g-accent);text-decoration:none;">Смотреть →</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Scrolling carousel -->
        <div id="newsCarousel" style="flex:1;display:flex;gap:16px;overflow-x:auto;scroll-behavior:smooth;scroll-snap-type:x mandatory;padding-bottom:8px;scrollbar-width:none;">

            <?php foreach ($news as $n): ?>
            <div style="flex:0 0 280px;scroll-snap-align:start;background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);overflow:hidden;display:flex;flex-direction:column;">
                <?php if ($n['image_url']): ?>
                <div style="height:140px;background:linear-gradient(135deg,#1a1a2e,#16213e);overflow:hidden;">
                    <img src="<?= e(UPLOAD_URL . $n['image_url']) ?>" style="width:100%;height:100%;object-fit:cover;">
                </div>
                <?php endif; ?>
                <div style="padding:16px;display:flex;flex-direction:column;gap:8px;flex:1;">
                    <div style="font-size:14px;font-weight:600;line-height:1.4;"><?= e($n['title']) ?></div>
                    <?php if ($n['content']): ?>
                    <div style="font-size:13px;color:var(--g-text-secondary);line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;"><?= strip_tags($n['content']) ?></div>
                    <?php endif; ?>
                    <div style="margin-top:auto;padding-top:10px;">
                        <?php if ($n['url']): ?>
                        <a href="<?= e($n['url']) ?>" target="_blank" style="font-size:13px;font-weight:600;color:var(--g-accent-light);">Подробнее →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Popular material -->
            <?php 
            $popular = getMaterials(1, null, null, 'popular');
            if (!empty($popular[0])): $pop = $popular[0]; 
            ?>
            <a href="/mod/<?= e($pop['slug']) ?>" style="flex:0 0 280px;scroll-snap-align:start;background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);overflow:hidden;display:flex;flex-direction:column;text-decoration:none;color:inherit;">
                <div style="height:140px;background:linear-gradient(135deg,#2a1a3e,#1a1a2e);display:flex;align-items:center;justify-content:center;position:relative;">
                    <?php if ($pop['cover_path']): ?>
                        <img src="<?= e(UPLOAD_URL . $pop['cover_path']) ?>" style="width:100%;height:100%;object-fit:cover;opacity:0.7;">
                    <?php endif; ?>
                    <div style="position:absolute;top:10px;left:10px;background:rgba(255,59,48,0.9);color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:700;">🔥 ТОП</div>
                </div>
                <div style="padding:16px;display:flex;flex-direction:column;gap:6px;flex:1;">
                    <div style="font-size:14px;font-weight:600;line-height:1.3;"><?= e($pop['title']) ?></div>
                    <div style="font-size:12px;color:var(--g-text-secondary);"><?= number_format($pop['downloads_count'], 0, ',', ' ') ?> скачиваний</div>
                    <div style="margin-top:auto;font-size:13px;font-weight:600;color:var(--g-accent-light);">Смотреть →</div>
                </div>
            </a>
            <?php endif; ?>

            <!-- Random material -->
            <?php 
            $allMats = getMaterials(null, null, null, 'new');
            if (count($allMats) > 1): 
                $rand = $allMats[array_rand($allMats)];
            ?>
            <a href="/mod/<?= e($rand['slug']) ?>" style="flex:0 0 280px;scroll-snap-align:start;background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);overflow:hidden;display:flex;flex-direction:column;text-decoration:none;color:inherit;">
                <div style="height:140px;background:linear-gradient(135deg,#1a2e1a,#0d1f0d);display:flex;align-items:center;justify-content:center;position:relative;">
                    <?php if ($rand['cover_path']): ?>
                        <img src="<?= e(UPLOAD_URL . $rand['cover_path']) ?>" style="width:100%;height:100%;object-fit:cover;opacity:0.6;">
                    <?php endif; ?>
                    <div style="position:absolute;top:10px;left:10px;background:rgba(52,199,89,0.9);color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:700;">🎯 СЛУЧАЙНО</div>
                </div>
                <div style="padding:16px;display:flex;flex-direction:column;gap:6px;flex:1;">
                    <div style="font-size:14px;font-weight:600;line-height:1.3;"><?= e($rand['title']) ?></div>
                    <div style="font-size:12px;color:var(--g-text-secondary);">Не знаешь что выбрать?</div>
                    <div style="margin-top:auto;font-size:13px;font-weight:600;color:var(--g-accent-light);">Открыть →</div>
                </div>
            </a>
            <?php endif; ?>

            <!-- VK promo -->
            <div style="flex:0 0 260px;scroll-snap-align:start;background:linear-gradient(135deg,#2787f5,#1769c2);border-radius:var(--g-radius);padding:20px;display:flex;flex-direction:column;gap:10px;min-height:180px;color:#fff;">
                <div style="font-size:28px;">💬</div>
                <div style="font-size:16px;font-weight:600;line-height:1.3;">Подпишись на VK</div>
                <div style="font-size:13px;opacity:0.85;line-height:1.4;">Там выходят анонсы, промокоды и ответы на вопросы быстрее, чем на сайте.</div>
                <div style="margin-top:auto;">
                    <a href="<?= e(setting('social_vk', 'https://vk.ru/galbraith_yt')) ?>" target="_blank" class="btn" style="background:#fff;color:#2787f5;text-decoration:none;width:100%;">Подписаться</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<h2 class="section-title">Новинки каталога</h2>
<div class="grid">
    <?php foreach ($latest as $m): $isPrem = !empty($m['is_premiere']); $isNew = !$isPrem && !empty($m['is_new']); ?>
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

<?php $nd = function_exists('nickOfDay') ? nickOfDay() : null; if ($nd): $ndg = nickGroupDefs(); ?>
<a href="/generator-nikov" class="nd-strip" style="margin-top:28px;">
    <?php if (!empty($nd['skin'])): ?><img src="/assets/img/nick/skins/<?= e($nd['skin']) ?>.webp" alt="" width="52" height="64" loading="lazy"><?php endif; ?>
    <span class="nd-text"><small>⭐ Ник дня</small><b><?= e($nd['nick']) ?></b></span>
    <span class="nd-tag"><?= e($ndg[$nd['group']]['label'] ?? '') ?></span>
    <span class="nd-cta">Сгенерировать свой ник →</span>
</a>
<?php endif; ?>

<div style="text-align:center;margin-top:32px;">
    <a href="/catalog" class="btn btn-primary">Все материалы →</a>
</div>

<script>
(function() {
    const carousel = document.getElementById('newsCarousel');
    if (!carousel) return;

    let autoScrollInterval;
    const cardWidth = 296; // 280px card + 16px gap
    const scrollDelay = 4000; // 4 seconds

    function startAutoScroll() {
        autoScrollInterval = setInterval(() => {
            const maxScroll = carousel.scrollWidth - carousel.clientWidth;
            if (carousel.scrollLeft >= maxScroll - 10) {
                carousel.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                carousel.scrollBy({ left: cardWidth, behavior: 'smooth' });
            }
        }, scrollDelay);
    }

    function stopAutoScroll() {
        clearInterval(autoScrollInterval);
    }

    carousel.addEventListener('mouseenter', stopAutoScroll);
    carousel.addEventListener('mouseleave', startAutoScroll);
    carousel.addEventListener('touchstart', stopAutoScroll, { passive: true });
    carousel.addEventListener('touchend', () => setTimeout(startAutoScroll, 3000));

    startAutoScroll();
})();

function scrollNews(dir) {
    const el = document.getElementById('newsCarousel');
    el.scrollBy({ left: dir * 296, behavior: 'smooth' });
}

// Lumo: плавное появление карточек при прокрутке
(function() {
    const cards = document.querySelectorAll('.grid > a.card');
    if (!cards.length) return;
    if (!('IntersectionObserver' in window)) { cards.forEach(c => c.classList.add('reveal-in')); return; }
    cards.forEach(c => c.classList.add('reveal'));
    const io = new IntersectionObserver(entries => {
        entries.forEach((en, i) => {
            if (en.isIntersecting) {
                en.target.style.transitionDelay = (i * 60) + 'ms';
                en.target.classList.add('reveal-in');
                io.unobserve(en.target);
            }
        });
    }, { threshold: 0.12 });
    cards.forEach(c => io.observe(c));
})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
