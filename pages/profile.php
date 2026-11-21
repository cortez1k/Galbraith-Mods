<?php
$user=currentUser();$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);$hasRealEmail=strpos($user['email'],'@google.placeholder')===false&&strpos($user['email'],'@vk.placeholder')===false;$buyerBonus=getBonusBalance($user['id']);$traderBonus=hasRole('trader')?getTraderBonusBalance($user['id']):0;$myOrdersCount=count(getOrdersForBuyer($user['id']));
$pageTitle='Личный кабинет';include __DIR__.'/../templates/header.php';
?>
<div style="max-width:760px;margin:0 auto"><?php if($flash):?><div style="background:rgba(232,28,90,.1);border:1px solid rgba(232,28,90,.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px"><?=e($flash)?></div><?php endif;?>
<div class="card" style="padding:28px;display:flex;gap:20px;align-items:center;margin-bottom:24px;flex-wrap:wrap">
    <div><img src="<?=e(avatarUrl($user['avatar'],$user['id']))?>" style="width:82px;height:82px;border-radius:50%;object-fit:cover"></div>
    <div style="flex:1;min-width:200px;">
        <h1 style="font-size:22px;font-weight:700;margin-bottom:4px"><?=e($user['name']?:'Пользователь')?></h1>
        <div style="color:var(--g-text-secondary);font-size:14px;margin-bottom:7px"><?=$hasRealEmail?e($user['email']):'Email не привязан'?><?php if($hasRealEmail&&empty($user['email_verified_at'])):?><span style="color:#ff9f0a"> (не подтверждён)</span><?php endif;?></div>
        <span style="display:inline-block;padding:4px 10px;border-radius:20px;background:<?=e(roleColor($user['role']))?>;color:#fff;font-size:12px;font-weight:600"><?=e(roleLabel($user['role']))?></span>
        <?php foreach(extraRoles($user['id']) as $extraRole): ?><span style="display:inline-block;padding:4px 10px;border-radius:20px;background:<?=e(roleColor($extraRole))?>;color:#fff;font-size:12px;font-weight:600;margin-left:6px"><?=e(roleLabel($extraRole))?></span><?php endforeach; ?>
    </div>
    <a href="/profile/settings" class="btn btn-secondary">⚙️ Настройки</a>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px"><a href="/orders" class="card" style="padding:18px;text-align:center;font-weight:600">🧾 Мои заказы (<?=$myOrdersCount?>)</a><a href="<?= hasRole('trader') ? '/seller/messages' : '/chats' ?>" class="card" style="padding:18px;text-align:center;font-weight:600">💬 Мои чаты</a><a href="/profile/favorites" class="card" style="padding:18px;text-align:center;font-weight:600">❤️ Избранное</a><a href="/profile/subscriptions" class="card" style="padding:18px;text-align:center;font-weight:600">🔔 Подписки</a><a href="/profile/bonuses" class="card" style="padding:18px;text-align:center;font-weight:600">🎁 Бонусы покупателя: <?=$buyerBonus?></a><?php if(hasRole('trader')):?><a href="/seller/bonuses" class="card" style="padding:18px;text-align:center;font-weight:600">🛍️ Бонусы торговца: <?=$traderBonus?></a><a href="/seller" class="card" style="padding:18px;text-align:center;font-weight:600">Панель продавца</a><?php endif;?></div>
<div class="card" style="padding:20px;margin-bottom:20px"><h3 style="font-size:16px;font-weight:600;margin-bottom:12px">🎁 Бонусы покупателя</h3><p style="font-size:13px;color:var(--g-text-secondary);line-height:1.6">Бонусы можно использовать при покупке. <strong>10 бонусов = 5% скидки</strong> на следующий заказ. Неиспользованный остаток сохраняется.</p><a href="/profile/bonuses" class="btn btn-secondary btn-sm" style="margin-top:10px">Подробнее о бонусах</a></div>
<a href="/logout" class="btn btn-secondary" style="width:100%;margin-top:8px">Выйти</a></div>
<?php include __DIR__.'/../templates/footer.php'; ?>
