<?php
$pageTitle = 'Проекты';
include __DIR__ . '/../templates/header.php';

$dbProjects = [];
try {
    $dbProjects = getDB()->query('SELECT * FROM projects ORDER BY sort_order ASC, id ASC')->fetchAll();
} catch (Throwable $e) {
    $dbProjects = [];
}
?>

<div class="projects-hero">
    <span class="hero-unusual-eyebrow"><i></i> Galbraith</span>
    <h1>Наши <em>проекты</em></h1>
    <p>Все площадки Galbraith в одном месте: соцсети, сервисы и разделы сайта.</p>
</div>

<div class="projects-grid">
    <!-- Социальные сети -->
    <section class="glass-tile project-tile">
        <div class="project-tile-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 4.5L2.9 11.7c-.8.3-.8 1.4.1 1.6l4.6 1.4 1.7 5.3c.3.8 1.3.9 1.8.3l2.5-2.9 4.7 3.5c.7.5 1.7.1 1.9-.7l3-14.2c.2-1-.8-1.8-1.7-1.5z"/><path d="M7.6 14.7L18.5 6.8"/></svg>
        </div>
        <h2>Социальные сети</h2>
        <ul class="project-links">
            <li><a href="https://t.me/galbraithmods" target="_blank" rel="noopener">Telegram <span>@galbraithmods</span></a></li>
            <li><a href="https://vk.com/galbraithmods" target="_blank" rel="noopener">VK <span>@galbraithmods</span></a></li>
            <li><a href="https://discord.gg/galbraithmods" target="_blank" rel="noopener">Discord <span>сервер</span></a></li>
        </ul>
    </section>

    <!-- Проекты, добавленные через админку -->
    <section class="glass-tile project-tile">
        <div class="project-tile-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 12l9 4 9-4"/><path d="M3 17l9 4 9-4"/></svg>
        </div>
        <h2>Наши проекты</h2>
        <?php if ($dbProjects): ?>
        <ul class="project-links">
            <?php foreach ($dbProjects as $p): ?>
            <li><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener"><?= e($p['title']) ?> <span>открыть →</span></a></li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="project-empty">Пока не добавлено ни одного проекта — управление в админ-панели.</p>
        <?php endif; ?>
    </section>

    <!-- Разделы сайта -->
    <section class="glass-tile project-tile">
        <div class="project-tile-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        </div>
        <h2>Разделы сайта</h2>
        <ul class="project-links">
            <li><a href="/catalog">Каталог модов <span>перейти →</span></a></li>
            <li><a href="/marketplace">Торговая площадка <span>перейти →</span></a></li>
            <li><a href="/generator-nikov">Генератор ников <span>перейти →</span></a></li>
        </ul>
    </section>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
