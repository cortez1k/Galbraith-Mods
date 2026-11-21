<?php
$search = $_GET['search'] ?? '';
$sql = "SELECT u.id, u.name, u.email, u.trader_frozen,
        (SELECT balance FROM user_balances b WHERE b.user_id = u.id) as balance,
        (SELECT balance FROM trader_bonuses tb WHERE tb.user_id = u.id) as bonus_balance,
        (SELECT COUNT(*) FROM trader_penalties p WHERE p.user_id = u.id AND p.is_active = 1 AND p.expires_at > datetime('now')) as active_penalties
        FROM users u WHERE u.role = 'trader'";
$params = [];
if ($search) { $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%'; }
$sql .= ' ORDER BY active_penalties DESC, balance DESC';
$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$traders = $stmt->fetchAll();
$pageTitle = 'Торговцы: балансы и дисциплина';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Торговцы</h1>

<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;">
    <input type="text" name="search" value="<?= e($search) ?>" class="input" placeholder="Поиск..." style="flex:1;">
    <button type="submit" class="btn btn-secondary">Найти</button>
</form>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Имя</th><th>Финансовый баланс</th><th>Баланс бонусов</th><th>Взыскания</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($traders as $t): ?>
        <tr style="<?= $t['trader_frozen'] ? 'opacity:0.5;' : '' ?>">
            <td><?= e($t['name']) ?> (<?= e($t['email']) ?>)<?= $t['trader_frozen'] ? ' <span style="color:#FF3B30;font-size:12px;">(удалён/заморожен)</span>' : '' ?></td>
            <td><?= number_format($t['balance'] ?? 0, 0, ',', ' ') ?> ₽</td>
            <td><?= rtrim(rtrim(number_format($t['bonus_balance'] ?? 0, 2, '.', ' '), '0'), '.') ?></td>
            <td><?= $t['active_penalties'] > 0 ? '<span style="color:#FF3B30;font-weight:600;">' . $t['active_penalties'] . '</span>' : '0' ?></td>
            <td><a href="/admin/traders/<?= $t['id'] ?>" class="btn btn-secondary btn-sm">Открыть</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
