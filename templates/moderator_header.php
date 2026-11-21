<?php
require_once __DIR__ . '/../includes/functions.php';
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$navLinks = [
    ['/moderator', '📊', 'Дашборд'],
    ['/moderator/tickets', '🎫', 'Тикеты'],
    ['/moderator/comments', '💬', 'Комментарии'],
    ['/moderator/banlist', '🚫', 'Бан-лист'],
    ['/moderator/disputes', '⚖️', 'Споры'],
    ['/moderator/chats', '💭', 'Чаты (просмотр)'],
];
$currentLabel = $pageTitle ?? 'Панель модератора';
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($currentLabel) ?> — Модерация Galbraith</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { margin: 0; }
        .admin-shell { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 240px; flex-shrink: 0; background: var(--g-card); border-right: 1px solid var(--g-border); display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .admin-sidebar-header { padding: 18px 20px; border-bottom: 1px solid var(--g-border); display: flex; align-items: center; gap: 10px; }
        .admin-sidebar-header .logo-icon { width: 32px; height: 32px; font-size: 14px; }
        .admin-nav-group { padding: 14px 12px 4px; }
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
                <div style="font-size:11px;color:var(--g-text-secondary);">Модерация</div>
            </div>
        </div>
        <div style="flex:1;overflow-y:auto;">
            <div class="admin-nav-group">
                <?php foreach ($navLinks as [$href, $icon, $label]): ?>
                    <a href="<?= $href ?>" class="admin-nav-link <?= $currentPath === $href ? 'active' : '' ?>"><span><?= $icon ?></span><span><?= e($label) ?></span></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="admin-sidebar-footer">
            <?php if (hasRole('admin')): ?><a href="/admin" class="admin-nav-link" style="background:var(--g-bg);">⚙️ Админ-панель</a><?php endif; ?>
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
                        <a href="/moderator" style="color:var(--g-text-secondary);">Модерация</a>
                        <?php if ($currentPath !== '/moderator'): ?> / <?= e($currentLabel) ?><?php endif; ?>
                    </div>
                    <h1 style="font-size:18px;font-weight:700;margin:2px 0 0;"><?= e($currentLabel) ?></h1>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <?php if ($currentPath !== '/moderator'): ?>
                    <a href="/moderator" class="btn btn-secondary btn-sm">← В главное меню</a>
                <?php endif; ?>
                <a href="javascript:history.back()" class="btn btn-secondary btn-sm">← Назад</a>
            </div>
        </div>
        <div class="admin-content">
