<?php
$db = getDB();
$banned = getBannedUsers();
$search = $_GET['search'] ?? '';
$users = [];
if ($search) {
    $stmt = $db->prepare("SELECT * FROM users WHERE (name LIKE ? OR email LIKE ?) AND role NOT IN ('admin','moderator') LIMIT 20");
    $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
    $users = $stmt->fetchAll();
}
$pageTitle = 'Бан-лист';
include __DIR__ . '/../templates/moderator_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Бан-лист</h1>

<form method="GET" style="margin-bottom:24px;display:flex;gap:8px;">
    <input type="text" name="search" value="<?= e($search) ?>" class="input" placeholder="Поиск пользователя по имени/email..." style="flex:1;">
    <button type="submit" class="btn btn-secondary">Найти</button>
</form>

<?php if ($users): ?>
<div style="margin-bottom:28px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:10px;">Результаты поиска</h3>
    <?php foreach ($users as $u): ?>
    <div class="card" style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
        <span><?= e($u['name']) ?> (<?= e($u['email']) ?>)</span>
        <?php if (!empty($u['banned_until']) && strtotime($u['banned_until']) > time()): ?>
            <form method="POST" action="/moderator/banlist/<?= $u['id'] ?>/unban"><button type="submit" class="btn btn-secondary btn-sm">Разбанить</button></form>
        <?php else: ?>
            <form method="POST" action="/moderator/banlist/<?= $u['id'] ?>/ban" style="display:flex;gap:6px;">
                <input type="number" name="days" placeholder="Дней (0=навсегда)" class="input" style="width:160px;" required>
                <input type="text" name="reason" placeholder="Причина" class="input" style="width:200px;" required>
                <button type="submit" class="btn btn-danger btn-sm">Забанить</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h3 style="font-size:15px;font-weight:600;margin-bottom:10px;">Забаненные пользователи</h3>
<?php if (!$banned): ?>
    <p style="color:var(--g-text-secondary);font-size:14px;">Список пуст</p>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Пользователь</th><th>До</th><th>Причина</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($banned as $u): ?>
        <tr>
            <td><?= e($u['name']) ?> (<?= e($u['email']) ?>)</td>
            <td><?= (strtotime($u['banned_until']) > time() + 50 * 365 * 86400) ? 'Навсегда' : date('d.m.Y', strtotime($u['banned_until'])) ?></td>
            <td><?= e($u['ban_reason']) ?></td>
            <td><form method="POST" action="/moderator/banlist/<?= $u['id'] ?>/unban"><button type="submit" class="btn btn-secondary btn-sm">Разбанить</button></form></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/moderator_footer.php'; ?>
