<?php
$id = (int)($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) redirect('/admin/traders');

$balance = getUserBalance($id);
$bonusBalance = getTraderBonusBalance($id);
$penalties = getAllPenalties($id);
$activePenalties = array_filter($penalties, function ($p) { return $p['is_active']; });
$maxActive = (int)setting('penalty_max_active', 5);
$bonusHistory = getTraderBonusHistory($id);
$stmt = getDB()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$id]);
$transactions = $stmt->fetchAll();

$pageTitle = 'Торговец: ' . $u['name'];
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:700;margin-bottom:4px;"><?= e($u['name']) ?> <?= $u['trader_frozen'] ? '<span style="color:#FF3B30;font-size:14px;">(удалён/заморожен)</span>' : '' ?></h1>
<p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:20px;"><?= e($u['email']) ?> · <a href="/admin/users/<?= $u['id'] ?>" style="color:var(--g-accent-light);">Роль и реквизиты →</a></p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px;">
    <div class="card" style="padding:18px;text-align:center;"><div style="font-size:22px;font-weight:700;color:var(--g-accent-light);"><?= number_format($balance,0,',',' ') ?> ₽</div><div style="font-size:13px;color:var(--g-text-secondary);">Финансовый баланс</div></div>
    <div class="card" style="padding:18px;text-align:center;"><div style="font-size:22px;font-weight:700;color:var(--g-accent-light);"><?= rtrim(rtrim(number_format($bonusBalance,2,'.',' '),'0'),'.') ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Баланс бонусов</div></div>
    <div class="card" style="padding:18px;text-align:center;"><div style="font-size:22px;font-weight:700;color:<?= count($activePenalties) > 0 ? '#FF3B30' : 'var(--g-accent-light)' ?>;"><?= count($activePenalties) ?></div><div style="font-size:13px;color:var(--g-text-secondary);">Активных взысканий</div></div>
</div>

<?php if (count($activePenalties) >= $maxActive && !$u['trader_frozen']): ?>
<div style="background:rgba(255,59,48,0.1);border:1px solid rgba(255,59,48,0.3);border-radius:var(--g-radius);padding:18px 20px;margin-bottom:24px;">
    <strong style="color:#FF3B30;">⚠️ Достигнут лимит взысканий (<?= count($activePenalties) ?>/<?= $maxActive ?>)</strong>
    <p style="font-size:14px;margin:8px 0 12px;">Выберите действие:</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <form method="POST" action="/admin/traders/<?= $id ?>/penalty/remove-one"><button type="submit" class="btn btn-secondary btn-sm">Дать шанс (снять 1 взыскание)</button></form>
        <form method="POST" action="/admin/traders/<?= $id ?>/delete-trader" onclick="return confirm('Удалить торговца? Товары скроются, активные заказы отменятся, баланс заморозится.')"><button type="submit" class="btn btn-danger btn-sm">Удалить торговца</button></form>
    </div>
</div>
<?php endif; ?>

<div class="card" style="padding:20px;margin-bottom:20px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;">Начислить/списать бонусы вручную</h3>
    <form method="POST" action="/admin/traders/<?= $id ?>/bonus-adjust" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="number" step="0.01" name="amount" class="input" placeholder="+/-" style="width:120px;" required>
        <input type="text" name="reason" class="input" placeholder="Причина (обязательно)" style="flex:1;min-width:200px;" required>
        <button type="submit" class="btn btn-primary btn-sm">Применить</button>
    </form>
</div>

<div class="card" style="padding:20px;margin-bottom:20px;"><h3 style="font-size:15px;font-weight:600;margin-bottom:12px;">Индивидуальная комиссия</h3><form method="POST" action="/admin/users/<?= $id ?>/edit" style="display:flex;gap:8px;max-width:500px"><input type="number" step="0.01" name="commission_percent" class="input" value="<?= e($u['commission_percent'] ?? '') ?>" placeholder="Пусто = глобальная комиссия"><input type="hidden" name="full_name" value="<?=e($u['full_name']??'')?>"><input type="hidden" name="contact_info" value="<?=e($u['contact_info']??'')?>"><button class="btn btn-primary btn-sm">Сохранить</button></form></div>

<div class="card" style="padding:20px;margin-bottom:20px;">
    <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;">Вынести взыскание</h3>
    <form method="POST" action="/admin/traders/<?= $id ?>/penalty/issue" style="display:grid;gap:10px;max-width:500px;">
        <select name="reason" class="select" required>
            <?php foreach (PENALTY_REASONS as $key => $label): ?><option value="<?= $key ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <textarea name="comment" class="input" rows="2" placeholder="Комментарий (обязательно)" required></textarea>
        <button type="submit" class="btn btn-danger btn-sm" style="width:fit-content;" onclick="return confirm('Вынести взыскание? Комиссия торговца увеличится.')">Вынести взыскание</button>
    </form>
</div>

<?php if ($u['trader_frozen']): ?>
<div class="card" style="padding:20px;margin-bottom:20px;">
    <form method="POST" action="/admin/traders/<?= $id ?>/restore-trader" onclick="return confirm('Восстановить торговца? Роль, товары и баланс вернутся.')">
        <button type="submit" class="btn btn-primary">Восстановить торговца</button>
    </form>
</div>
<?php endif; ?>

<h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">История взысканий</h3>
<?php if (!$penalties): ?><p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:24px;">Взысканий не было</p><?php else: ?>
<div style="overflow-x:auto;margin-bottom:24px;">
<table class="admin-table">
    <thead><tr><th>Причина</th><th>Кто вынес</th><th>Дата</th><th>Истекает</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($penalties as $p): ?>
        <tr>
            <td><?= e($p['reason']) ?><?= $p['comment'] ? '<br><span style="font-size:12px;color:var(--g-text-secondary);">' . e($p['comment']) . '</span>' : '' ?></td>
            <td><?= e($p['issued_by_name'] ?? '—') ?></td>
            <td><?= date('d.m.Y', strtotime($p['issued_at'])) ?></td>
            <td><?= date('d.m.Y', strtotime($p['expires_at'])) ?></td>
            <td><?= $p['is_active'] ? '🔴 Активно' : '⚪ Снято' ?></td>
            <td><?php if ($p['is_active']): ?><form method="POST" action="/admin/traders/<?= $id ?>/penalty/<?= $p['id'] ?>/remove"><button type="submit" class="btn btn-secondary btn-sm">Снять</button></form><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">История бонусных транзакций</h3>
<?php if (!$bonusHistory): ?><p style="color:var(--g-text-secondary);font-size:14px;">Транзакций не было</p><?php else: ?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Дата</th><th>Тип</th><th>Сумма</th><th>Причина</th></tr></thead>
    <tbody>
    <?php foreach ($bonusHistory as $h): ?>
        <tr><td><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td><td><?= e($h['type']) ?></td><td><?= $h['amount'] ?></td><td><?= e($h['reason']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
