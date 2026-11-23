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
<body class="grain"<?= (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])) ? ' data-logged-in="1"' : '' ?>>
    <!-- Radial glow background is handled by CSS on body -->
    
    <header class="g-header" role="banner">
        <div class="g-header-inner">
            <a href="/" class="g-logo" aria-label="Galbraith — главная">GALBRAITH<span class="g-logo-dot">.</span></a>

            <nav class="g-nav" role="navigation" aria-label="Основная навигация">
                <a href="/pages/projects.php" class="g-nav-link">Проекты</a>
                <a href="/pages/marketplace.php" class="g-nav-link">Магазин</a>
                <a href="/pages/support.php" class="g-nav-link">Поддержка</a>
            </nav>

            <div class="g-header-right">
                <?php if (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])):
                    $gHeaderUser = currentUser(); ?>
                    <div class="header-popup-wrap">
                        <button type="button" class="g-header-btn header-popup-trigger" aria-label="Уведомления" onclick="toggleHeaderPopup('notifPopup')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.7 21a2 2 0 0 1-3.4 0"></path></svg>
                        </button>
                        <div id="notifPopup" class="header-popup">
                            <div class="header-popup-head"><strong>Уведомления</strong><a href="/notifications" class="header-popup-action">Все</a></div>
                        </div>
                    </div>
                    <a href="/pages/profile.php" class="g-header-btn g-header-account" title="Личный кабинет">
                        <span class="g-header-user-dot" aria-hidden="true"></span>
                        <span class="g-header-user-name"><?= e($gHeaderUser['name'] ?? 'Кабинет') ?></span>
                    </a>
                <?php else: ?>
                    <a href="/pages/login.php" class="g-header-btn">Вход</a>
                <?php endif; ?>
                <button class="g-header-burger" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-menu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Мобильное меню -->
        <div id="mobile-menu" class="g-mobile-menu">
            <a href="/pages/projects.php">Проекты</a>
            <a href="/pages/marketplace.php">Магазин</a>
            <a href="/pages/support.php">Поддержка</a>
            <?php if (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id'])): ?>
                <a href="/pages/profile.php">Кабинет</a>
                <a href="/pages/orders.php">Заказы</a>
                <a href="/pages/settings.php">Настройки</a>
                <a href="/pages/favorites.php">Избранное</a>
                <form action="/includes/auth.php" method="post">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="g-mobile-logout">Выход</button>
                </form>
            <?php else: ?>
                <a href="/pages/login.php">Вход</a>
                <a href="/pages/register.php">Регистрация</a>
            <?php endif; ?>
        </div>
    </header>

    <script>
    (function () {
        var burger = document.querySelector('.g-header-burger');
        var menu = document.getElementById('mobile-menu');
        if (!burger || !menu) return;
        burger.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
    </script>
    
    <main class="main-content page-fade" role="main">