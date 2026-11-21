<?php
$order = getOrderById((int)($_GET['id'] ?? 0));
if (!$order) redirect('/admin/orders');
autoConfirmIfDue($order);
$order = getOrderById($order['id']);
$messages = getOrderMessages($order['id']);
$pageTitle = 'Заказ #' . $order['id'];
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <div>
        <h1 style="font-size:20px;font-weight:700;"><?= e($order['product_title']) ?></h1>
        <div style="font-size:13px;color:var(--g-text-secondary);">Заказ #<?= $order['id'] ?> · Покупатель: <?= e($order['buyer_name']) ?> (<?= e($order['buyer_email']) ?>) · Продавец: <?= e($order['seller_name']) ?> (<?= e($order['seller_email']) ?>)</div>
    </div>
    <span class="status status-<?= $order['status'] === 'completed' ? 'closed' : ($order['status'] === 'awaiting_confirmation' ? 'new' : 'progress') ?>" style="font-size:13px;padding:6px 12px;"><?= e(orderStatusLabel($order['status'])) ?></span>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:start;" class="order-grid">
<style>@media(max-width:800px){.order-grid{grid-template-columns:1fr !important;}}</style>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="max-height:480px;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($messages as $msg): ?>
            <?php if ($msg['is_system']): ?>
                <div style="align-self:center;background:rgba(232,28,90,0.1);color:var(--g-accent-light);font-size:13px;padding:8px 14px;border-radius:20px;max-width:90%;text-align:center;">ℹ️ <?= e($msg['message']) ?></div>
            <?php else: ?>
                <?php $isBuyer = (int)$msg['sender_id'] === (int)$order['buyer_id']; ?>
                <div class="<?= !empty($msg['is_admin']) ? 'admin-message' : '' ?>" style="align-self:<?= $isBuyer ? 'flex-start' : 'flex-end' ?>;max-width:75%;background:<?= !empty($msg['is_admin']) ? 'rgba(255,59,48,.12)' : 'var(--g-card)' ?>;border:1px solid <?= !empty($msg['is_admin']) ? 'rgba(255,59,48,.35)' : 'var(--g-border)' ?>;border-radius:12px;padding:10px 14px;">
                    <div style="font-size:11px;color:var(--g-text-secondary);margin-bottom:4px;"><strong><?= e($msg['sender_name'] ?? 'Пользователь') ?></strong> <?php if(!empty($msg['is_admin'])):?><span class="admin-tag">АДМИНИСТРАТОР</span><?php else:?>(<?= $isBuyer ? 'покупатель' : 'продавец' ?>)<?php endif;?></div>
                    <div style="font-size:14px;white-space:pre-wrap;"><?= e($msg['message']) ?></div>
                    <?php if ($msg['file_path']): ?><a href="<?= e(UPLOAD_URL . $msg['file_path']) ?>" target="_blank" style="font-size:12px;display:block;margin-top:6px;color:var(--g-accent-light);">📎 Вложение</a><?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <form method="POST" action="/admin/orders/<?= $order['id'] ?>/message" style="border-top:1px solid var(--g-border);padding:12px;display:flex;gap:8px;">
        <input type="text" name="message" class="input" placeholder="Сообщение от имени администратора..." style="flex:1;" required>
        <button type="submit" class="btn btn-primary btn-sm">Отправить</button>
    </form>
</div>

<div style="display:flex;flex-direction:column;gap:14px;">
    <div class="card" style="padding:16px;font-size:14px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--g-text-secondary);">Цена товара</span><span><?= number_format($order['amount'], 0, ',', ' ') ?> ₽</span></div>
        <?php if ($order['discount_amount'] > 0): ?><div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="color:var(--g-text-secondary);">Скидка</span><span>−<?= number_format($order['discount_amount'], 0, ',', ' ') ?> ₽</span></div><?php endif; ?>
        <div style="display:flex;justify-content:space-between;font-weight:700;margin-bottom:6px;"><span>К оплате</span><span><?= number_format($order['final_amount'], 0, ',', ' ') ?> ₽</span></div>
        <div style="display:flex;justify-content:space-between;color:var(--g-text-secondary);font-size:12px;"><span>Комиссия (<?= $order['commission_percent'] ?>%)</span><span><?= number_format($order['commission_amount'], 0, ',', ' ') ?> ₽</span></div>
        <div style="display:flex;justify-content:space-between;color:var(--g-text-secondary);font-size:12px;"><span>К выплате продавцу</span><span><?= number_format($order['net_amount'], 0, ',', ' ') ?> ₽</span></div>
    </div>

    <div class="card" style="padding:16px;">
        <h4 style="font-size:14px;font-weight:600;margin-bottom:10px;">Управление статусом</h4>
        <form method="POST" action="/admin/orders/<?= $order['id'] ?>/status" style="display:flex;flex-direction:column;gap:8px;">
            <?php if ($order['status'] === 'awaiting_confirmation'): ?>
                <button type="submit" name="status_action" value="confirm_payment" class="btn btn-primary btn-sm" onclick="return confirm('Подтвердить получение оплаты?')">✅ Подтвердить оплату</button>
                <button type="submit" name="status_action" value="cancel" class="btn btn-secondary btn-sm">❌ Отменить заказ</button>
            <?php elseif ($order['status'] === 'paid'): ?>
                <button type="submit" name="status_action" value="in_progress" class="btn btn-secondary btn-sm">🔄 Отметить «в процессе»</button>
                <button type="submit" name="status_action" value="complete" class="btn btn-primary btn-sm" onclick="return confirm('Завершить заказ вручную и выплатить продавцу?')">✅ Завершить заказ</button>
                <button type="submit" name="status_action" value="refund" class="btn btn-danger btn-sm" onclick="return confirm('Оформить возврат покупателю?')">💸 Возврат</button>
            <?php elseif ($order['status'] === 'in_progress'): ?>
                <button type="submit" name="status_action" value="complete" class="btn btn-primary btn-sm" onclick="return confirm('Завершить заказ вручную и выплатить продавцу?')">✅ Завершить заказ</button>
                <button type="submit" name="status_action" value="refund" class="btn btn-danger btn-sm" onclick="return confirm('Оформить возврат покупателю?')">💸 Возврат</button>
            <?php elseif ($order['status'] === 'disputed'): ?>
                <a href="/moderator/disputes" class="btn btn-secondary btn-sm">Перейти в раздел «Споры»</a>
            <?php else: ?>
                <p style="color:var(--g-text-secondary);font-size:13px;">Заказ в финальном статусе, действия недоступны.</p>
            <?php endif; ?>
        </form>
        <?php if ($order['auto_confirm_at']): ?>
            <p style="font-size:12px;color:var(--g-text-secondary);margin-top:10px;">Автоподтверждение: <?= date('d.m.Y H:i', strtotime($order['auto_confirm_at'])) ?></p>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
