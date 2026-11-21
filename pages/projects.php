<?php
$pageTitle = 'Проекты';
include __DIR__ . '/../templates/header.php';
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

            <!-- Проекты на сайте -->
            <div style="background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); padding: 24px;">
                <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 16px; color: var(--g-accent);">Проекты на сайте</h2>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 12px;"><a href="/catalog" style="color: var(--g-text); text-decoration: none; font-size: 14px;">Каталог модов</a></li>
                    <li style="margin-bottom: 12px;"><a href="/marketplace" style="color: var(--g-text); text-decoration: none; font-size: 14px;">Торговая площадка</a></li>
                    <li style="margin-bottom: 12px;"><a href="/generator-nikov" style="color: var(--g-text); text-decoration: none; font-size: 14px;">Генератор ников</a></li>
                </ul>
            </div>
        </div>

        <div style="padding-top: 32px; border-top: 1px solid var(--g-border);">
            <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 16px; color: var(--g-accent);">Добавить проект</h2>
            <p style="color: var(--g-text-secondary); font-size: 14px;">Ссылки редактируются в админ-панели: <code>/admin/projects</code></p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>