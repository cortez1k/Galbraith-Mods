<?php
$chatId = (int)($_GET['id'] ?? 0);
$chat = getSupportChatById($chatId);
if (!$chat) redirect('/support/chats/new');
$stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$chat['user_id']]);
$chatUser = $stmt->fetch();
$messages = getSupportChatMessages($chatId, true);
$agentId = $_SESSION['admin_id'];
$quickReplies = getQuickReplies();
$isMine = (int)$chat['assigned_agent_id'] === (int)$agentId;

$pageTitle = 'Чат: ' . ($chatUser['name'] ?? 'Пользователь');
include __DIR__ . '/../templates/support_header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
    <div>
        <h2 style="font-size:18px;font-weight:700;margin-bottom:4px;">🙋 <?= e($chatUser['name'] ?? 'Пользователь') ?></h2>
        <div style="font-size:13px;color:var(--g-text-secondary);"><?= e($chatUser['email'] ?? '') ?> · <a href="/admin/users/<?= (int)$chat['user_id'] ?>" style="color:var(--g-accent-light);">карточка пользователя</a></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if (!$chat['assigned_agent_id']): ?>
            <form method="POST" action="/support/chats/<?= $chatId ?>/claim"><button type="submit" class="btn btn-primary btn-sm">Взять в работу</button></form>
        <?php elseif ($isMine): ?>
            <span style="align-self:center;font-size:13px;color:var(--g-success);">✅ У вас в работе</span>
        <?php else: ?>
            <span style="align-self:center;font-size:13px;color:var(--g-text-secondary);">В работе у другого агента</span>
        <?php endif; ?>
        <?php if ($chat['status'] !== 'closed'): ?>
            <form method="POST" action="/support/chats/<?= $chatId ?>/close" onsubmit="return confirm('Закрыть обращение?')"><button type="submit" class="btn btn-secondary btn-sm">Закрыть</button></form>
        <?php endif; ?>
    </div>
</div>

<?php if ($quickReplies): ?>
<div style="max-width:800px;margin-bottom:12px;">
    <div style="font-size:12px;color:var(--g-text-secondary);margin-bottom:8px;">⚡ Быстрые ответы — нажмите, чтобы вставить в поле ниже (форматирование: **жирный**, *курсив*, `код`):</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php foreach ($quickReplies as $qr): ?>
            <button type="button" class="btn btn-secondary btn-sm qr-btn" data-content="<?= e($qr['content']) ?>" title="<?= e($qr['shortcut'] ?: $qr['title']) ?>"><?= e($qr['title']) ?></button>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<div class="chat-card" style="max-width:800px;">
    <div id="supportAgentChatBox" class="seller-chat-box" data-chat="<?= $chatId ?>">
        <?php foreach ($messages as $m): $mine = !empty($m['is_agent']); ?>
            <?php if ($m['is_internal_note']): ?>
                <div class="seller-chat-row" style="justify-content:center;">
                    <div class="seller-chat-bubble" style="max-width:90%;background:var(--g-warning-dim);border:1px solid rgba(245,166,35,0.3);">
                        <div class="seller-chat-meta"><strong>📝 Заметка — <?= e($m['sender_name'] ?? 'Агент') ?></strong><span>только для персонала</span><span><?= e(date('H:i', strtotime($m['created_at']))) ?></span></div>
                        <div class="seller-chat-text"><?= renderSupportMessage($m['message']) ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="seller-chat-row <?= $mine ? 'mine' : '' ?>">
                    <div class="seller-chat-bubble <?= $mine ? 'admin-message' : '' ?>">
                        <div class="seller-chat-meta"><strong><?= $mine ? e($m['sender_name'] ?? 'Агент') : e($chatUser['name'] ?? 'Пользователь') ?></strong><span><?= e(date('H:i', strtotime($m['created_at']))) ?></span></div>
                        <?php if ($m['message'] !== ''): ?><div class="seller-chat-text"><?= renderSupportMessage($m['message']) ?></div><?php endif; ?>
                        <?php if (!empty($m['attachment_path'])): ?><a class="chat-image-link" href="<?= e(UPLOAD_URL . $m['attachment_path']) ?>" target="_blank"><img src="<?= e(UPLOAD_URL . $m['attachment_path']) ?>" alt="Изображение"></a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <form method="POST" action="/support/chats/<?= $chatId ?>/reply" enctype="multipart/form-data" class="seller-chat-form">
        <textarea name="message" class="input" rows="2" placeholder="Ответ пользователю... (можно **жирный**, *курсив*, `код`)"></textarea>
        <div class="chat-form-bottom" style="justify-content:space-between;">
            <label class="chat-file">📎 <input type="file" name="attachment" accept="image/*"> <span>Изображение</span></label>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-secondary" type="submit" name="is_note" value="1">📝 Добавить заметку (не видна пользователю)</button>
                <button class="btn btn-primary" type="submit">Ответить пользователю</button>
            </div>
        </div>
    </form>
