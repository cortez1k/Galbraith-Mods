<?php
$search = $_GET['search'] ?? '';
$sql = "SELECT u.id, u.name, u.email, COALESCE(b.balance, 0) as balance FROM users u LEFT JOIN bonuses b ON b.user_id = u.id WHERE u.role IN ('user','premium','trader')";
$params = [];
if ($search) { $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%'; }
$sql .= ' ORDER BY balance DESC';
$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$pageTitle = 'Бонусы пользователей';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Бонусы пользователей</h1>

<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;">
    <input type="text" name="search" value="<?= e($search) ?>" class="input" placeholder="Поиск..." style="flex:1;">
    <button type="submit" class="btn btn-secondary">Найти</button>
</form>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Пользователь</th><th>Баланс бонусов</th><th>Изменить</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['name']) ?> (<?= e($u['email']) ?>)</td>
            <td><?= (int)$u['balance'] ?></td>
            <td>
                <form method="POST" action="/admin/bonuses/<?= $u['id'] ?>/adjust" style="display:flex;gap:6px;">
                    <input type="number" name="amount" class="input" placeholder="+/-" style="width:90px;" required>
                    <input type="text" name="reason" class="input" placeholder="Причина" style="width:180px;" required>
                    <button type="submit" class="btn btn-secondary btn-sm">Применить</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
