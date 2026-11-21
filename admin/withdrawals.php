<?php
$stmt = getDB()->query("SELECT w.*, u.name as user_name, u.email as user_email, u.full_name as recipient_name FROM withdrawal_requests w LEFT JOIN users u ON w.user_id = u.id ORDER BY w.created_at DESC");
$requests = $stmt->fetchAll();
$pageTitle = 'Заявки на вывод';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Заявки на вывод средств</h1>

<?php if (!$requests): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary);">Заявок пока нет</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>#</th><th>Пользователь</th><th>Сумма</th><th>Комиссия продавца</th><th>Выручка (справочно)</th><th>Карта</th><th>ФИО получателя</th><th>Статус</th><th>Дата</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($requests as $r): ?>
        <tr>
            <td>#<?= $r['id'] ?></td>
            <td><?= e($r['user_name']) ?> (<?= e($r['user_email']) ?>)</td>
            <td><?= number_format($r['amount'], 0, ',', ' ') ?> ₽<?= $r['fee_amount'] > 0 ? ' <span style="color:var(--g-text-secondary);font-size:12px;">(комиссия вывода ' . $r['fee_percent'] . '%: −' . number_format($r['fee_amount'],0,',',' ') . ' ₽)</span>' : '' ?></td>
            <td><?= $r['commission_percent_snapshot'] !== null ? $r['commission_percent_snapshot'] . '%' : '—' ?></td>
            <td style="font-size:12px;color:var(--g-text-secondary);">
                <?php if ($r['gross_sales_snapshot'] !== null): ?>
                    Оплачено покупателями: <?= number_format($r['gross_sales_snapshot'], 0, ',', ' ') ?> ₽<br>
                    Удержано комиссии: <?= number_format($r['commission_amount_snapshot'], 0, ',', ' ') ?> ₽
                <?php else: ?>—<?php endif; ?>
            </td>
            <td style="font-family:var(--g-font-mono);white-space:nowrap;"><?= $r['card_number'] ? e(decryptSecret($r['card_number'])) : '—' ?></td>
            <td><?= $r['recipient_name'] ? e($r['recipient_name']) : '<span style="color:#FF3B30;">не указано</span>' ?></td>
            <td><?= ['pending' => '⏳ Ожидает', 'completed' => '✅ Выполнено', 'failed' => '❌ Отклонено'][$r['status']] ?? $r['status'] ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
            <td>
                <?php if ($r['status'] === 'pending'): ?>
                <form method="POST" action="/admin/withdrawals/<?= $r['id'] ?>/process" style="display:flex;gap:6px;">
                    <input type="text" name="comment" class="input" placeholder="Комментарий" style="width:140px;">
                    <button type="submit" name="decision" value="approve" class="btn btn-primary btn-sm" onclick="return confirm('Подтвердить, что перевод выполнен вручную?')">Выполнено</button>
                    <button type="submit" name="decision" value="reject" class="btn btn-danger btn-sm">Отклонить</button>
                </form>
                <?php else: ?>
                    <?= e($r['admin_comment'] ?? '') ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
