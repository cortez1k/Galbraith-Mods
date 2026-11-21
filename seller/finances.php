<?php
$user = currentUser();
$balance = getUserBalance($user['id']);
$stmt = getDB()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();
$stmt = getDB()->prepare('SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$withdrawals = $stmt->fetchAll();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$pageTitle = 'Финансы';
include __DIR__ . '/../templates/header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">Финансы</h1>

<?php if ($flash): ?><div style="background:rgba(232,28,90,0.1);border:1px solid rgba(232,28,90,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<div class="card" style="padding:24px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
    <div>
        <div style="font-size:13px;color:var(--g-text-secondary);">Доступный баланс</div>
        <div style="font-size:32px;font-weight:700;color:var(--g-accent-light);"><?= number_format($balance, 0, ',', ' ') ?> ₽</div>
    </div>
    <?php if (!sellerHasPayoutDetails($user)): ?>
        <span style="color:var(--g-text-secondary);font-size:14px;">Заполните <a href="/seller/settings" style="color:var(--g-accent-light);">реквизиты</a> для вывода</span>
    <?php else: ?>
    <form method="POST" action="/seller/withdraw" style="display:flex;gap:8px;">
        <input type="number" step="1" min="1" max="<?= floor($balance) ?>" name="amount" class="input" placeholder="Сумма" required>
        <button type="submit" class="btn btn-primary">Вывести</button>
    </form>
    <?php endif; ?>
</div>

<h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">Заявки на вывод</h3>
<?php if (!$withdrawals): ?><p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:24px;">Заявок пока нет</p><?php else: ?>
<div style="overflow-x:auto;margin-bottom:24px;">
<table class="admin-table">
    <thead><tr><th>Дата</th><th>Сумма</th><th>Статус</th><th>Комментарий</th></tr></thead>
    <tbody><?php foreach ($withdrawals as $w): ?>
    <tr><td><?= date('d.m.Y H:i', strtotime($w['created_at'])) ?></td><td><?= number_format($w['amount'], 0, ',', ' ') ?> ₽</td>
        <td><?= ['pending' => '⏳ Ожидает', 'completed' => '✅ Выполнено', 'failed' => '❌ Отклонено'][$w['status']] ?? $w['status'] ?></td>
        <td><?= e($w['admin_comment'] ?? '—') ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
</div>
<?php endif; ?>

<h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">История транзакций</h3>
<?php if (!$transactions): ?><p style="color:var(--g-text-secondary);font-size:14px;">Транзакций пока нет</p><?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Дата</th><th>Тип</th><th>Сумма</th><th>Описание</th></tr></thead>
    <tbody><?php foreach ($transactions as $t): ?>
    <tr><td><?= date('d.m.Y H:i', strtotime($t['created_at'])) ?></td>
        <td><?= ['credit' => 'Начисление', 'debit' => 'Списание', 'withdrawal' => 'Вывод'][$t['type']] ?? $t['type'] ?></td>
        <td style="color:<?= $t['amount'] >= 0 ? '#34C759' : '#FF3B30' ?>;"><?= ($t['amount'] >= 0 ? '+' : '') . number_format($t['amount'], 0, ',', ' ') ?> ₽</td>
        <td><?= e($t['description']) ?></td></tr>
    <?php endforeach; ?></tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
