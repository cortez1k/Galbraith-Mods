<?php
$stmt = getDB()->query("SELECT l.*, u.name as user_name FROM admin_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 300");
$logs = $stmt->fetchAll();
$pageTitle = 'Логи действий';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Логи действий администраторов и модераторов</h1>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Дата</th><th>Пользователь</th><th>Действие</th><th>Детали</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
        <tr>
            <td><?= date('d.m.Y H:i:s', strtotime($l['created_at'])) ?></td>
            <td><?= e($l['user_name'] ?? ('ID ' . $l['user_id'])) ?></td>
            <td><?= e($l['action']) ?></td>
            <td><?= e($l['details']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
