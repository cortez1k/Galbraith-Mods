<?php
$db = getDB();
$status = $_GET['status'] ?? '';
$sql = 'SELECT * FROM tickets';
if ($status) { $sql .= ' WHERE status = ?'; $stmt = $db->prepare($sql); $stmt->execute([$status]); }
else { $stmt = $db->query($sql . ' ORDER BY created_at DESC'); }
$tickets = $stmt->fetchAll();
$pageTitle = 'Тикеты';
include __DIR__ . '/../templates/moderator_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Тикеты</h1>

<form method="GET" style="margin-bottom:20px;">
    <select name="status" class="select" style="width:auto;" onchange="this.form.submit()">
        <option value="">Все статусы</option>
        <option value="new" <?= $status=='new'?'selected':'' ?>>Новые</option>
        <option value="in_progress" <?= $status=='in_progress'?'selected':'' ?>>В работе</option>
        <option value="closed" <?= $status=='closed'?'selected':'' ?>>Закрытые</option>
    </select>
</form>

<div style="overflow-x:auto;">
    <table class="admin-table">
        <thead><tr><th>ID</th><th>Тема</th><th>Контакт</th><th>Статус</th><th>Дата</th></tr></thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td>#<?= $t['id'] ?></td>
                <td><a href="/moderator/tickets/<?= $t['id'] ?>" style="color:var(--g-accent-light);"><?= e($t['subject']) ?></a></td>
                <td><?= e($t['contact']) ?></td>
                <td>
                    <?php if($t['status']=='new'): ?><span class="status status-new">Новый</span>
                    <?php elseif($t['status']=='in_progress'): ?><span class="status status-progress">В работе</span>
                    <?php else: ?><span class="status status-closed">Закрыт</span><?php endif; ?>
                </td>
                <td><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../templates/moderator_footer.php'; ?>