</div>
<script>
(function(){
 const box=document.getElementById('supportAgentChatBox');
 const ta=document.querySelector('.chat-card textarea[name="message"]');
 document.querySelectorAll('.qr-btn').forEach(b=>b.addEventListener('click',()=>{ta.value=b.dataset.content;ta.focus();}));
 const chatUserName=<?= json_encode($chatUser['name'] ?? 'Пользователь', JSON_UNESCAPED_UNICODE) ?>;
 let last=box.innerHTML;
 let lastCount=null;
 function esc(s){const d=document.createElement('div');d.textContent=s??'';return d.innerHTML}
 function rich(s){let h=esc(s);h=h.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>');h=h.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g,'$1<em>$2</em>');h=h.replace(/`([^`\n]+)`/g,'<code>$1</code>');return h.replace(/\n/g,'<br>')}
 function render(rows){
   const html=rows.map(m=>{
     if(m.is_internal_note){
       return `<div class="seller-chat-row" style="justify-content:center;"><div class="seller-chat-bubble" style="max-width:90%;background:var(--g-warning-dim);border:1px solid rgba(245,166,35,0.3);"><div class="seller-chat-meta"><strong>📝 Заметка — ${esc(m.sender_name||'Агент')}</strong><span>только для персонала</span><span>${esc(m.time||'')}</span></div><div class="seller-chat-text">${rich(m.message)}</div></div></div>`;
     }
     const mine=!!m.is_agent;
     return `<div class="seller-chat-row ${mine?'mine':''}"><div class="seller-chat-bubble ${mine?'admin-message':''}"><div class="seller-chat-meta"><strong>${mine?esc(m.sender_name||'Агент'):esc(chatUserName)}</strong><span>${esc(m.time||'')}</span></div>${m.message?`<div class="seller-chat-text">${rich(m.message)}</div>`:''}${m.attachment_path?`<a class="chat-image-link" href="${esc(m.attachment_url)}" target="_blank"><img src="${esc(m.attachment_url)}" alt="Изображение"></a>`:''}</div></div>`;
   }).join('');
   if(html!==last){const nearBottom=box.scrollHeight-box.scrollTop-box.clientHeight<100;box.innerHTML=html;last=html;if(nearBottom)box.scrollTop=box.scrollHeight; if(lastCount!==null&&rows.length>lastCount){const newest=rows[rows.length-1];if(newest&&!newest.is_agent&&window.galbraithPlaySound)window.galbraithPlaySound('alert')}lastCount=rows.length}
 }
 async function poll(){try{const r=await fetch(`/api/support-chat?chat_id=${box.dataset.chat}`,{headers:{'X-Requested-With':'XMLHttpRequest'}});const j=await r.json();if(j.success)render(j.messages)}catch(e){}}
 box.scrollTop=box.scrollHeight;
 setInterval(poll,3000);
})();
</script>
<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
