<?php
$order=getOrderById((int)($_GET['id']??0));if(!$order)redirect('/orders');$user=currentUser();$isBuyer=$user&&(int)$user['id']===(int)$order['buyer_id'];$isSeller=$user&&(int)$user['id']===(int)$order['seller_id'];if(!$isBuyer&&!$isSeller&&!hasRole('admin'))redirect('/orders');autoConfirmIfDue($order);$order=getOrderById($order['id']);$messages=getOrderMessages($order['id']);$stmt=getDB()->prepare('SELECT * FROM reviews WHERE order_id=?');$stmt->execute([$order['id']]);$existingReview=$stmt->fetch();$pageTitle='Заказ #'.$order['id'];include __DIR__.'/../templates/header.php';
?>
<?php $flash=$_SESSION['flash']??null; unset($_SESSION['flash']); if($flash): ?><div class="card" style="padding:14px;margin-bottom:16px"><?=e($flash)?></div><?php endif; ?>
<?php if($isBuyer && receiptStatusAllowed($order['status'])): ?>
<div class="card" style="padding:20px;margin-bottom:20px;border-color:rgba(52,199,89,.35)"><div style="font-size:18px;font-weight:700;margin-bottom:6px">✓ Покупка успешно завершена</div><div style="color:var(--g-text-secondary);font-size:14px;margin-bottom:14px">Заказ #<?=$order['id']?> · <?=e($order['product_title'])?> · <?=number_format($order['final_amount'],2,',',' ')?> ₽</div><div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn btn-secondary" href="/orders/<?=$order['id']?>/receipt">Посмотреть</a><a class="btn btn-secondary" href="/orders/<?=$order['id']?>/receipt-pdf">Скачать PDF</a><a class="btn btn-secondary" href="/orders/<?=$order['id']?>/receipt#emailBox">Отправить на email</a></div></div>
<?php endif; ?>
<a href="/orders" class="btn btn-secondary" style="margin-bottom:16px">← Мои заказы</a>
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px"><div><h1 style="font-size:20px;font-weight:700"><?=e($order['product_title'])?></h1><div style="font-size:13px;color:var(--g-text-secondary)">Заказ #<?=$order['id']?> · <?=$isBuyer?'Продавец: '.e($order['seller_name']):'Покупатель: '.e($order['buyer_name'])?></div></div><span class="status status-<?= $order['status']==='completed'?'closed':($order['status']==='awaiting_confirmation'?'new':'progress') ?>" style="font-size:13px;padding:6px 12px"><?=e(orderStatusLabel($order['status']))?></span></div>
<div class="order-grid" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:20px;align-items:start">
<div class="chat-card">
 <div class="chat-head"><div><strong>💬 Чат заказа</strong><div class="chat-subtitle">Сообщения обновляются автоматически</div></div></div>
 <div id="orderChatBox" class="seller-chat-box" style="height:520px" data-order="<?=$order['id']?>">
 <?php echo renderOrderChatMessages($messages, (int)$user['id']); ?>
 </div>
 <?php if(!in_array($order['status'],['completed','refunded','cancelled'])):?><form method="POST" action="/orders/<?=$order['id']?>/message" enctype="multipart/form-data" class="seller-chat-form" id="orderChatForm"><textarea name="message" id="orderChatText" class="input" rows="2" placeholder="Сообщение... (можно вставить картинку из буфера обмена — Ctrl+V)"></textarea><div id="orderPastePreview" style="display:none;margin:8px 0;position:relative;width:fit-content;"><img style="max-height:90px;border-radius:8px;display:block;"><button type="button" onclick="document.getElementById('orderFileInput').value='';document.getElementById('orderPastePreview').style.display='none';" style="position:absolute;top:-8px;right:-8px;width:22px;height:22px;border-radius:50%;background:var(--g-danger);color:#fff;border:none;cursor:pointer;font-size:13px;line-height:1;">✕</button></div><div class="chat-form-bottom"><label class="chat-file">📎 <input type="file" name="attachment" id="orderFileInput" accept="image/*"><span>Изображение</span></label><button type="submit" class="btn btn-primary btn-sm">Отправить</button></div></form>
