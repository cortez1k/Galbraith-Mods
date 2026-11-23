<?php
require_once __DIR__ . '/../includes/functions.php';
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$staffUser = currentUser();
$adminUnread = $staffUser ? getUnreadNotificationsCount($staffUser['id']) : 0;

$navGroups = [
    'Обзор' => [
        ['/admin', '📊', 'Дашборд'],
        ['/admin/statistics', '📈', 'Статистика'],
    ],
    'Контент' => [
        ['/admin/materials', '📦', 'Материалы'],
        ['/admin/categories', '🗂️', 'Категории'],
        ['/admin/tasks', '✅', 'Задания (подписки)'],
        ['/admin/pages', '📄', 'Страницы'],
        ['/admin/news', '📰', 'Новости'],
    ],
    'Маркетплейс' => [
        ['/admin/moderation', '🔍', 'Модерация товаров'],
        ['/admin/marketplace', '🗳️', 'Витрина: порядок/закреп'],
        ['/admin/orders', '🧾', 'Заказы'],
        ['/support/chats/new', '🆘', 'Поддержка (чаты)'],
        ['/admin/quick-replies', '⚡', 'Быстрые ответы'],
        ['/admin/seller-chats', '💬', 'Чаты: вопросы продавцам'],
        ['/admin/auto-delivery', '⚡', 'Товары с автовыдачей'],
        ['/admin/promos', '🏷️', 'Промокоды'],
        ['/admin/reviews', '⭐', 'Отзывы'],
    ],
    'Пользователи' => [
        ['/admin/users', '👤', 'Пользователи и роли'],
        ['/admin/traders', '🏪', 'Торговцы: бонусы/дисциплина'],
        ['/admin/tickets', '🎫', 'Тикеты'],
        ['/admin/comments', '💬', 'Комментарии'],
    ],
    'Бонусы и рефералка' => [
        ['/admin/bonuses', '🎁', 'Бонусы покупателей'],
        ['/admin/bonus-tasks', '🏆', 'Задания для бонусов'],
        ['/admin/referrals', '🔗', 'Реферальная программа'],
    ],
    'Финансы' => [
        ['/admin/withdrawals', '💸', 'Заявки на вывод'],
    ],
    'Система' => [
        ['/admin/vk-broadcast', '📢', 'Рассылка в VK'],
        ['/admin/settings', '⚙️', 'Настройки'],
        ['/admin/totp', '🔐', '2FA защита'],
        ['/admin/password', '🔑', 'Сменить пароль'],
        ['/admin/logs', '🗒️', 'Логи действий'],
        ['/admin/nicks', '🎭', 'Генератор ников'],
        ['/admin/projects', '🛠️', 'Проекты'],
    ],
];

// Flat lookup for the current page's title (used in the breadcrumb bar).
$currentLabel = $pageTitle ?? 'Админ-панель';
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($currentLabel) ?> — Админка Galbraith</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { margin: 0; }
        .admin-shell { display: flex; min-height: 100vh; }
        .admin-sidebar {
            width: 260px; flex-shrink: 0; background: var(--g-card); border-right: 1px solid var(--g-border);
            display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .admin-sidebar-header { padding: 18px 20px; border-bottom: 1px solid var(--g-border); display: flex; align-items: center; gap: 10px; }
        .admin-sidebar-header .logo-icon { width: 32px; height: 32px; font-size: 14px; }
        .admin-nav-group { padding: 14px 12px 4px; }
        .admin-nav-group-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--g-text-secondary); padding: 0 10px 6px; }
        .admin-nav-link { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; color: var(--g-text-secondary); text-decoration: none; font-size: 14px; margin-bottom: 2px; }
        .admin-nav-link:hover { background: var(--g-bg); color: var(--g-text-primary, #fff); }
        .admin-nav-link.active { background: var(--g-accent); color: #fff; font-weight: 600; }
        .admin-sidebar-footer { margin-top: auto; padding: 14px 16px; border-top: 1px solid var(--g-border); display: flex; flex-direction: column; gap: 8px; }
        .admin-main { flex: 1; min-width: 0; }
        .admin-topbar { position: sticky; top: 0; z-index: 10; background: var(--g-bg); border-bottom: 1px solid var(--g-border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .admin-content { padding: 24px 28px 60px; }
        .admin-burger { display: none; }
        @media (max-width: 900px) {
            .admin-sidebar { position: fixed; left: -280px; top: 0; z-index: 100; transition: left 0.2s; box-shadow: 0 0 30px rgba(0,0,0,0.4); }
            .admin-sidebar.open { left: 0; }
            .admin-burger { display: inline-flex; }
            .admin-content { padding: 16px; }
            .admin-topbar { padding: 12px 16px; }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-header">
            <div class="logo-icon">G</div>
            <div>
                <div style="font-weight:700;font-size:14px;">Galbraith</div>
                <div style="font-size:11px;color:var(--g-text-secondary);">Админ-панель</div>
            </div>
        </div>
        <div style="flex:1;overflow-y:auto;">
        <?php foreach ($navGroups as $groupTitle => $links): ?>
            <div class="admin-nav-group">
                <div class="admin-nav-group-title"><?= e($groupTitle) ?></div>
                <?php foreach ($links as [$href, $icon, $label]): ?>
                    <a href="<?= $href ?>" class="admin-nav-link <?= $currentPath === $href ? 'active' : '' ?>"><span><?= $icon ?></span><span><?= e($label) ?></span></a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="admin-sidebar-footer">
            <a href="/moderator" class="admin-nav-link" style="background:var(--g-bg);">🛡️ Панель модератора</a>
            <a href="/" class="admin-nav-link" style="background:var(--g-bg);">🌐 На сайт</a>
            <form method="POST" action="/admin/logout">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary" style="width:100%;">Выйти</button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
        <div class="admin-topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="admin-burger btn btn-secondary btn-sm" onclick="document.getElementById('adminSidebar').classList.toggle('open')">☰</button>
                <div>
                    <div style="font-size:12px;color:var(--g-text-secondary);">
                        <a href="/admin" style="color:var(--g-text-secondary);">Админка</a>
                        <?php if ($currentPath !== '/admin'): ?> / <?= e($currentLabel) ?><?php endif; ?>
                    </div>
                    <h1 style="font-size:18px;font-weight:700;margin:2px 0 0;"><?= e($currentLabel) ?></h1>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <?php if ($currentPath !== '/admin'): ?>
                    <a href="/admin" class="btn btn-secondary btn-sm">← В главное меню</a>
                <?php endif; ?>
                <a href="javascript:history.back()" class="btn btn-secondary btn-sm">← Назад</a>
            </div>
        </div>
        <div class="admin-content">