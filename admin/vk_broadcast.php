<?php
$pageTitle = 'Рассылка в VK';
$result = $_SESSION['vk_broadcast_result'] ?? null;
unset($_SESSION['vk_broadcast_result']);
$db = getDB();
$contactsCount = (int)$db->query("SELECT COUNT(*) FROM vk_contacts WHERE CAST(peer_id AS INTEGER) > 0")->fetchColumn();
$linkedCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE vk_bot_peer_id IS NOT NULL AND vk_bot_peer_id <> ''")->fetchColumn();
$broadcasts = vkBroadcastList(30);
$activeId = (int)($_GET['broadcast_id'] ?? 0);
$csrf = csrfToken();
$statusMap = [
    'queued' => ['⏳', 'В очереди'],
    'running' => ['▶️', 'Идёт'],
    'paused' => ['⏸️', 'Приостановлена'],
    'finished' => ['✅', 'Завершена'],
    'draft' => ['📝', 'Черновик'],
];
include __DIR__ . '/../templates/admin_header.php';
?>

<div style="max-width:1100px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
        <div>
            <h2 style="font-size:22px;font-weight:700;margin:0 0 7px;">📢 Рассылка в VK</h2>
            <p style="font-size:13px;color:var(--g-text-secondary);margin:0;line-height:1.6;">
                Получатели — <strong style="color:var(--g-text);">все пользователи, чьи диалоги доступны сообществу VK</strong>, а не только аккаунты сайта с привязанным VK.
                Сейчас в базе: <strong style="color:var(--g-text);" id="contactsCount"><?= $contactsCount ?></strong> диалогов; привязано к сайту: <strong><?= $linkedCount ?></strong>.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <form method="POST" action="/admin/vk-broadcast/sync">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-secondary">🔄 Синхронизировать диалоги VK</button>
            </form>
            <a href="/admin/settings" class="btn btn-secondary">⚙️ Настройки VK</a>
        </div>
    </div>

    <?php if ($result): ?>
        <div style="margin-bottom:18px;padding:14px 16px;border-radius:12px;border:1px solid <?= !empty($result['ok']) ? '#2d8a57' : '#a33' ?>;background:<?= !empty($result['ok']) ? 'rgba(45,138,87,.10)' : 'rgba(163,51,51,.10)' ?>;">
            <?php if (!empty($result['ok'])): ?>
                <strong>✅ <?= e($result['message'] ?? 'Готово') ?></strong>
                <?php if (isset($result['total'])): ?><span style="color:var(--g-text-secondary);"> Получателей: <?= (int)$result['total'] ?>.</span><?php endif; ?>
            <?php else: ?>
                <strong>❌ Не удалось выполнить действие.</strong>
                <span style="color:var(--g-text-secondary);"><?= e($result['error'] ?? 'Неизвестная ошибка') ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!vkBotConfigured() || setting('vk_bot_enabled','0') !== '1'): ?>
        <div style="padding:18px;border:1px solid var(--g-border);border-radius:14px;background:var(--g-card);margin-bottom:18px;">
            <strong>VK-бот пока не подключён.</strong>
            <div style="font-size:13px;color:var(--g-text-secondary);margin-top:6px;">Сначала подключите сообщество в настройках VK-бота.</div>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/vk-broadcast/create" id="vkBroadcastForm" style="display:grid;gap:16px;margin-bottom:28px;">
        <?= csrfField() ?>
        <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:14px;padding:18px;">
            <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:7px;">Заголовок</label>
            <input type="text" name="title" class="input" maxlength="120" required placeholder="Например: 🔥 Новая сборка уже на сайте">
        </div>
        <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:14px;padding:18px;">
            <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:7px;">Текст сообщения</label>
            <textarea name="message" class="input" rows="8" maxlength="4000" required placeholder="Напишите содержание рассылки..."></textarea>
            <div style="font-size:12px;color:var(--g-text-secondary);margin-top:7px;">Абзацы и переносы строк сохранятся. Эмодзи можно использовать прямо в тексте.</div>
        </div>
        <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:14px;padding:18px;">
            <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:7px;">Ссылка (необязательно)</label>
            <input type="url" name="url" class="input" placeholder="<?= e(rtrim(SITE_URL,'/')) ?>/builds/...">
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Создать рассылку для всех <?= $contactsCount ?> доступных диалогов VK?')">📢 Создать и запустить</button>
            <span style="font-size:12px;color:var(--g-text-secondary);">Отправка идёт пакетами. Её можно поставить на паузу и продолжить позже.</span>
        </div>
    </form>

    <div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:14px;overflow:hidden;">
        <div style="padding:17px 18px;border-bottom:1px solid var(--g-border);font-weight:700;">📊 История рассылок</div>
        <?php if (!$broadcasts): ?>
            <div style="padding:25px;color:var(--g-text-secondary);font-size:13px;">Рассылок пока не было.</div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;min-width:850px;">
                    <thead><tr style="color:var(--g-text-secondary);font-size:12px;text-align:left;border-bottom:1px solid var(--g-border);">
                        <th style="padding:12px 16px;">Рассылка</th><th style="padding:12px 16px;">Статус</th><th style="padding:12px 16px;">Получатели</th><th style="padding:12px 16px;">Попытки</th><th style="padding:12px 16px;">Отправлено</th><th style="padding:12px 16px;">Ошибки</th><th style="padding:12px 16px;">Действия</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($broadcasts as $b): $st = $statusMap[$b['status']] ?? ['•', $b['status']]; ?>
                        <tr id="broadcast-row-<?= (int)$b['id'] ?>" style="border-bottom:1px solid var(--g-border);vertical-align:top;">
                            <td style="padding:14px 16px;"><strong><?= e($b['title']) ?></strong><div style="font-size:11px;color:var(--g-text-secondary);margin-top:5px;">#<?= (int)$b['id'] ?> · <?= e($b['created_at']) ?></div></td>
                            <td style="padding:14px 16px;" class="b-status"><span><?= $st[0] ?> <?= e($st[1]) ?></span></td>
                            <td style="padding:14px 16px;" class="b-total"><?= (int)$b['total'] ?></td>
                            <td style="padding:14px 16px;" class="b-attempts"><?= (int)$b['attempts'] ?></td>
                            <td style="padding:14px 16px;color:#39b86b;font-weight:600;" class="b-sent"><?= (int)$b['sent'] ?></td>
                            <td style="padding:14px 16px;color:#d96b6b;" class="b-failed"><?= (int)$b['failed'] ?></td>
                            <td style="padding:14px 16px;white-space:nowrap;">
                                <?php if (in_array($b['status'], ['queued','running','paused'], true)): ?>
                                    <button type="button" class="btn btn-secondary b-action" data-id="<?= (int)$b['id'] ?>" data-status="<?= e($b['status']) ?>" style="font-size:12px;">...</button>
                                <?php else: ?>
                                    <span style="font-size:12px;color:var(--g-text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function(){
    const csrf = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
    const activeId = <?= $activeId ?>;
    const statusText = {queued:'⏳ В очереди', running:'▶️ Идёт', paused:'⏸️ Приостановлена', finished:'✅ Завершена'};
    const timers = {};
    function post(path, data){
        data = Object.assign({csrf_token: csrf}, data || {});
        return fetch(path, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body:new URLSearchParams(data)}).then(r=>r.json());
    }
    function updateRow(b){
        const row=document.getElementById('broadcast-row-'+b.id); if(!row) return;
        row.querySelector('.b-status').innerHTML=statusText[b.status] || b.status;
        row.querySelector('.b-total').textContent=b.total;
        row.querySelector('.b-attempts').textContent=b.attempts;
        row.querySelector('.b-sent').textContent=b.sent;
        row.querySelector('.b-failed').textContent=b.failed;
        const btn=row.querySelector('.b-action');
        if(btn){ btn.dataset.status=b.status; btn.textContent=b.status==='paused'?'▶️ Продолжить':'⏸️ Пауза'; }
    }
    function process(id){
        if(timers[id]) return;
        timers[id]=true;
        post('/admin/vk-broadcast/process',{id:id}).then(data=>{
            timers[id]=false;
            if(!data.ok){ alert(data.error || 'Ошибка обработки рассылки'); return; }
            if(data.broadcast) updateRow(data.broadcast);
            if(data.finished || (data.broadcast && data.broadcast.status==='finished')) return;
            if(data.paused || (data.broadcast && data.broadcast.status==='paused')) return;
            setTimeout(()=>process(id), 350);
        }).catch(()=>{ timers[id]=false; alert('Не удалось связаться с сервером. Рассылку можно продолжить кнопкой.'); });
    }
    document.querySelectorAll('.b-action').forEach(btn=>btn.addEventListener('click',function(){
        const id=this.dataset.id, status=this.dataset.status;
        if(status==='paused'){
            post('/admin/vk-broadcast/resume',{id:id}).then(d=>{ if(d.broadcast){updateRow(d.broadcast); process(id);} });
        } else {
            post('/admin/vk-broadcast/pause',{id:id}).then(d=>{ if(d.broadcast) updateRow(d.broadcast); });
        }
    }));
    if(activeId) process(activeId);
})();
</script>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
