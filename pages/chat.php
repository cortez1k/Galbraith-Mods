<?php
requireLogin();
$user=currentUser();
$productId=(int)($_GET['product_id']??0);
$buyerId=(int)($_GET['buyer_id']??0);
$product=getTraderProductById($productId);
if(!$product) redirect('/marketplace');
$sellerId=(int)$product['user_id'];
if((int)$user['id']===$sellerId){ if(!$buyerId) redirect('/seller/messages'); $chatBuyer=$buyerId; } elseif((int)$user['id']===$buyerId){ $chatBuyer=(int)$user['id']; } else redirect('/');
$messages=getSellerChatMessages($productId,$chatBuyer,$sellerId);
$pageTitle='Чат — '.$product['title'];
include __DIR__.'/../templates/header.php';
?>
<div class="chat-page">
<a href="<?= (int)$user['id']===$sellerId ? '/seller/messages' : '/product/'.e($product['slug']) ?>" class="btn btn-secondary" style="margin-bottom:16px;">← Назад</a>
<div class="chat-card">
  <div class="chat-head"><div><strong><?= e($product['title']) ?></strong><div class="chat-subtitle"><?= (int)$user['id']===$sellerId ? 'Чат с покупателем' : 'Чат с продавцом' ?></div></div><span class="chat-live">● онлайн-обновление</span></div>
  <div id="sellerChatBox" class="seller-chat-box" data-product="<?= $productId ?>" data-buyer="<?= $chatBuyer ?>" data-seller="<?= $sellerId ?>">
    <?php foreach($messages as $m): $mine=(int)$m['sender_id']===(int)$user['id']; $admin=!empty($m['is_admin']); ?>
      <div class="seller-chat-row <?= $mine?'mine':'' ?>">
        <div class="seller-chat-bubble <?= $admin?'admin-message':'' ?>">
          <div class="seller-chat-meta"><strong><?= e($m['sender_name']??'Пользователь') ?></strong><?php if($admin): ?><span class="admin-tag">АДМИНИСТРАТОР</span><?php endif; ?><span><?= e(date('H:i',strtotime($m['created_at']))) ?></span></div>
          <?php if($m['message']!==''): ?><div class="seller-chat-text"><?= e($m['message']) ?></div><?php endif; ?>
          <?php if(!empty($m['attachment_path'])): ?><a class="chat-image-link" href="<?= e(UPLOAD_URL.$m['attachment_path']) ?>" target="_blank"><img src="<?= e(UPLOAD_URL.$m['attachment_path']) ?>" alt="Изображение"></a><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <form id="sellerChatForm" class="seller-chat-form" enctype="multipart/form-data">
    <textarea name="message" id="sellerChatText" class="input" rows="2" placeholder="Напишите сообщение... (можно вставить картинку из буфера обмена — Ctrl+V)"></textarea>
    <div id="chatPastePreview" style="display:none;margin:8px 0;position:relative;width:fit-content;"><img style="max-height:90px;border-radius:8px;display:block;"><button type="button" onclick="clearChatPaste()" style="position:absolute;top:-8px;right:-8px;width:22px;height:22px;border-radius:50%;background:var(--g-danger);color:#fff;border:none;cursor:pointer;font-size:13px;line-height:1;">✕</button></div>
    <div class="chat-form-bottom"><label class="chat-file">📎 <input type="file" name="attachment" id="chatFileInput" accept="image/*"> <span>Изображение</span></label><button class="btn btn-primary" type="submit">Отправить</button></div>
  </form>
</div>
</div>
<script>
(function(){
 const box=document.getElementById('sellerChatBox'), form=document.getElementById('sellerChatForm');
 let last='';
 function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
 function render(rows){const html=rows.map(m=>{const mine=Number(m.sender_id)===Number(box.dataset.user);return `<div class="seller-chat-row ${mine?'mine':''}"><div class="seller-chat-bubble ${Number(m.is_admin)?'admin-message':''}"><div class="seller-chat-meta"><strong>${esc(m.sender_name||'Пользователь')}</strong>${Number(m.is_admin)?'<span class="admin-tag">АДМИНИСТРАТОР</span>':''}<span>${esc(m.time||'')}</span></div>${m.message?`<div class="seller-chat-text">${esc(m.message)}</div>`:''}${m.attachment_path?`<a class="chat-image-link" href="${esc(m.attachment_url)}" target="_blank"><img src="${esc(m.attachment_url)}" alt="Изображение"></a>`:''}</div></div>`}).join(''); if(html!==last){const nearBottom=box.scrollHeight-box.scrollTop-box.clientHeight<100;box.innerHTML=html;last=html;if(nearBottom)box.scrollTop=box.scrollHeight; if(lastCount!==null&&rows.length>lastCount){const newest=rows[rows.length-1];if(newest&&Number(newest.sender_id)!==Number(box.dataset.user)&&window.galbraithPlaySound)window.galbraithPlaySound()}lastCount=rows.length}}
 let lastCount=null;
 async function poll(){try{const u=`/api/chat?product_id=${box.dataset.product}&buyer_id=${box.dataset.buyer}`;const r=await fetch(u,{headers:{'X-Requested-With':'XMLHttpRequest'}});const j=await r.json();if(j.success){box.dataset.user=j.user_id;render(j.messages)}}catch(e){}}
 form.addEventListener('submit',async e=>{e.preventDefault();const fd=new FormData(form);fd.append('product_id',box.dataset.product);fd.append('buyer_id',box.dataset.buyer);const btn=form.querySelector('button');btn.disabled=true;try{const r=await fetch('/chat/send',{method:'POST',body:fd});const j=await r.json();if(j.success){form.reset();if(window.clearChatPaste)clearChatPaste();await poll();box.scrollTop=box.scrollHeight}else alert(j.error||'Не удалось отправить')}finally{btn.disabled=false}});
 box.dataset.user='<?= (int)$user['id'] ?>'; box.scrollTop=box.scrollHeight; poll(); setInterval(poll,2000);

 // Вставка картинки из буфера обмена (Ctrl+V) прямо в текстовое поле сообщения —
 // подменяем содержимое обычного <input type="file">, чтобы дальше форма
 // отправлялась тем же кодом, что и обычное прикрепление файла.
 const fileInput=document.getElementById('chatFileInput'), pastePreview=document.getElementById('chatPastePreview'), pasteImg=pastePreview.querySelector('img'), textarea=document.getElementById('sellerChatText');
 window.clearChatPaste=function(){fileInput.value='';pastePreview.style.display='none';pasteImg.src=''};
 textarea.addEventListener('paste', function(e){
   const items=(e.clipboardData||window.clipboardData).items;
   for(const item of items){
     if(item.type.indexOf('image')===0){
       const file=item.getAsFile();
       const dt=new DataTransfer(); dt.items.add(file); fileInput.files=dt.files;
       pasteImg.src=URL.createObjectURL(file); pastePreview.style.display='block';
       e.preventDefault();
       break;
     }
   }
 });
})();
</script>
<?php include __DIR__.'/../templates/footer.php'; ?>
