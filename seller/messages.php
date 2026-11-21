<?php
$user=currentUser();
$tab=($_GET['tab']??'buyers')==='sellers'?'sellers':'buyers';

$rowsBuyers=getSellerMessages($user['id']);
$groupsBuyers=[];foreach($rowsBuyers as $r){$key=$r['product_id'].':'.$r['buyer_id'];$groupsBuyers[$key]=$r;}

$rowsSellers=getBuyerChats($user['id']);
$groupsSellers=[];foreach($rowsSellers as $r){$key=$r['product_id'].':'.$r['seller_id'];$groupsSellers[$key]=$r;}

$pageTitle='Чаты'; include __DIR__.'/../templates/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:10px;flex-wrap:wrap"><h1 style="font-size:22px;font-weight:700">💬 Чаты</h1><a href="/seller" class="btn btn-secondary">К панели продавца</a></div>

<div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid var(--g-border);">
    <a href="?tab=buyers" style="padding:10px 16px;font-weight:600;font-size:14px;color:<?= $tab==='buyers'?'var(--g-text)':'var(--g-text-secondary)' ?>;border-bottom:2px solid <?= $tab==='buyers'?'var(--g-accent)':'transparent' ?>;">С покупателями (<?= count($groupsBuyers) ?>)</a>
    <a href="?tab=sellers" style="padding:10px 16px;font-weight:600;font-size:14px;color:<?= $tab==='sellers'?'var(--g-text)':'var(--g-text-secondary)' ?>;border-bottom:2px solid <?= $tab==='sellers'?'var(--g-accent)':'transparent' ?>;">С продавцами (<?= count($groupsSellers) ?>)</a>
</div>

<?php if ($tab === 'buyers'): ?>
    <?php if(!$groupsBuyers): ?><div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary)">Вопросов от покупателей пока нет.</div>
    <?php else: ?><div style="display:grid;gap:12px"><?php foreach($groupsBuyers as $r): ?><a href="/chat?product_id=<?= (int)$r['product_id'] ?>&buyer_id=<?= (int)$r['buyer_id'] ?>" class="card chat-list-item"><div><strong><?= e($r['product_title']??'Товар') ?></strong><div style="font-size:13px;color:var(--g-text-secondary);margin-top:4px">Покупатель: <?= e($r['buyer_name']??'Пользователь') ?></div></div><div style="text-align:right"><div style="font-size:12px;color:var(--g-text-secondary)"><?= e(date('d.m.Y H:i',strtotime($r['created_at']))) ?></div><div style="color:var(--g-accent-light);font-weight:600;margin-top:5px">Открыть →</div></div></a><?php endforeach; ?></div><?php endif; ?>
<?php else: ?>
    <?php if(!$groupsSellers): ?><div class="card" style="padding:40px;text-align:center;color:var(--g-text-secondary)">Вы пока не задавали вопросов продавцам.</div>
    <?php else: ?><div style="display:grid;gap:12px"><?php foreach($groupsSellers as $r): ?><a href="/chat?product_id=<?= (int)$r['product_id'] ?>&buyer_id=<?= (int)$user['id'] ?>" class="card chat-list-item"><div><strong><?= e($r['product_title']??'Товар') ?></strong><div style="font-size:13px;color:var(--g-text-secondary);margin-top:4px">Продавец: <?= e($r['seller_name']??'Пользователь') ?></div></div><div style="text-align:right"><div style="font-size:12px;color:var(--g-text-secondary)"><?= e(date('d.m.Y H:i',strtotime($r['created_at']))) ?></div><div style="color:var(--g-accent-light);font-weight:600;margin-top:5px">Открыть →</div></div></a><?php endforeach; ?></div><?php endif; ?>
<?php endif; ?>
<?php include __DIR__.'/../templates/footer.php'; ?>
