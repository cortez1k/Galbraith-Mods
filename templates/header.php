<?php
require_once __DIR__ . '/../includes/functions.php';
$logo = setting('logo_path');
$menuPages = getPagesMenu();
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$headerUser = isLoggedIn() ? currentUser() : null;
$unreadCount = $headerUser ? getUnreadNotificationsCount($headerUser['id']) : 0;
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(isset($pageTitleFull) ? $pageTitleFull : (($pageTitle ?? 'Galbraith') . ' — Сборки и моды для AMAZING RP')) ?></title>
    <meta name="description" content="<?= e($pageDesc ?? 'Качественные сборки, моды и AHK-скрипты для AMAZING RP (Amazing RP)') ?>">
    <?= isset($headExtra) ? $headExtra : '' ?>
    <link rel="icon" href="<?= e(setting('favicon_path') ? UPLOAD_URL . setting('favicon_path') : '/assets/uploads/logos/favicon.ico') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Rajdhani:wght@500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: '1' ?>">
    <style id="dynamic-theme">
        :root {
            --g-accent: <?= e(setting('accent_default', '#E81C5A')) ?>;
        }
    </style>
</head>
<body data-logged-in="<?= !empty($_SESSION['user_id']) ? '1' : '0' ?>">
    <?php if (setting('banner_enabled', '0') === '1' && (setting('banner_highlight') || setting('banner_message'))): ?>
    <div class="top-banner">
        <div class="container top-banner-inner">
            <div class="top-banner-left">
                <?php if (setting('banner_icon')): ?><span class="top-banner-icon"><?= e(setting('banner_icon')) ?></span><?php endif; ?>
                <?php if (setting('banner_brand')): ?>
                    <span class="top-banner-brand">
                        <?= e(setting('banner_brand')) ?>
                        <?php if (setting('banner_brand_tag')): ?><span class="top-banner-brand-tag"><?= e(setting('banner_brand_tag')) ?></span><?php endif; ?>
                    </span>
                    <span class="top-banner-divider" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="top-banner-text">
                    <?php if (setting('banner_highlight')): ?><strong class="top-banner-highlight"><?= e(setting('banner_highlight')) ?></strong><?php endif; ?>
                    <?= e(setting('banner_message')) ?>
                </span>
            </div>
            <?php if (setting('banner_button_text')): ?>
                <a href="<?= e(setting('banner_button_url') ?: '#') ?>" class="top-banner-btn"><?= e(setting('banner_button_text')) ?>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <header class="header header-capsule">
        <div class="container header-inner">
            <a href="/" class="logo">
                <?php if ($logo): ?>
                    <img src="<?= e(UPLOAD_URL . $logo) ?>" alt="G" style="width:34px;height:34px;border-radius:8px;object-fit:cover;">
                <?php else: ?>
                    <div class="logo-icon">G</div>
                <?php endif; ?>
                Galbraith
            </a>
            <nav class="nav">
                <a href="/catalog" class="<?= $currentPath === '/catalog' ? 'active' : '' ?>">Каталог</a>
                <a href="/marketplace" class="<?= $currentPath === '/marketplace' ? 'active' : '' ?>">Торговая площадка</a>
                <a href="/projects" class="<?= $currentPath === '/projects' ? 'active' : '' ?>">Проекты</a>
                <?php foreach ($menuPages as $p): ?>
                    <a href="/page/<?= e($p['slug']) ?>"><?= e($p['title']) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="header-actions">
                <button class="icon-btn nav-toggle" type="button" onclick="toggleMobileNav()" aria-label="Меню" aria-expanded="false" id="navToggleBtn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path id="navToggleIcon" d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
                <button class="icon-btn" onclick="toggleThemeModal()" aria-label="Тема" title="Настройки темы">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                </button>
                <button class="icon-btn" onclick="document.getElementById('donateModal').classList.add('active')" aria-label="Поддержать" title="Поддержать проект">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </button>
                <?php if ($headerUser): ?>
                    <div class="header-popup-wrap">
                        <button class="icon-btn header-popup-trigger" type="button" onclick="toggleHeaderPopup('notificationsPopup')" aria-label="Уведомления" title="Уведомления">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <?php if ($unreadCount > 0): ?><span class="header-badge" id="notificationBadge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?>
                        </button>
                        <div class="header-popup notifications-popup" id="notificationsPopup">
                            <div class="header-popup-head"><strong>Уведомления</strong><?php if($unreadCount>0): ?><button class="header-popup-action" type="button" onclick="markNotificationsRead()">Прочитать всё</button><?php endif; ?></div>
                            <div class="notifications-list" id="notificationsList">
                            <?php $headerNotifications=getNotifications($headerUser['id'],15); if(!$headerNotifications): ?><div class="notifications-empty">🔔<br><span>Уведомлений пока нет</span></div><?php else: foreach($headerNotifications as $n): $nd=json_decode($n['data']??'{}',true)?:[]; ?>
                                <a href="/notifications/<?= (int)$n['id'] ?>" class="notification-item <?= empty($n['is_read'])?'unread ':'' ?><?= $n['type']==='auto_delivery'?' auto-delivery-notification':'' ?>"><div class="notification-icon"><?= e(notificationIcon($n['type'])) ?></div><div class="notification-content"><div class="notification-title"><?= e(notificationSubject($n['type'])) ?></div><div class="notification-text"><?= e(notificationPreview($n['type'],$nd)) ?></div><div class="notification-time notification-time-local" data-utc="<?= e(notificationUtcIso($n['created_at'])) ?>"><?= e(formatNotificationTime($n['created_at'])) ?></div></div></a>
                            <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (hasRole('trader')): ?><a href="/seller/messages" class="icon-btn" aria-label="Чаты" title="Все чаты"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-9 8.3 9.7 9.7 0 0 1-4-.8L3 21l1.8-4.3A8.4 8.4 0 1 1 21 11.5z"/></svg></a><?php endif; ?>
                    <div class="user-menu" style="position:relative;">
                        <button class="icon-btn" onclick="this.parentElement.classList.toggle('open')" aria-label="Профиль" style="padding:0;overflow:hidden;border-radius:50%;width:34px;height:34px;">
                            <?php if (!empty($headerUser['avatar'])): ?><img src="<?= e(avatarUrl($headerUser['avatar'],$headerUser['id'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><img src="<?= e(avatarUrl('',$headerUser['id'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?>
                        </button>
                        <div class="user-menu-dropdown" style="display:none;position:absolute;right:0;top:44px;background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius-sm);min-width:200px;padding:8px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.3);">
                            <div style="padding:8px 10px;font-size:13px;color:var(--g-text-secondary);border-bottom:1px solid var(--g-border);margin-bottom:6px;"><?= e($headerUser['name']) ?><br><span style="font-size:11px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= e(roleColor($headerUser['role'])) ?>;margin-right:5px;"></span><?= e(roleLabel($headerUser['role'])) ?></span></div>
                            <a href="/profile" style="display:block;padding:8px 10px;border-radius:6px;font-size:14px;">Профиль</a>
                            <a href="/profile/settings" style="display:block;padding:8px 10px;border-radius:6px;font-size:14px;">Настройки</a>
                            <?php if (hasRole('trader')): ?><a href="/seller" style="display:block;padding:8px 10px;border-radius:6px;font-size:14px;">Панель продавца</a><?php endif; ?>
                            <?php if (hasRole(['moderator','admin'])): ?><a href="/moderator" style="display:block;padding:8px 10px;border-radius:6px;font-size:14px;">Модерация</a><?php endif; ?>
                            <?php if (hasRole(['support','admin'])): ?><a href="/support/chats" style="display:block;padding:8px 10px;border-radius:6px;font-size:14px;">Панель поддержки</a><?php endif; ?>
                            <?php if (hasRole('admin')): ?><a href="/admin" style="display:block;padding:8px 10px;border-radius:6px;font-size:14px;">Админка</a><?php endif; ?>
                            <a href="/logout" style="display:block;padding:8px 10px;border-radius:6px;font-size:14px;color:#ff5050;">Выйти</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login" class="btn btn-primary" style="padding:8px 16px;font-size:14px;">Войти</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <nav class="mobile-nav" id="mobileNav">
        <a href="/catalog" class="<?= $currentPath === '/catalog' ? 'active' : '' ?>">Каталог</a>
        <a href="/marketplace" class="<?= $currentPath === '/marketplace' ? 'active' : '' ?>">Торговая площадка</a>
        <a href="/projects" class="<?= $currentPath === '/projects' ? 'active' : '' ?>">Проекты</a>
        <?php foreach ($menuPages as $p): ?>
            <a href="/page/<?= e($p['slug']) ?>"><?= e($p['title']) ?></a>
        <?php endforeach; ?>
        <?php if ($headerUser): ?>
            <div class="mobile-nav-divider"></div>
            <a href="/profile">👤 Личный кабинет</a>
            <a href="/profile/settings">⚙️ Настройки</a>
            <?php if (hasRole('trader')): ?><a href="/seller">🛍️ Панель продавца</a><?php endif; ?>
            <?php if (hasRole(['moderator','admin'])): ?><a href="/moderator">🛡️ Модерация</a><?php endif; ?>
            <?php if (hasRole(['support','admin'])): ?><a href="/support/chats">🆘 Панель поддержки</a><?php endif; ?>
            <?php if (hasRole('admin')): ?><a href="/admin">⚙️ Админка</a><?php endif; ?>
            <a href="/logout" style="color:var(--g-danger);">Выйти</a>
        <?php else: ?>
            <div class="mobile-nav-divider"></div>
            <a href="/login" class="btn btn-primary" style="margin:4px 16px;">Войти</a>
        <?php endif; ?>
    </nav>
    <style>.user-menu.open .user-menu-dropdown{display:block !important;}</style>
    <script>
        function toggleMobileNav(){
            const nav = document.getElementById('mobileNav');
            const btn = document.getElementById('navToggleBtn');
            const icon = document.getElementById('navToggleIcon');
            const open = nav.classList.toggle('open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            icon.setAttribute('d', open ? 'M6 6l12 12M6 18L18 6' : 'M3 6h18M3 12h18M3 18h18');
            document.body.style.overflow = open ? 'hidden' : '';
        }
    </script>
    <script>
        document.addEventListener('click', function(e){
            document.querySelectorAll('.user-menu.open').forEach(function(m){
                if (!m.contains(e.target)) m.classList.remove('open');
            });
        });
    </script>

    <main class="container" style="padding-top: 24px; padding-bottom: 40px;">
