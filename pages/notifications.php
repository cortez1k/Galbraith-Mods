<?php
// pages/notifications.php
requireLogin();
$pageTitle = 'Уведомления — Galbraith';
require __DIR__ . '/../templates/header.php';

$notifications = getNotifications($_SESSION['user_id'], 100);
$unreadIds = array_filter(array_column($notifications, 'id', 'is_read'), fn($read) => $read === '0');

// Отметить все как прочитанные
if (!empty($notifications)) {
    getDB()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
        ->execute([$_SESSION['user_id']]);
}
?>
<div style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
    <h1 style="color: var(--g-text); margin: 0 0 30px 0; font-size: 28px;">Уведомления</h1>
    
    <?php if (empty($notifications)): ?>
        <div style="text-align: center; color: var(--g-text-secondary); padding: 60px 20px;">
            <p style="font-size: 16px;">Нет уведомлений</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($notifications as $n):
                $data = json_decode($n['data'] ?? '{}', true) ?: [];
                $type = $n['type'];
                $title = notificationSubject($type);
                $text = notificationPreview($type, $data);
                $icon = notificationIcon($type);
                $time = formatNotificationTime($n['created_at']);
                $isUnread = empty($n['is_read']);
                
                $url = '/profile';
                if (!empty($data['order_id'])) {
                    $url = '/orders/' . (int)$data['order_id'];
                } elseif ($type === 'seller_question') {
                    $url = hasRole('trader') ? '/seller/messages' : '/profile';
                }
            ?>
                <a href="<?= htmlspecialchars($url) ?>" 
                   style="display: flex; gap: 14px; padding: 16px 18px; background: rgba(255,255,255,0.03); border: 1px solid var(--g-border); border-radius: 8px; color: var(--g-text); text-decoration: none; transition: all 0.2s; <?= $isUnread ? 'background: rgba(232, 28, 90, 0.08); border-color: var(--g-accent);' : '' ?>"
                   onmouseover="this.style.background='rgba(255,255,255,0.06); this.style.borderColor=\'var(--g-accent)\';'" 
                   onmouseout="this.style.background='<?= $isUnread ? 'rgba(232, 28, 90, 0.08)' : 'rgba(255,255,255,0.03)' ?>'; this.style.borderColor='var(--g-border)';'">
                    <div style="flex-shrink: 0; font-size: 20px; line-height: 1;"><?= $icon ?></div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: start; gap: 12px; margin-bottom: 4px;">
                            <div style="font-weight: 500; font-size: 14px;"><?= e($title) ?></div>
                            <?php if ($isUnread): ?>
                                <div style="flex-shrink: 0; width: 8px; height: 8px; background: var(--g-accent); border-radius: 50%; margin-top: 4px;"></div>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 13px; color: var(--g-text-secondary); line-height: 1.4; margin-bottom: 6px;">
                            <?= e($text) ?>
                        </div>
                        <div style="font-size: 11px; color: var(--g-text-secondary-dim);">
                            <?= e($time) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
