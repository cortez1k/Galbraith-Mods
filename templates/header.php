<?php
// templates/header.php
// Дизайн-система v4 «Majestic Dark Neon» — шапка сайта
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Galbraith') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <?= $extraHead ?? '' ?>
</head>
<body class="grain">
    <!-- Radial glow background is handled by CSS on body -->
    
    <header class="header" role="banner">
        <div class="header-inner">
            <a href="/" class="logo" aria-label="Galbraith — главная">
                <span class="logo-icon" aria-hidden="true"></span>
                <span class="logo-text">GALBRAITH<span class="logo-dot">.</span></span>
            </a>
            
            <nav class="header-nav" role="navigation" aria-label="Основная навигация">
                <a href="/pages/projects.php" class="nav-link">Проекты</a>
                <a href="/pages/marketplace.php" class="nav-link">Магазин</a>
                <a href="/pages/support.php" class="nav-link">Поддержка</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/pages/profile.php" class="nav-link">Кабинет</a>
                    <a href="/pages/orders.php" class="nav-link">Заказы</a>
                    <a href="/pages/settings.php" class="nav-link">Настройки</a>
                    <a href="/includes/auth.php?action=logout" class="btn btn-ghost">Выход</a>
                <?php else: ?>
                    <a href="/pages/login.php" class="btn btn-primary">Вход</a>
                    <a href="/pages/register.php" class="btn btn-ghost">Регистрация</a>
                <?php endif; ?>
            </nav>
            
            <button class="header-mobile-toggle" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
        
        <!-- Mobile menu -->
        <div id="mobile-menu" class="header-mobile-menu" hidden>
            <nav class="mobile-nav">
                <a href="/pages/projects.php" class="mobile-nav-link">Проекты</a>
                <a href="/pages/marketplace.php" class="mobile-nav-link">Магазин</a>
                <a href="/pages/support.php" class="mobile-nav-link">Поддержка</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/pages/profile.php" class="mobile-nav-link">Кабинет</a>
                    <a href="/pages/orders.php" class="mobile-nav-link">Заказы</a>
                    <a href="/pages/settings.php" class="mobile-nav-link">Настройки</a>
                    <form action="/includes/auth.php" method="post" style="display:inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="mobile-nav-link btn btn-ghost" style="width:100%;text-align:left;padding:0">Выход</button>
                    </form>
                <?php else: ?>
                    <a href="/pages/login.php" class="mobile-nav-link btn btn-primary" style="display:block;text-align:center">Вход</a>
                    <a href="/pages/register.php" class="mobile-nav-link btn btn-ghost" style="display:block;text-align:center">Регистрация</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <main class="main-content page-fade" role="main">