<?php
$pageTitle = 'Проекты';

$dbProjects = [];
try {
    $dbProjects = getDB()->query('SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
} catch (Throwable $e) {}

// Соцсети сайта (не проектов) — общие настройки из админки.
$siteSocialMap = [
    'vk'         => 'social_vk',
    'youtube'    => 'social_yt',
    'telegram'   => 'social_telegram',
    'discord'    => 'social_discord',
    'instagram'  => 'social_instagram',
    'tiktok'     => 'social_tiktok',
    'boosty'     => 'social_boosty',
];
$socialTitles = [
    'vk'        => 'VK',
    'youtube'   => 'YouTube',
    'telegram'  => 'Telegram',
    'discord'   => 'Discord',
    'instagram' => 'Instagram',
    'tiktok'    => 'TikTok',
    'boosty'    => 'Boosty',
];
$siteSocials = [];
foreach ($siteSocialMap as $key => $settingKey) {
    $url = trim((string)setting($settingKey, ''));
    if ($url !== '') $siteSocials[$key] = $url;
}

function projectSocialIcon($key) {
    $icons = [
        'vk'        => '<path d="M13.16 17.5c-5.3 0-8.6-3.7-8.73-9.8h2.71c.09 4.48 2.08 6.38 3.62 6.77V7.7h2.57v3.86c1.51-.16 3.1-1.9 3.63-3.86h2.55c-.4 2.4-2.1 4.13-3.3 4.86 1.2.6 3.13 2.1 3.86 4.94h-2.8c-.57-1.86-1.99-3.3-3.94-3.46v3.46h-.17z"/>',
        'youtube'   => '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.76-1.77C18.25 5 12 5 12 5s-6.25 0-7.84.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.76 1.77C5.75 19 12 19 12 19s6.25 0 7.84-.43a2.5 2.5 0 0 0 1.76-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15V9l5.2 3L10 15z"/>',
        'telegram'  => '<path d="M21.9 4.3 19 19.4c-.2 1-.8 1.2-1.6.8l-4.5-3.3-2.2 2.1c-.3.3-.5.5-1 .5l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L7.3 13.2l-4.4-1.4c-1-.3-1-1 .2-1.4l17.2-6.6c.8-.3 1.5.2 1.6 1.5z"/>',
        'discord'   => '<path d="M19.3 5.6A16 16 0 0 0 15.4 4.3l-.2.4a14 14 0 0 0-6.4 0l-.2-.4a16 16 0 0 0-3.9 1.3C2.2 9.1 1.5 12.5 1.8 15.8a16 16 0 0 0 4.8 2.4l.6-1a10 10 0 0 1-1.6-.8l.4-.3a11 11 0 0 0 9.6 0l.4.3a10 10 0 0 1-1.6.8l.6 1a16 16 0 0 0 4.8-2.4c.4-3.9-.6-7.2-2.5-10.2zM8.7 13.8c-.9 0-1.6-.8-1.6-1.8s.7-1.8 1.6-1.8 1.7.8 1.6 1.8c0 1-.7 1.8-1.6 1.8zm6.6 0c-.9 0-1.6-.8-1.6-1.8s.7-1.8 1.6-1.8 1.7.8 1.6 1.8c0 1-.7 1.8-1.6 1.8z"/>',
        'instagram' => '<path d="M12 4.6c2.4 0 2.7 0 3.6.1.9 0 1.4.2 1.7.3.4.2.7.4 1 .7.3.3.5.6.7 1 .1.3.3.8.3 1.7.1.9.1 1.2.1 3.6s0 2.7-.1 3.6c0 .9-.2 1.4-.3 1.7-.2.4-.4.7-.7 1-.3.3-.6.5-1 .7-.3.1-.8.3-1.7.3-.9.1-1.2.1-3.6.1s-2.7 0-3.6-.1c-.9 0-1.4-.2-1.7-.3-.4-.2-.7-.4-1-.7-.3-.3-.5-.6-.7-1-.1-.3-.3-.8-.3-1.7C4.6 14.7 4.6 14.4 4.6 12s0-2.7.1-3.6c0-.9.2-1.4.3-1.7.2-.4.4-.7.7-1 .3-.3.6-.5 1-.7.3-.1.8-.3 1.7-.3.9-.1 1.2-.1 3.6-.1zM12 3c-2.4 0-2.8 0-3.7.1-.9 0-1.6.2-2.2.4a4.4 4.4 0 0 0-1.6 1c-.5.5-.8 1-1 1.6-.2.6-.4 1.3-.4 2.2C3 9.2 3 9.6 3 12s0 2.8.1 3.7c0 .9.2 1.6.4 2.2.2.6.5 1.1 1 1.6.5.5 1 .8 1.6 1 .6.2 1.3.4 2.2.4.9.1 1.3.1 3.7.1s2.8 0 3.7-.1c.9 0 1.6-.2 2.2-.4.6-.2 1.1-.5 1.6-1 .5-.5.8-1 1-1.6.2-.6.4-1.3.4-2.2.1-.9.1-1.3.1-3.7s0-2.8-.1-3.7c0-.9-.2-1.6-.4-2.2a4.4 4.4 0 0 0-1-1.6c-.5-.5-1-.8-1.6-1-.6-.2-1.3-.4-2.2-.4C14.8 3 14.4 3 12 3zm0 4.4a4.6 4.6 0 1 0 0 9.2 4.6 4.6 0 0 0 0-9.2zm0 7.6a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm5.9-7.8a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0z"/>',
        'tiktok'    => '<path d="M16.6 3h-2.9v12.1a2.4 2.4 0 1 1-2.4-2.4c.2 0 .5 0 .7.1V9.8a5.3 5.3 0 1 0 4.6 5.3V8.6a5.9 5.9 0 0 0 3.4 1.1V6.8a3.4 3.4 0 0 1-3.4-3.4V3z"/>',
        'boosty'    => '<path d="M4 5.5h16v4.2c0 4-1.7 6.1-4.4 7.2-.4.2-.6.4-.6.8V20h-2.9v-2.3c0-.4-.2-.6-.6-.8C8.6 15.8 7 13.7 7 9.7h3.1c0 2.8.8 4.2 2.1 4.9.3.1.5.2.8.2V5.5z"/>',
    ];
    return $icons[$key] ?? '<path d="M10.6 13.4a1 1 0 0 0 1.4 0l4.2-4.2-1.4-1.4-4.2 4.2a1 1 0 0 0 0 1.4zM12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/>';
}

function projectIconUrl($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if ($path[0] !== '/') $path = '/' . ltrim($path, './');
    return $path;
}

include __DIR__ . '/../templates/header.php';
?>
<link rel="stylesheet" href="/assets/css/projects.css?v=<?= @filemtime(__DIR__ . '/../assets/css/projects.css') ?>">

<div class="pj-wrap">
    <header class="pj-hero">
        <span class="pj-eyebrow"><i></i>Galbraith Ecosystem</span>
        <h1>Проекты <span class="pj-grad">Galbraith</span></h1>
        <p>Сервисы, инструменты и сообщества, которые мы делаем для игроков Amazing RP.</p>
        <div class="pj-hero-stats">
            <div><strong><?= count($dbProjects) ?></strong><span>проектов</span></div>
            <div><strong><?= count($siteSocials) ?></strong><span>каналов</span></div>
            <div><strong>24/7</strong><span>на связи</span></div>
        </div>
    </header>

    <?php if ($siteSocials): ?>
    <section class="pj-socials" aria-labelledby="pj-socials-title">
        <div class="pj-socials-head">
            <div>
                <h2 id="pj-socials-title">Galbraith в сети</h2>
                <p>Официальные каналы всего сайта: обновления, поддержка, новости</p>
            </div>
            <span class="pj-socials-count"><?= count($siteSocials) ?> <?= count($siteSocials) === 1 ? 'канал' : 'канала' ?></span>
        </div>
        <div class="pj-social-list">
            <?php foreach ($siteSocials as $key => $url): ?>
                <a class="pj-social" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">
                    <span class="pj-social-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><?= projectSocialIcon($key) ?></svg></span>
                    <span class="pj-social-txt"><strong><?= e($socialTitles[$key] ?? ucfirst($key)) ?></strong><span><?= e(preg_replace('~^https?://(www\.)?~i', '', rtrim($url, '/'))) ?></span></span>
                    <svg class="pj-social-arr" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="pj-list" aria-labelledby="pj-list-title">
        <div class="pj-list-head">
            <h2 id="pj-list-title">Наши проекты</h2>
            <p>Основные продукты экосистемы</p>
        </div>

        <?php if ($dbProjects): ?>
            <div class="pj-grid">
                <?php foreach ($dbProjects as $i => $p):
                    $icon = projectIconUrl($p['icon_path'] ?? '');
                    $cat = trim((string)($p['category'] ?? ''));
                ?>
                <article class="pj-card" style="--i:<?= (int)$i ?>">
                    <span class="pj-card-glow" aria-hidden="true"></span>
                    <div class="pj-card-top">
                        <span class="pj-ico">
                            <?php if ($icon): ?>
                                <img src="<?= e($icon) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="pj-ico-ph"><?= e(mb_strtoupper(mb_substr(trim($p['title']), 0, 1))) ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if ($cat !== ''): ?><span class="pj-tag"><?= e($cat) ?></span><?php endif; ?>
                    </div>
                    <h3><?= e($p['title']) ?></h3>
                    <?php if (!empty($p['description'])): ?>
                        <p class="pj-desc"><?= e(mb_substr(trim($p['description']), 0, 180)) ?></p>
                    <?php endif; ?>
                    <a class="pj-open" href="<?= e($p['url']) ?>" <?= preg_match('~^https?://~i', (string)$p['url']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                        Перейти
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="pj-empty">
                <span class="pj-empty-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
                <h3>Проекты скоро появятся</h3>
                <p>Мы готовим новые сервисы для Amazing RP. Загляните позже или подпишитесь на наши каналы.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
