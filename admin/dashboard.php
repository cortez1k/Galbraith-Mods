<?php
$db = getDB();
$stats = [
    'materials' => $db->query('SELECT COUNT(*) FROM materials')->fetchColumn(),
    'downloads' => $db->query('SELECT COALESCE(SUM(downloads_count),0) FROM materials')->fetchColumn(),
    'comments' => $db->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
    'tickets_new' => $db->query('SELECT COUNT(*) FROM tickets WHERE status = "new"')->fetchColumn(),
];
$pageTitle = 'Админ-панель';
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <h1 style="font-size:22px;font-weight:600;">Админ-панель</h1>
    <a href="/admin/totp" class="btn btn-secondary">🔐 2FA защита</a>
    <a href="/admin/password" class="btn btn-secondary">🔑 Сменить пароль</a>
    <form method="POST" action="/admin/logout" style="display:inline;">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn btn-secondary">Выйти</button>
    </form>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:32px;">
    <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;text-align:center;">
        <div style="font-size:32px;font-weight:600;color:var(--g-accent-light);"><?= $stats['materials'] ?></div>
        <div style="font-size:13px;color:var(--g-text-secondary);margin-top:4px;">Материалов</div>
    </div>
    <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;text-align:center;">
        <div style="font-size:32px;font-weight:600;color:var(--g-accent-light);"><?= number_format($stats['downloads'], 0, ',', ' ') ?></div>
        <div style="font-size:13px;color:var(--g-text-secondary);margin-top:4px;">Скачиваний</div>
    </div>
    <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;text-align:center;">
        <div style="font-size:32px;font-weight:600;color:var(--g-accent-light);"><?= $stats['comments'] ?></div>
        <div style="font-size:13px;color:var(--g-text-secondary);margin-top:4px;">Комментариев</div>
    </div>
    <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;text-align:center;">
        <div style="font-size:32px;font-weight:600;color:var(--g-accent-light);"><?= $stats['tickets_new'] ?></div>
        <div style="font-size:13px;color:var(--g-text-secondary);margin-top:4px;">Новых тикетов</div>
    </div>
</div>

<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:32px;">
    <a href="/admin/materials/create" class="btn btn-primary">+ Добавить материал</a>
    <a href="/admin/materials" class="btn btn-secondary">Материалы</a>
    <a href="/admin/categories" class="btn btn-secondary">Категории</a>
    <a href="/admin/tasks" class="btn btn-secondary">Задания</a>
    <a href="/admin/pages" class="btn btn-secondary">Страницы</a>
    <a href="/admin/comments" class="btn btn-secondary">Комментарии</a>
    <a href="/admin/tickets" class="btn btn-secondary">Тикеты</a>
    <a href="/admin/news" class="btn btn-secondary">Новости</a>
    <a href="/admin/settings" class="btn btn-secondary">Настройки</a>
    <a href="/admin/vk-broadcast" class="btn btn-secondary">📢 Рассылка в VK</a>
</div>

<h2 style="font-size:18px;font-weight:600;margin-bottom:16px;">Торговая площадка и гарант</h2>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:32px;">
    <a href="/admin/users" class="btn btn-secondary">Пользователи и роли</a>
    <a href="/admin/orders" class="btn btn-secondary">Заказы</a>
    <a href="/admin/withdrawals" class="btn btn-secondary">Заявки на вывод</a>
    <a href="/admin/reviews" class="btn btn-secondary">Отзывы</a>
    <a href="/admin/bonuses" class="btn btn-secondary">Бонусы пользователей</a>
    <a href="/admin/logs" class="btn btn-secondary">Логи действий</a>
    <a href="/moderator" class="btn btn-secondary">Панель модератора</a>
    <a href="/admin/traders" class="btn btn-secondary">🎁 Торговцы: бонусы и дисциплина</a>
    <a href="/admin/bonus-tasks" class="btn btn-secondary">Задания для бонусов</a>
    <a href="/admin/referrals" class="btn btn-secondary">Реферальная программа</a>
    <a href="/admin/marketplace" class="btn btn-secondary">Управление маркетплейсом</a>
</div>

<h2 style="font-size:18px;font-weight:600;margin-bottom:16px;">Быстрые ссылки</h2>
<div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;">
    <p style="color:var(--g-text-secondary);font-size:14px;">Добро пожаловать в панель управления. Здесь вы можете управлять всем контентом сайта.</p>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
