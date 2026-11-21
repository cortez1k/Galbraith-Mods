<?php
$db = getDB();

$totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalTraders = $db->query("SELECT COUNT(*) FROM users WHERE role = 'trader'")->fetchColumn();
$newUsers30 = $db->query("SELECT COUNT(*) FROM users WHERE created_at >= datetime('now','-30 days')")->fetchColumn();

$totalOrders = $db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$completedOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
$disputedOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'disputed'")->fetchColumn();
$gmv = $db->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = 'completed'")->fetchColumn();
$commissionRevenue = $db->query("SELECT COALESCE(SUM(commission_amount),0) FROM orders WHERE status = 'completed'")->fetchColumn();
$gmv30 = $db->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status = 'completed' AND created_at >= datetime('now','-30 days')")->fetchColumn();

$totalProducts = $db->query("SELECT COUNT(*) FROM trader_products WHERE status = 'approved'")->fetchColumn();
$onModeration = $db->query("SELECT COUNT(*) FROM trader_products WHERE status = 'on_moderation'")->fetchColumn();
$freeProducts = $db->query("SELECT COUNT(*) FROM trader_products WHERE status = 'approved' AND price = 0")->fetchColumn();
$paidProducts = $db->query("SELECT COUNT(*) FROM trader_products WHERE status = 'approved' AND price > 0")->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) as c, COALESCE(SUM(amount),0) as s FROM withdrawal_requests WHERE status = 'pending'");
$pw = $stmt->fetch();

$avgRating = $db->query('SELECT AVG(rating) FROM reviews WHERE is_hidden = 0')->fetchColumn();
$activePenalties = $db->query("SELECT COUNT(*) FROM trader_penalties WHERE is_active = 1 AND expires_at > datetime('now')")->fetchColumn();

$topSellers = $db->query("SELECT u.name, COUNT(*) as cnt, COALESCE(SUM(o.amount),0) as revenue
    FROM orders o JOIN users u ON o.seller_id = u.id
    WHERE o.status = 'completed' GROUP BY o.seller_id ORDER BY revenue DESC LIMIT 5")->fetchAll();

$topProducts = $db->query("SELECT p.title, p.downloads_count, p.views_count FROM trader_products p WHERE p.status='approved' ORDER BY p.downloads_count DESC LIMIT 5")->fetchAll();

// Orders per day, last 14 days
$dailyOrders = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE date(created_at) = ?");
    $stmt->execute([$day]);
    $dailyOrders[$day] = (int)$stmt->fetchColumn();
}
$maxDaily = max(1, max($dailyOrders));

$pageTitle = 'Статистика';
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:28px;">
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= number_format($totalUsers,0,',',' ') ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Пользователей (+<?= $newUsers30 ?> за 30 дн.)</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= $totalTraders ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Торговцев</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= number_format($gmv,0,',',' ') ?> ₽</div><div style="font-size:13px;color:var(--g-text-secondary);">Оборот всего (GMV)</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= number_format($gmv30,0,',',' ') ?> ₽</div><div style="font-size:13px;color:var(--g-text-secondary);">Оборот за 30 дней</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= number_format($commissionRevenue,0,',',' ') ?> ₽</div><div style="font-size:13px;color:var(--g-text-secondary);">Доход по комиссии</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= $totalOrders ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Заказов всего (<?= $completedOrders ?> завершено)</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:<?= $disputedOrders>0?'#FF3B30':'var(--g-accent-light)' ?>;"><?= $disputedOrders ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Открытых споров</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= $totalProducts ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Товаров (<?= $paidProducts ?> платных / <?= $freeProducts ?> бесплатных)</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:<?= $onModeration>0?'#FF9F0A':'var(--g-accent-light)' ?>;"><?= $onModeration ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Товаров на модерации</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= $pw['c'] ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Заявок на вывод (<?= number_format($pw['s'],0,',',' ') ?> ₽)</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:var(--g-accent-light);"><?= $avgRating ? number_format($avgRating,1) : '—' ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Средний рейтинг продавцов</div></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:26px;font-weight:700;color:<?= $activePenalties>0?'#FF3B30':'var(--g-accent-light)' ?>;"><?= $activePenalties ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Активных взысканий</div></div>
</div>

<div class="card" style="padding:20px;margin-bottom:24px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:16px;">Заказы за последние 14 дней</h3>
    <div style="display:flex;align-items:flex-end;gap:6px;height:120px;">
        <?php foreach ($dailyOrders as $day => $count): ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;" title="<?= $day ?>: <?= $count ?>">
                <div style="width:100%;background:var(--g-accent);border-radius:3px 3px 0 0;height:<?= max(2, round($count / $maxDaily * 90)) ?>px;"></div>
                <span style="font-size:9px;color:var(--g-text-secondary);"><?= date('d.m', strtotime($day)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="stats-grid">
<style>@media(max-width:800px){.stats-grid{grid-template-columns:1fr !important;}}</style>
<div class="card" style="padding:20px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:14px;">Топ-5 продавцов по выручке</h3>
    <?php if (!$topSellers): ?><p style="color:var(--g-text-secondary);font-size:14px;">Данных пока нет</p><?php else: ?>
    <table class="admin-table"><thead><tr><th>Продавец</th><th>Заказов</th><th>Выручка</th></tr></thead><tbody>
        <?php foreach ($topSellers as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= $s['cnt'] ?></td><td><?= number_format($s['revenue'],0,',',' ') ?> ₽</td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
</div>
<div class="card" style="padding:20px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:14px;">Топ-5 товаров по скачиваниям</h3>
    <?php if (!$topProducts): ?><p style="color:var(--g-text-secondary);font-size:14px;">Данных пока нет</p><?php else: ?>
    <table class="admin-table"><thead><tr><th>Товар</th><th>Скачиваний</th><th>Просмотров</th></tr></thead><tbody>
        <?php foreach ($topProducts as $p): ?><tr><td><?= e($p['title']) ?></td><td><?= $p['downloads_count'] ?></td><td><?= $p['views_count'] ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
</div>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
