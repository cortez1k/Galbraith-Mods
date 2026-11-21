<?php
$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) redirect('/moderator/tickets');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
    if (!empty(trim($_POST['reply'] ?? '')) && filter_var($ticket['contact'], FILTER_VALIDATE_EMAIL)) {
        @mail($ticket['contact'], 'Ответ по тикету #' . $id . ' — Galbraith', $_POST['reply']);
    }
    logAdminAction('ticket_update', 'Ticket #' . $id . ' -> ' . $_POST['status']);
    redirect('/moderator/tickets/' . $id);
}

$pageTitle = 'Тикет #' . $ticket['id'];
include __DIR__ . '/../templates/moderator_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:8px;"><?= e($ticket['subject']) ?></h1>
<div style="color:var(--g-text-secondary);font-size:13px;margin-bottom:20px;">
    #<?= $ticket['id'] ?> • <?= e($ticket['contact']) ?> • <?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?>
</div>

<div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;margin-bottom:20px;">
    <p style="font-size:14px;line-height:1.7;white-space:pre-wrap;"><?= e($ticket['message']) ?></p>
</div>

<form method="POST">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;">
        <select name="status" class="select" style="width:auto;">
            <option value="new" <?= $ticket['status']=='new'?'selected':'' ?>>Новый</option>
            <option value="in_progress" <?= $ticket['status']=='in_progress'?'selected':'' ?>>В работе</option>
            <option value="closed" <?= $ticket['status']=='closed'?'selected':'' ?>>Закрыт</option>
        </select>
    </div>
    <textarea name="reply" class="input" rows="4" placeholder="Ответ пользователю (отправится на email, если контакт — email)" style="margin-bottom:12px;"></textarea>
    <button type="submit" class="btn btn-primary">Сохранить</button>
</form>

<?php include __DIR__ . '/../templates/moderator_footer.php'; ?>
