<?php
$db = getDB();
$roleFilter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';
$sql = 'SELECT u.*, (SELECT balance FROM user_balances b WHERE b.user_id = u.id) as balance FROM users u WHERE 1=1';
$params = [];
if ($roleFilter) { $sql .= ' AND u.role = ?'; $params[] = $roleFilter; }
if ($search) { $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%'; }
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle = 'Пользователи';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Пользователи</h1>

<?php if ($flash): ?><div style="background:rgba(255,80,80,0.1);border:1px solid rgba(255,80,80,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <input type="text" name="search" value="<?= e($search) ?>" class="input" placeholder="Поиск..." style="flex:1;min-width:200px;">
    <select name="role" class="select" onchange="this.form.submit()" style="width:auto;">
        <option value="">Все роли</option>
        <?php foreach (ROLES as $r): ?><option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= e(roleLabel($r)) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Найти</button>
</form>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>ID</th><th>Имя</th><th>Email</th><th>Роль</th><th>Баланс</th><th>Дата регистрации</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span style="display:inline-block;padding:3px 9px;border-radius:14px;background:<?= e(roleColor($u['role'])) ?>;color:#fff;font-size:12px;font-weight:600;"><?= e(roleLabel($u['role'])) ?></span></td>
            <td><?= $u['balance'] !== null ? number_format($u['balance'], 0, ',', ' ') . ' ₽' : '—' ?></td>
            <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
            <td style="display:flex;gap:6px"><a href="/admin/users/<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Управлять</a><?php if((int)$u['id']!==(int)($_SESSION['admin_id']??0)): ?><form method="POST" action="/admin/users/<?= $u['id'] ?>/delete" onsubmit="return confirm('Удалить аккаунт навсегда?')"><button class="btn btn-danger btn-sm">Удалить</button></form><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