<script>
(function(){
  const ta=document.getElementById('orderChatText'), fi=document.getElementById('orderFileInput'), pv=document.getElementById('orderPastePreview');
  if(!ta) return;
  ta.addEventListener('paste', function(e){
    const items=(e.clipboardData||window.clipboardData).items;
    for(const item of items){
      if(item.type.indexOf('image')===0){
        const file=item.getAsFile();
        const dt=new DataTransfer(); dt.items.add(file); fi.files=dt.files;
        pv.querySelector('img').src=URL.createObjectURL(file); pv.style.display='block';
        e.preventDefault();
        break;
      }
    }
  });
})();
</script>
<?php endif;?>
</div>
<div style="display:flex;flex-direction:column;gap:14px">
<div class="card" style="padding:16px;font-size:14px"><div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="color:var(--g-text-secondary)">Цена товара</span><span><?=number_format($order['amount'],2,',',' ')?> ₽</span></div><?php if((float)$order['discount_amount']>0):?><div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="color:var(--g-text-secondary)">Скидка</span><span>−<?=number_format($order['discount_amount'],2,',',' ')?> ₽</span></div><?php endif;?><?php if(!empty($order['promo_code'])):?><div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="color:var(--g-text-secondary)">Промокод</span><span><?=e($order['promo_code'])?> (−<?=number_format($order['promo_discount_amount'],2,',',' ')?> ₽)</span></div><?php endif;?><div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid var(--g-border);padding-top:8px;margin-top:4px"><span>К оплате</span><span><?=number_format($order['final_amount'],2,',',' ')?> ₽</span></div><?php if(hasRole('admin')&&!$isBuyer):?><div style="display:flex;justify-content:space-between;margin-top:8px;color:var(--g-text-secondary);font-size:12px"><span>Комиссия (<?=$order['commission_percent']?>%)</span><span><?=number_format($order['commission_amount'],2,',',' ')?> ₽</span></div><div style="display:flex;justify-content:space-between;color:var(--g-text-secondary);font-size:12px"><span>К выплате торговцу</span><strong><?=number_format($order['net_amount'],0,',',' ')?> ₽</strong></div><?php endif;?></div>
<?php if(($isBuyer||$isSeller)&&in_array($order['status'],['paid','in_progress'])):?><form method="POST" action="/orders/<?=$order['id']?>/dispute"><button type="submit" class="btn btn-secondary" style="width:100%" onclick="return confirm('Открыть спор?')">⚠️ Открыть спор</button></form><?php endif;?>
<?php if($isBuyer&&in_array($order['status'],['paid','in_progress'])):?><form method="POST" action="/orders/<?=$order['id']?>/confirm"><button class="btn btn-primary" style="width:100%" onclick="return confirm('Подтвердить получение товара?')">✅ Подтвердить получение</button></form><?php endif;?>
<?php if($isBuyer&&$order['status']==='completed'&&!$existingReview):?><div class="card" style="padding:16px"><h4 style="font-size:14px;font-weight:600;margin-bottom:10px">Оставить отзыв</h4><form method="POST" action="/orders/<?=$order['id']?>/review"><select name="rating" class="select" style="width:100%;margin-bottom:8px"><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>"><?=str_repeat('⭐',$i)?></option><?php endfor;?></select><textarea name="comment" class="input" rows="3" placeholder="Комментарий"></textarea><button class="btn btn-primary" style="width:100%;margin-top:8px">Отправить</button></form></div><?php endif;?>
</div></div></div>
<script>(function(){const b=document.getElementById('orderChatBox');let last='';let firstLoad=true;async function poll(){try{const r=await fetch('/api/order-chat?id='+b.dataset.order);const j=await r.json();if(!j.success)return;const h=j.html;if(h!==last){const near=b.scrollHeight-b.scrollTop-b.clientHeight<100;b.innerHTML=h;last=h;if(near)b.scrollTop=b.scrollHeight;if(!firstLoad){const rows=b.querySelectorAll('.seller-chat-row');const lastRow=rows[rows.length-1];if(lastRow&&!lastRow.classList.contains('mine')&&window.galbraithPlaySound)window.galbraithPlaySound()}firstLoad=false}}catch(e){}}b.scrollTop=b.scrollHeight;poll();setInterval(poll,2000)})();</script>
<style>@media(max-width:800px){.order-grid{grid-template-columns:1fr!important}}</style>
<?php include __DIR__.'/../templates/footer.php'; ?>
