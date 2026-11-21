<?php
$db = getDB();
$stats = [
    'tickets_new' => $db->query("SELECT COUNT(*) FROM tickets WHERE status = 'new'")->fetchColumn(),
    'disputes' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'disputed'")->fetchColumn(),
    'banned' => $db->query("SELECT COUNT(*) FROM users WHERE banned_until IS NOT NULL AND banned_until > datetime('now')")->fetchColumn(),
    'comments' => $db->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
];
$pageTitle = 'Панель модератора';
include __DIR__ . '/../templates/moderator_header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Панель модератора</h1>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:700;color:var(--g-accent-light);"><?= $stats['tickets_new'] ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Новых тикетов</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:700;color:var(--g-accent-light);"><?= $stats['disputes'] ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Открытых споров</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:700;color:var(--g-accent-light);"><?= $stats['banned'] ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Забанено</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:700;color:var(--g-accent-light);"><?= $stats['comments'] ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Комментариев</div></div>
</div>

<div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/moderator/tickets" class="btn btn-secondary">Тикеты</a>
    <a href="/moderator/comments" class="btn btn-secondary">Комментарии</a>
    <a href="/moderator/banlist" class="btn btn-secondary">Бан-лист</a>
    <a href="/moderator/disputes" class="btn btn-secondary">Споры</a>
    <a href="/moderator/chats" class="btn btn-secondary">Чаты</a>
</div>

<?php include __DIR__ . '/../templates/moderator_footer.php'; ?>
