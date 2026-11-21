<?php
requireLogin();
$user = currentUser();
$chat = getOrCreateSupportChat($user['id']);
$messages = getSupportChatMessages($chat['id'], false); // без внутренних заметок агентов
$pageTitle = 'Поддержка';
include __DIR__ . '/../templates/header.php';
?>
<div class="chat-page">
<a href="/profile" class="btn btn-secondary" style="margin-bottom:16px;">← Назад</a>
<div class="chat-card">
  <div class="chat-head"><div><strong>🆘 Поддержка Galbraith</strong><div class="chat-subtitle"><?= $chat['status'] === 'closed' ? 'Обращение закрыто — можете написать снова' : 'Обычно отвечаем в течение дня' ?></div></div><span class="chat-live">● онлайн-обновление</span></div>
  <div id="supportChatBox" class="seller-chat-box" data-chat="<?= $chat['id'] ?>">
    <?php if (!$messages): ?>
        <p style="text-align:center;color:var(--g-text-secondary);padding:20px;">Опишите проблему — мы ответим прямо здесь.</p>
    <?php endif; ?>
    <?php foreach ($messages as $m): $mine = !$m['is_agent']; ?>
      <div class="seller-chat-row <?= $mine ? 'mine' : '' ?>">
        <div class="seller-chat-bubble <?= $m['is_agent'] ? 'admin-message' : '' ?>">
          <div class="seller-chat-meta"><strong><?= $m['is_agent'] ? 'Поддержка' : e($m['sender_name'] ?? 'Вы') ?></strong><?php if ($m['is_agent']): ?><span class="admin-tag">ПОДДЕРЖКА</span><?php endif; ?><span><?= e(date('H:i', strtotime($m['created_at']))) ?></span></div>
          <?php if ($m['message'] !== ''): ?><div class="seller-chat-text"><?= e($m['message']) ?></div><?php endif; ?>
          <?php if (!empty($m['attachment_path'])): ?><a class="chat-image-link" href="<?= e(UPLOAD_URL . $m['attachment_path']) ?>" target="_blank"><img src="<?= e(UPLOAD_URL . $m['attachment_path']) ?>" alt="Изображение"></a><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <form id="supportChatForm" class="seller-chat-form" enctype="multipart/form-data">
    <textarea name="message" id="supportChatText" class="input" rows="2" placeholder="Опишите проблему... (можно вставить картинку из буфера обмена — Ctrl+V)"></textarea>
    <div id="chatPastePreview" style="display:none;margin:8px 0;position:relative;width:fit-content;"><img style="max-height:90px;border-radius:8px;display:block;"><button type="button" onclick="clearChatPaste()" style="position:absolute;top:-8px;right:-8px;width:22px;height:22px;border-radius:50%;background:var(--g-danger);color:#fff;border:none;cursor:pointer;font-size:13px;line-height:1;">✕</button></div>
    <div class="chat-form-bottom"><label class="chat-file">📎 <input type="file" name="attachment" id="chatFileInput" accept="image/*"> <span>Изображение</span></label><button class="btn btn-primary" type="submit">Отправить</button></div>
  </form>
</div>
</div>
<script>
(function(){
 const box=document.getElementById('supportChatBox'), form=document.getElementById('supportChatForm');
 let last='';
 let lastCount=null;
 function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
 function render(rows){const html=rows.map(m=>{const mine=!m.is_agent;return `<div class="seller-chat-row ${mine?'mine':''}"><div class="seller-chat-bubble ${m.is_agent?'admin-message':''}"><div class="seller-chat-meta"><strong>${m.is_agent?'Поддержка':esc(m.sender_name||'Вы')}</strong>${m.is_agent?'<span class="admin-tag">ПОДДЕРЖКА</span>':''}<span>${esc(m.time||'')}</span></div>${m.message?`<div class="seller-chat-text">${esc(m.message)}</div>`:''}${m.attachment_path?`<a class="chat-image-link" href="${esc(m.attachment_url)}" target="_blank"><img src="${esc(m.attachment_url)}" alt="Изображение"></a>`:''}</div></div>`}).join('')||'<p style="text-align:center;color:var(--g-text-secondary);padding:20px;">Опишите проблему — мы ответим прямо здесь.</p>'; if(html!==last){const nearBottom=box.scrollHeight-box.scrollTop-box.clientHeight<100;box.innerHTML=html;last=html;if(nearBottom)box.scrollTop=box.scrollHeight; if(lastCount!==null&&rows.length>lastCount){const newest=rows[rows.length-1];if(newest&&newest.is_agent&&window.galbraithPlaySound)window.galbraithPlaySound('chime')}lastCount=rows.length}}
 async function poll(){try{const r=await fetch(`/api/support-chat?chat_id=${box.dataset.chat}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});const j=await r.json();if(j.success)render(j.messages)}catch(e){}}
 form.addEventListener('submit',async e=>{e.preventDefault();const fd=new FormData(form);fd.append('chat_id',box.dataset.chat);const btn=form.querySelector('button');btn.disabled=true;try{const r=await fetch('/support/send',{method:'POST',body:fd});const j=await r.json();if(j.success){form.reset();if(window.clearChatPaste)clearChatPaste();await poll();box.scrollTop=box.scrollHeight}else alert(j.error||'Не удалось отправить')}finally{btn.disabled=false}});
 box.scrollTop=box.scrollHeight; poll(); setInterval(poll,2000);

 const fileInput=document.getElementById('chatFileInput'), pastePreview=document.getElementById('chatPastePreview'), pasteImg=pastePreview.querySelector('img'), textarea=document.getElementById('supportChatText');
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
<?php include __DIR__ . '/../templates/footer.php'; ?>
