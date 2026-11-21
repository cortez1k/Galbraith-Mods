<?php
$order = getOrderById((int)($_GET['id'] ?? 0));
if (!$order) redirect('/moderator/chats');
$messages = getOrderMessages($order['id']);
$pageTitle = 'Чат заказа #' . $order['id'];
include __DIR__ . '/../templates/moderator_header.php';
?>

<h1 style="font-size:20px;font-weight:700;margin-bottom:4px;"><?= e($order['product_title']) ?></h1>
<div style="font-size:13px;color:var(--g-text-secondary);margin-bottom:20px;">
    Заказ #<?= $order['id'] ?> · Покупатель: <?= e($order['buyer_name']) ?> · Продавец: <?= e($order['seller_name']) ?> · Статус: <?= e(orderStatusLabel($order['status'])) ?>
</div>

<div class="card" style="padding:16px;max-height:520px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;">
    <?php foreach ($messages as $msg): ?>
        <?php if ($msg['is_system']): ?>
            <div style="align-self:center;background:rgba(232,28,90,0.1);color:var(--g-accent-light);font-size:13px;padding:8px 14px;border-radius:20px;max-width:90%;text-align:center;">ℹ️ <?= e($msg['message']) ?></div>
        <?php else: ?>
            <?php $isBuyer = (int)$msg['sender_id'] === (int)$order['buyer_id']; ?>
            <div style="align-self:<?= $isBuyer ? 'flex-start' : 'flex-end' ?>;max-width:75%;background:var(--g-card);border:1px solid var(--g-border);border-radius:12px;padding:10px 14px;">
                <div style="font-size:11px;color:var(--g-text-secondary);margin-bottom:4px;"><?= e($msg['sender_name'] ?? 'Пользователь') ?> (<?= $isBuyer ? 'покупатель' : 'продавец' ?>)</div>
                <div style="font-size:14px;white-space:pre-wrap;"><?= e($msg['message']) ?></div>
                <?php if ($msg['file_path']): ?><a href="<?= e(UPLOAD_URL . $msg['file_path']) ?>" target="_blank" style="font-size:12px;display:block;margin-top:6px;color:var(--g-accent-light);">📎 Вложение</a><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$messages): ?><p style="color:var(--g-text-secondary);font-size:14px;">Сообщений пока нет</p><?php endif; ?>
</div>
<p style="font-size:12px;color:var(--g-text-secondary);margin-top:10px;">Модератор может только просматривать переписку. Для вмешательства используйте /admin/orders (доступно администратору).</p>

<?php include __DIR__ . '/../templates/moderator_footer.php'; ?>
