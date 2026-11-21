<?php
requireLogin();
$user = currentUser();
$rows = getBuyerChats($user['id']);
$groups = [];
foreach ($rows as $r) { $key = $r['product_id'] . ':' . $r['seller_id']; $groups[$key] = $r; }
$pageTitle = 'Мои чаты';
include __DIR__ . '/../templates/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:10px;flex-wrap:wrap"><h1 style="font-size:22px;font-weight:700">💬 Чаты с продавцами</h1><a href="/profile" class="btn btn-secondary">В личный кабинет</a></div>

<?php if (!$groups): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary)">Вы пока не задавали вопросов продавцам. Открыть чат можно со страницы товара.</div>
<?php else: ?>
    <div style="display:grid;gap:12px">
        <?php foreach ($groups as $r): ?>
            <a href="/chat?product_id=<?= (int)$r['product_id'] ?>&buyer_id=<?= (int)$user['id'] ?>" class="card chat-list-item">
                <div><strong><?= e($r['product_title'] ?? 'Товар') ?></strong><div style="font-size:13px;color:var(--g-text-secondary);margin-top:4px">Продавец: <?= e($r['seller_name'] ?? 'Пользователь') ?></div></div>
                <div style="text-align:right"><div style="font-size:12px;color:var(--g-text-secondary)"><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></div><div style="color:var(--g-accent-light);font-weight:600;margin-top:5px">Открыть →</div></div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/../templates/footer.php'; ?>
