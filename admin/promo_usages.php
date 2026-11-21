<?php
$promoId = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare('SELECT * FROM promo_codes WHERE id = ?');
$stmt->execute([$promoId]);
$promo = $stmt->fetch();
if (!$promo) redirect('/admin/promos');

$stmt = $db->prepare('SELECT pu.*, u.name as user_name, u.email as user_email, o.final_amount
    FROM promo_code_usages pu
    LEFT JOIN users u ON pu.user_id = u.id
    LEFT JOIN orders o ON pu.order_id = o.id
    WHERE pu.promo_id = ? ORDER BY pu.created_at DESC');
$stmt->execute([$promoId]);
$usages = $stmt->fetchAll();
$totalSaved = array_sum(array_column($usages, 'discount_amount'));

$pageTitle = 'Использования промокода ' . $promo['code'];
include __DIR__ . '/../templates/admin_header.php';
?>
<a href="/admin/promos" class="btn btn-secondary" style="margin-bottom:16px;">← Промокоды</a>
<h1 style="font-size:22px;font-weight:700;margin-bottom:6px;">🏷️ <?= e($promo['code']) ?></h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;">
    Скидка: <?= e($promo['discount_value']) ?><?= $promo['discount_type']==='percent'?'%':' ₽' ?>
    · Использовано: <?= count($usages) ?><?= $promo['max_uses'] ? ' из ' . $promo['max_uses'] : '' ?>
    · <strong style="color:var(--g-accent-light);">Всего сэкономлено покупателями: <?= number_format($totalSaved,2,',',' ') ?> ₽</strong>
</p>

<?php if (!$usages): ?>
    <p style="color:var(--g-text-secondary);">Этот промокод ещё никто не использовал.</p>
<?php else: ?>
<div style="overflow:auto;">
<table class="admin-table">
    <thead><tr><th>Когда</th><th>Пользователь</th><th>Заказ</th><th>Сумма заказа</th><th>Сэкономлено</th></tr></thead>
    <tbody>
    <?php foreach ($usages as $u): ?>
        <tr>
            <td><?= e($u['created_at']) ?></td>
            <td><?= e($u['user_name'] ?: '—') ?><?php if($u['user_email']):?><br><span style="font-size:12px;color:var(--g-text-secondary);"><?=e($u['user_email'])?></span><?php endif;?></td>
            <td><a href="/admin/orders/<?= $u['order_id'] ?>" style="color:var(--g-accent-light);">#<?= $u['order_id'] ?></a></td>
            <td><?= $u['final_amount']!==null ? number_format($u['final_amount'],2,',',' ').' ₽' : '—' ?></td>
            <td style="color:#34C759;font-weight:600;"><?= number_format($u['discount_amount'],2,',',' ') ?> ₽</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
