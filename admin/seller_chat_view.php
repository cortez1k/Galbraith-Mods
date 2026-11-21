<?php
$sellerId = (int)($_GET['seller_id'] ?? 0);
$buyerId = (int)($_GET['buyer_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);
if (!$sellerId || !$buyerId || !$productId) redirect('/admin/seller-chats');

$db = getDB();
$stmt = $db->prepare('SELECT name FROM users WHERE id = ?'); $stmt->execute([$sellerId]); $sellerName = $stmt->fetchColumn() ?: 'Продавец';
$stmt->execute([$buyerId]); $buyerName = $stmt->fetchColumn() ?: 'Покупатель';
$stmt = $db->prepare('SELECT title, slug FROM trader_products WHERE id = ?'); $stmt->execute([$productId]); $product = $stmt->fetch();

$messages = getSellerChatMessages($productId, $buyerId, $sellerId);

$pageTitle = 'Чат: ' . $sellerName . ' ↔ ' . $buyerName;
include __DIR__ . '/../templates/admin_header.php';
?>

<a href="/admin/seller-chats" class="btn btn-secondary" style="margin-bottom:16px;">← Все чаты</a>
<h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">
    🏪 <?= e($sellerName) ?> ↔ 🙋 <?= e($buyerName) ?>
</h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;">
    По товару: <?php if ($product): ?><a href="/product/<?= e($product['slug']) ?>" target="_blank" style="color:var(--g-accent-light);"><?= e($product['title']) ?></a><?php else: ?>товар удалён<?php endif; ?>
    · только просмотр, админ в эту переписку не пишет
</p>

<div class="card" style="padding:20px;max-width:800px;">
<?php if (!$messages): ?>
    <p style="color:var(--g-text-secondary);">Сообщений нет.</p>
<?php else: ?>
    <?php foreach ($messages as $msg): $mine = (int)$msg['sender_id'] === $sellerId; ?>
        <div style="display:flex;<?= $mine ? 'justify-content:flex-end;' : '' ?>margin-bottom:12px;">
            <div style="max-width:75%;background:<?= $mine ? 'rgba(232,28,90,.15)' : 'rgba(255,255,255,.05)' ?>;border-radius:12px;padding:10px 14px;">
                <div style="font-size:12px;color:var(--g-text-secondary);margin-bottom:4px;">
                    <?= $mine ? '🏪 ' . e($sellerName) : '🙋 ' . e($msg['sender_name'] ?: $buyerName) ?>
                    <?php if (!empty($msg['is_admin'])): ?> · <span style="color:#FF9F0A;">от имени админа</span><?php endif; ?>
                    · <?= e($msg['created_at']) ?>
                </div>
                <?php if ($msg['message']): ?><div style="white-space:pre-wrap;font-size:14px;"><?= e($msg['message']) ?></div><?php endif; ?>
                <?php if (!empty($msg['attachment_path'])): ?><a href="<?= e(UPLOAD_URL . $msg['attachment_path']) ?>" target="_blank" style="display:inline-block;margin-top:6px;color:var(--g-accent-light);font-size:13px;">📎 Вложение</a><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
