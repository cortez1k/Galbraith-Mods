<?php
require_once __DIR__ . '/../includes/functions.php';
$logo = setting('logo_path');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$headerUser = null;
$unreadCount = 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Galbraith Mods') ?> — Galbraith Mods</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&family=Rajdhani:wght@500;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header-glass glass">
        <div class="container" style="display:flex; align-items:center; justify-content:space-between; padding:1rem 0;">
            <div style="display:flex; align-items:center; gap:1rem;">
                <?php if ($logo): ?>
                    <img src="<?= htmlspecialchars($logo) ?>" alt="logo" style="height:40px;">
                <?php endif; ?>
                <div class="logo-text" style="font-family:Rajdhani, Manrope, sans-serif; font-size:1.5rem; font-weight:700; letter-spacing:.05em;">Galbraith Mods</div>
            </div>
            <nav style="display:flex; gap:2rem; align-items:center;">
                <?php foreach (getPagesMenu() as $item): ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= ($currentPath === $item['url'] ? 'btn-primary' : 'btn-secondary') ?> btn"><?= htmlspecialchars($item['label']) ?></a>
                <?php endforeach; ?>
                <div style="position:relative;">
                    <button class="btn-secondary btn" style="position:relative;" onclick="document.getElementById('themeModal').style.display='flex'">
                        <span>Тема</span>
                        <?php if ($unreadCount): ?><span style="position:absolute; top:-6px; right:-6px; background:var(--accent); color:#fff; border-radius:50%; width:18px; height:18px; font-size:.7rem; display:flex; align-items:center; justify-content:center;"><?= $unreadCount ?></span><?php endif; ?>
                    </button>
                </div>
            </nav>
        </div>
    </header>
    <main>