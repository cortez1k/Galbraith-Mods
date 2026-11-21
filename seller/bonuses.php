<?php
$user = currentUser();
$balance = getTraderBonusBalance($user['id']);
$buyerBonusBalance = getBonusBalance($user['id']);
$tasks = getTraderTasksWithProgress($user['id']);
$options = getBonusShopOptions();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$pageTitle = 'Мои бонусы';
include __DIR__ . '/../templates/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <h1 style="font-size:22px;font-weight:700;">Мои бонусы</h1>
    <div style="display:flex;gap:8px;">
        <a href="/seller/referrals" class="btn btn-secondary btn-sm">Реферальная программа</a>
        <a href="/seller/discipline" class="btn btn-secondary btn-sm">Дисциплина</a>
    </div>
</div>

<?php if ($flash): ?><div style="background:rgba(232,28,90,0.1);border:1px solid rgba(232,28,90,0.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px;"><?= e($flash) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:20px"><div class="card" style="padding:20px;text-align:center"><div style="font-size:12px;color:var(--g-text-secondary)">Бонусы торговца</div><div style="font-size:30px;font-weight:800;color:var(--g-accent-light)"><?=rtrim(rtrim(number_format($balance,2,'.',' '),'0'),'.')?></div></div><div class="card" style="padding:20px;text-align:center"><div style="font-size:12px;color:var(--g-text-secondary)">Бонусы покупателя</div><div style="font-size:30px;font-weight:800;color:var(--g-accent-light)"><?=$buyerBonusBalance?></div></div></div>
<div class="card" style="padding:28px;text-align:center;margin-bottom:28px;background:linear-gradient(135deg,rgba(232,28,90,0.12),rgba(232,28,90,0.03));">
    <div style="font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Баланс бонусов</div>
    <div style="font-size:40px;font-weight:800;color:var(--g-accent-light);"><?= rtrim(rtrim(number_format($balance, 2, '.', ' '), '0'), '.') ?></div>
</div>

<h2 style="font-size:17px;font-weight:600;margin-bottom:14px;">Задания</h2>
<?php if (!$tasks): ?>
    <p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:28px;">Активных заданий пока нет</p>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:28px;">
    <?php foreach ($tasks as $t): ?>
    <div class="card" style="padding:16px 18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
            <div>
                <strong><?= e($t['title']) ?></strong>
                <?php if ($t['is_completed'] && !$t['is_repeatable']): ?><span style="color:#34C759;margin-left:8px;">✅ Выполнено</span><?php endif; ?>
                <?php if ($t['is_repeatable']): ?><span style="color:var(--g-text-secondary);font-size:12px;margin-left:8px;">(многократное)</span><?php endif; ?>
            </div>
            <span style="font-weight:700;color:var(--g-accent-light);">+<?= rtrim(rtrim(number_format($t['bonus_amount'],2,'.',' '),'0'),'.') ?></span>
        </div>
        <?php if ($t['description']): ?><p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:8px;"><?= e($t['description']) ?></p><?php endif; ?>
        <div style="background:var(--g-bg);border-radius:20px;height:8px;overflow:hidden;margin-bottom:6px;">
            <div style="background:var(--g-accent);height:100%;width:<?= $t['percent'] ?>%;"></div>
        </div>
        <div style="font-size:12px;color:var(--g-text-secondary);"><?= $t['progress'] ?> / <?= $t['condition_value'] ?> (<?= $t['percent'] ?>%)</div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2 style="font-size:17px;font-weight:600;margin-bottom:14px;">Магазин бонусов</h2>
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
    <?php foreach ($options as $key => $opt): ?>
    <div class="card" style="padding:16px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
            <strong><?= e($opt['label']) ?></strong>
            <div style="font-size:13px;color:var(--g-text-secondary);">Стоимость: <?= $opt['cost'] ?> <?= $key === 'cash_conversion' ? 'бонус за единицу' : 'бонусов' ?></div>
        </div>
        <form method="POST" action="/seller/bonuses/activate" style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="option_type" value="<?= $key ?>">
            <?php if ($key === 'cash_conversion'): ?>
                <input type="number" name="qty" min="1" value="1" class="input" style="width:80px;">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Использовать эту опцию?')" <?= $balance < $opt['cost'] ? 'disabled' : '' ?>>Использовать</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<a href="#" onclick="document.getElementById('historyBox').style.display='block';this.style.display='none';return false;" style="font-size:14px;color:var(--g-accent-light);">История операций →</a>
<div id="historyBox" style="display:none;margin-top:14px;overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Дата</th><th>Тип</th><th>Сумма</th><th>Причина</th></tr></thead>
    <tbody>
    <?php foreach (getTraderBonusHistory($user['id']) as $h): ?>
        <tr>
            <td><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td>
            <td><?= ['auto_accrual'=>'Автоначисление','manual'=>'Вручную','spend'=>'Списание','referral'=>'Реферал'][$h['type']] ?? $h['type'] ?></td>
            <td style="color:<?= $h['amount'] >= 0 ? '#34C759' : '#FF3B30' ?>;"><?= ($h['amount'] >= 0 ? '+' : '') . $h['amount'] ?></td>
            <td><?= e($h['reason']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
