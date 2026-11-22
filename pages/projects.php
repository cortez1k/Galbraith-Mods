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

<div class="container" style="padding-top: 40px; padding-bottom: 40px;">
    <div style="max-width: 800px; margin: 0 auto;">
        <h1 style="font-size: 32px; font-weight: 700; margin-bottom: 24px;">Проекты Galbraith</h1>

        <div style="display: grid; gap: 24px; margin-bottom: 40px;">
            <!-- Социальные сети -->
            <div style="background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); padding: 24px;">
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 16px; color: var(--g-accent);">Социальные сети</h2>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 12px;"><a href="https://t.me/galbraithmods" style="color: var(--g-text); text-decoration: none; font-size: 14px;">Telegram @galbraithmods</a></li>
                    <li style="margin-bottom: 12px;"><a href="https://vk.com/galbraithmods" style="color: var(--g-text); text-decoration: none; font-size: 14px;">VK @galbraithmods</a></li>
                    <li style="margin-bottom: 12px;"><a href="https://discord.gg/galbraithmods" style="color: var(--g-text); text-decoration: none; font-size: 14px;">Discord</a></li>
                </ul>
            </div>

            <!-- Проекты, добавленные через админку -->
            <div style="background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); padding: 24px;">
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 16px; color: var(--g-accent);">Наши проекты</h2>
                <?php if ($dbProjects): ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($dbProjects as $p): ?>
                    <li style="margin-bottom: 12px;"><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener" style="color: var(--g-text); text-decoration: none; font-size: 14px;"><?= e($p['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p style="color: var(--g-text-secondary); font-size: 14px;">Пока не добавлено ни одного проекта.</p>
                <?php endif; ?>
            </div>

            <!-- Разделы сайта -->
            <div style="background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); padding: 24px;">
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 16px; color: var(--g-accent);">Разделы сайта</h2>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 12px;"><a href="/catalog" style="color: var(--g-text); text-decoration: none; font-size: 14px;">Каталог модов</a></li>
                    <li style="margin-bottom: 12px;"><a href="/marketplace" style="color: var(--g-text); text-decoration: none; font-size: 14px;">Торговая площадка</a></li>
                    <li style="margin-bottom: 12px;"><a href="/generator-nikov" style="color: var(--g-text); text-decoration: none; font-size: 14px;">Генератор ников</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
