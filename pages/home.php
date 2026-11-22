<?php
$latest = getMaterials(6);
$news = getNews(10);
$ytVid = setting('youtube_last_video_id');
$ytInfo = $ytVid ? getYouTubeVideoInfo($ytVid) : null;
$newMaterials = getMaterials(null, null, null, 'new');
include __DIR__ . '/../templates/header.php';
$pageTitle = 'Главная';
?>
    <section class="hero glass" style="padding:4rem 0; position:relative; overflow:hidden;">
        <div class="bg-glow g-glow-breathe" style="inset:-20%; z-index:-1; background:radial-gradient(circle at 30% 30%, var(--accent-dim), transparent 50%);"></div>
        <div class="container">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:3rem; align-items:center;">
                <div>
                    <h1 style="font-family:Rajdhani, Manrope, sans-serif; font-size:3.5rem; line-height:1.1; margin:0 0 1.5rem;">Готовы к новому уровню?</h1>
                    <p style="color:var(--text-2); font-size:1.1rem; margin:0 0 2rem; max-width:480px;">Лучшие моды, сборки и ресурсы для вашего сервера. Все в одном месте.</p>
                    <div style="display:flex; gap:1rem;">
                        <a href="/materials" class="btn-primary btn-lg">Каталог модов</a>
                        <a href="/projects" class="btn-secondary btn-lg">Проекты</a>
                    </div>
                </div>
                <div class="hero-stats" style="display:flex; gap:2rem;">
                    <div class="stat">
                        <div class="az-num" style="font-family:JetBrains Mono, monospace; font-size:2rem; font-weight:700;"><?= count($latest) ?></div>
                        <div style="color:var(--text-2); font-size:.85rem;">Матерьялов</div>
                    </div>
                    <div class="stat">
                        <div class="az-num" style="font-family:JetBrains Mono, monospace; font-size:2rem; font-weight:700;"><?= count($news) ?></div>
                        <div style="color:var(--text-2); font-size:.85rem;">Новостей</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section style="padding:3rem 0;">
        <div class="container">
            <h2 class="section-title">Новые материалы</h2>
            <div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));">
                <?php foreach ($latest as $m): ?>
                    <div class="card glass reveal">
                        <?php if (!empty($m['image'])): ?><img src="<?= htmlspecialchars($m['image']) ?>" alt="" class="card-img"><?php endif; ?>
                        <div class="card-body">
                            <div class="card-badge" style="color:var(--accent); font-weight:700;"><?= htmlspecialchars($m['type'] ?? 'Мод') ?></div>
                            <h3 class="card-title"><?= htmlspecialchars($m['title']) ?></h3>
                            <p class="card-desc"><?= htmlspecialchars($m['desc'] ?? $m['description'] ?? '') ?></p>
                            <div class="card-meta" style="margin-top:auto;"><?= htmlspecialchars($m['price'] ?? 'Бесплатно') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section style="padding:3rem 0; background:var(--bg-soft);">
        <div class="container">
            <h2 class="section-title">Новости</h2>
            <div class="grid" style="grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));">
                <?php foreach ($news as $n): ?>
                    <div class="card glass reveal">
                        <div class="card-body">
                            <h3 class="card-title"><?= htmlspecialchars($n['title']) ?></h3>
                            <p class="card-desc"><?= htmlspecialchars($n['desc'] ?? $n['description'] ?? '') ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

<?php include __DIR__ . '/../templates/footer.php'; ?>