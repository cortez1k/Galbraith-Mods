<?php
$pageTitle = 'Проекты';
include __DIR__ . '/../templates/header.php';

$dbProjects = [];
try {
    $dbProjects = getDB()->query('SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
} catch (Throwable $e) {
    $dbProjects = [];
}

$socialIcons = [
    'telegram' => '📩',
    'vk' => '📘',
    'youtube' => '▶️',
    'discord' => '💬',
    'instagram' => '📷',
];
$socialLabels = [
    'telegram' => 'Telegram',
    'vk' => 'VK',
    'youtube' => 'YouTube',
    'discord' => 'Discord',
    'instagram' => 'Instagram',
];

// Социальные сети сайта из общих настроек (social_yt, social_vk и т.д.)
$siteSocials = [];
foreach ($socialLabels as $key => $label) {
    $settingKeys = ['vk' => 'social_vk', 'youtube' => 'social_yt'];
    $v = setting($settingKeys[$key] ?? ('social_' . $key));
    if ($v) {
        $siteSocials[$key] = ['label' => $label, 'url' => $v];
    }
}
?>

<div class="container" style="padding-top: 40px; padding-bottom: 40px;">
    <div style="max-width: 1100px; margin: 0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom: 32px; flex-wrap: wrap;">
            <div>
                <h1 style="font-size: 32px; font-weight: 700; margin-bottom: 8px;">Проекты Galbraith</h1>
                <p style="font-size: 15px; color: var(--g-text-secondary);">Наши сервисы, моды и сообщества для Amazing RP.</p>
            </div>
        </div>

        <!-- Социальные сети сайта (из настроек) -->
        <?php if ($siteSocials): ?>
        <div style="background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); padding: 20px; margin-bottom: 32px;">
            <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 14px; color: var(--g-accent);">Социальные сети сайта</h2>
            <div style="display:flex; gap: 14px; flex-wrap: wrap;">
                <?php foreach ($siteSocials as $key => $s): ?>
                <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" style="display:flex; align-items:center; gap:8px; padding: 10px 16px; background: var(--g-bg); border: 1px solid var(--g-border); border-radius: var(--g-radius); color: var(--g-text); text-decoration: none; font-size: 14px;">
                    <span style="font-size: 16px;"><?= $socialIcons[$key] ?? '🔗' ?></span>
                    <span><?= e($s['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Проекты -->
        <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 20px;">Наши проекты</h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
            <?php if ($dbProjects): foreach ($dbProjects as $p):
                $socialsP = $p['socials'] ? json_decode($p['socials'], true) : [];
            ?>
                <a href="<?= e($p['url']) ?>" target="_blank" rel="noopener" style="text-decoration:none; color: inherit; display:block; background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); overflow: hidden; transition: transform 0.15s, box-shadow 0.15s;">
                    <div style="aspect-ratio: 4/3; background: var(--g-bg); border-bottom: 1px solid var(--g-border); position: relative;">
                        <?php if (!empty($p['icon_path'])): ?>
                            <img src="<?= e(UPLOAD_URL . $p['icon_path']) ?>" alt="<?= e($p['title']) ?>" style="width:100%; height:100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size: 48px; color: var(--g-text-secondary);">🛠️</div>
                        <?php endif; ?>
                        <?php if ((int)$p['is_active'] === 0): ?>
                            <div style="position:absolute; top:8px; left:8px; background: rgba(0,0,0,0.7); color: #ff5f5f; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 700;">Выключено</div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 18px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom: 10px;">
                            <h3 style="font-size: 18px; font-weight: 700; margin: 0;"><?= e($p['title']) ?></h3>
                            <?php if (!empty($p['category'])): ?>
                                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 8px; background: var(--g-bg); border: 1px solid var(--g-border); border-radius: 6px; color: var(--g-text-secondary); white-space: nowrap;"><?= e($p['category']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 14px; color: var(--g-text-secondary); line-height: 1.5; margin-bottom: 14px;"><?= e($p['description'] ?: 'Проект без описания.') ?></p>
                        <div style="display:flex; gap:8px; flex-wrap: wrap;">
                            <?php foreach ($socialsP as $k => $v): ?>
                                <a href="<?= e($v) ?>" target="_blank" rel="noopener" style="display:flex; align-items:center; gap:6px; font-size: 13px; color: var(--g-accent-light); text-decoration: none;">
                                    <span style="font-size: 14px;"><?= $socialIcons[$k] ?? '🔗' ?></span>
                                    <span><?= e($socialLabels[$k] ?? ucfirst($k)) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; else: ?>
                <div style="grid-column: 1 / -1; background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); padding: 40px; text-align: center; color: var(--g-text-secondary);">
                    Пока не добавлено ни одного активного проекта.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
