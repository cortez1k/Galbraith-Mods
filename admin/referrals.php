<?php
$stmt = getDB()->query("SELECT r.*, ru.name as referrer_name, rf.name as referred_name,
    (SELECT COUNT(*) FROM orders o WHERE o.buyer_id = r.referred_user_id AND o.status = 'completed') as purchases,
    (SELECT COALESCE(SUM(amount),0) FROM referral_bonuses rb WHERE rb.referrer_id = r.referrer_id AND rb.referred_user_id = r.referred_user_id) as bonuses_earned
    FROM referrals r LEFT JOIN users ru ON r.referrer_id = ru.id LEFT JOIN users rf ON r.referred_user_id = rf.id
    ORDER BY r.created_at DESC");
$referrals = $stmt->fetchAll();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle = 'Реферальная программа';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Реферальная программа</h1>

<?php if ($flash): ?><div style="background:rgba(255,80,80,0.1);border:1px solid rgba(255,80,80,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<div class="card" style="padding:18px;margin-bottom:24px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:10px;">Добавить связь вручную</h3>
    <form method="POST" action="/admin/referrals/add" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="number" name="referrer_id" class="input" placeholder="ID реферера" style="width:160px;" required>
        <input type="number" name="referred_user_id" class="input" placeholder="ID приглашённого" style="width:160px;" required>
        <button type="submit" class="btn btn-primary btn-sm">Добавить</button>
    </form>
</div>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Реферер</th><th>Приглашённый</th><th>Дата</th><th>Истекает</th><th>Статус</th><th>Покупок</th><th>Бонусов начислено</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($referrals as $r): ?>
        <tr>
            <td><?= e($r['referrer_name']) ?></td>
            <td><?= e($r['referred_name']) ?></td>
            <td><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
            <td><?= date('d.m.Y', strtotime($r['expires_at'])) ?></td>
            <td><?= strtotime($r['expires_at']) > time() ? '🟢 активна' : '⚪ истекла' ?></td>
            <td><?= $r['purchases'] ?></td>
            <td><?= $r['bonuses_earned'] ?></td>
            <td><form method="POST" action="/admin/referrals/<?= $r['id'] ?>/delete"><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Удалить связь?')">Удалить</button></form></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
