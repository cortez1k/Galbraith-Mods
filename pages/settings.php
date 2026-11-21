<?php
$user = currentUser();
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$hasRealEmail = strpos($user['email'], '@google.placeholder') === false && strpos($user['email'], '@vk.placeholder') === false;
$has2fa = !empty($user['twofa_enabled']) && !empty($user['totp_secret']);
$pageTitle = 'Настройки';
include __DIR__ . '/../templates/header.php';
?>
<div style="max-width:760px;margin:0 auto;">
    <a href="/profile" class="btn btn-secondary" style="margin-bottom:16px;">← Личный кабинет</a>
    <h1 style="font-size:22px;font-weight:700;margin-bottom:20px;">⚙️ Настройки</h1>

    <?php if ($flash): ?><div style="background:rgba(232,28,90,.1);border:1px solid rgba(232,28,90,.3);border-radius:var(--g-radius-sm);padding:12px 16px;margin-bottom:16px;font-size:14px"><?= e($flash) ?></div><?php endif; ?>

    <div class="card" style="padding:20px;margin-bottom:20px"><h3 style="font-size:16px;font-weight:600;margin-bottom:12px">Профиль</h3><form method="POST" action="/profile/update" enctype="multipart/form-data" style="display:grid;gap:12px;max-width:480px"><div><label style="font-size:13px;color:var(--g-text-secondary)">Никнейм</label><input name="name" class="input" value="<?= e($user['name']) ?>" maxlength="100" required></div><div><label style="font-size:13px;color:var(--g-text-secondary)">Аватар</label><input type="file" name="avatar" class="input" accept="image/jpeg,image/png,image/webp"></div><button class="btn btn-primary" style="width:fit-content">Сохранить профиль</button></form></div>

    <div class="card" style="padding:20px;margin-bottom:20px"><h3 style="font-size:16px;font-weight:600;margin-bottom:8px">🔐 Двухфакторная аутентификация</h3><p style="font-size:13px;color:var(--g-text-secondary);line-height:1.6">Дополнительно к коду из email можно защитить аккаунт кодом из Google Authenticator или другого TOTP-приложения.</p>
    <?php if ($has2fa): ?>
        <div style="margin:14px 0;display:flex;align-items:center;gap:8px;color:var(--g-success);font-weight:600;">✅ Включена и подтверждена</div>
        <form method="POST" action="/profile/2fa" style="display:flex;gap:8px"><input type="hidden" name="action" value="disable"><input name="code" class="input" placeholder="Код 2FA" maxlength="6" required><button class="btn btn-danger">Отключить</button></form>
    <?php elseif (!empty($user['totp_pending']) && !empty($user['totp_secret'])): ?>
        <div style="margin:14px 0;text-align:center"><img src="<?= e(getTOTPQRUrl($user['totp_secret'], 'Galbraith: ' . $user['name'])) ?>" style="width:180px;height:180px;border-radius:8px"><div style="font-family:var(--g-font-mono);margin-top:8px;letter-spacing:2px"><?= e($user['totp_secret']) ?></div></div>
        <p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:10px;">Отсканируйте QR в приложении-аутентификаторе (Google Authenticator, Authy и т.п.), затем введите сгенерированный код ниже — только после этого 2FA станет активной.</p>
        <form method="POST" action="/profile/2fa" style="display:flex;gap:8px;margin-bottom:8px;"><input type="hidden" name="action" value="confirm"><input name="code" class="input" placeholder="Код из приложения" maxlength="6" required autofocus><button class="btn btn-primary">Подтвердить</button></form>
        <form method="POST" action="/profile/2fa"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-secondary btn-sm">Отменить подключение</button></form>
    <?php else: ?>
        <form method="POST" action="/profile/2fa"><input type="hidden" name="action" value="start"><button class="btn btn-primary">Включить 2FA</button></form>
    <?php endif; ?>
    </div>

    <div class="card" style="padding:20px;margin-bottom:20px" id="notifSettingsCard">
        <h3 style="font-size:16px;font-weight:600;margin-bottom:8px">🔔 Уведомления в браузере и звук</h3>
        <p style="font-size:13px;color:var(--g-text-secondary);line-height:1.6;margin-bottom:14px;">Настройки хранятся в этом браузере — на других устройствах их можно задать отдельно.</p>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid var(--g-border);">
            <div>
                <div style="font-weight:600;font-size:14px;">Уведомления браузера</div>
                <div style="font-size:12px;color:var(--g-text-secondary);margin-top:2px;">Всплывающее системное уведомление, даже если вкладка сайта свёрнута</div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="notifPushBtn">Разрешить</button>
        </div>

        <div style="padding:14px 0;">
            <div style="font-weight:600;font-size:14px;margin-bottom:10px;">Звук уведомлений</div>
            <div style="display:grid;gap:8px;max-width:340px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;"><input type="radio" name="notifSound" value="off"> Выключен</label>
                <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;"><input type="radio" name="notifSound" value="soft"> Мягкий</label>
                <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;"><input type="radio" name="notifSound" value="chime"> Мелодичный</label>
                <label style="display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer;"><input type="radio" name="notifSound" value="alert"> Тревожный</label>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="notifSoundTestBtn" style="margin-top:12px;">▶️ Проверить звук</button>
        </div>
        <div id="notifSettingsStatus" style="font-size:12px;color:var(--g-success);margin-top:4px;"></div>
    </div>
    <script>
    (function(){
        function ready(fn){ if (window.galbraithNotifSettings) fn(); else setTimeout(function(){ ready(fn); }, 50); }
        ready(function(){
            const s = window.galbraithNotifSettings.get();
            document.querySelectorAll('input[name="notifSound"]').forEach(function(r){ r.checked = (r.value === s.sound); r.addEventListener('change', function(){ const cur = window.galbraithNotifSettings.get(); cur.sound = r.value; window.galbraithNotifSettings.save(cur); showStatus('Сохранено'); }); });
            const pushBtn = document.getElementById('notifPushBtn');
            function refreshPushBtn(){
                if (typeof Notification === 'undefined') { pushBtn.textContent = 'Не поддерживается'; pushBtn.disabled = true; return; }
                if (Notification.permission === 'granted') { pushBtn.textContent = '✅ Разрешено'; pushBtn.classList.add('btn-primary'); }
                else if (Notification.permission === 'denied') { pushBtn.textContent = 'Заблокировано в браузере'; pushBtn.disabled = true; }
                else { pushBtn.textContent = 'Разрешить'; }
            }
            refreshPushBtn();
            pushBtn.addEventListener('click', function(){ window.galbraithRequestPushPermission(function(granted, err){ refreshPushBtn(); showStatus(granted ? 'Уведомления включены' : (err || 'Не удалось включить')); }); });
            document.getElementById('notifSoundTestBtn').addEventListener('click', function(){ const cur = window.galbraithNotifSettings.get(); window.galbraithPlaySound(cur.sound); });
            function showStatus(text){ const el = document.getElementById('notifSettingsStatus'); el.textContent = text; setTimeout(function(){ el.textContent = ''; }, 2000); }
        });
    })();
    </script>

    <div class="card" style="padding:20px;margin-bottom:20px"><h3 style="font-size:16px;font-weight:600;margin-bottom:8px">💬 VK-уведомления</h3><p style="font-size:13px;color:var(--g-text-secondary);line-height:1.6">Дублируем уведомления сайта личными сообщениями в VK — ничего не пропустите, даже если не заходите на сайт.</p>
    <?php if (!vkBotConfigured() || setting('vk_bot_enabled','0') !== '1'): ?>
        <p style="font-size:13px;color:var(--g-text-secondary);margin-top:10px;">VK-бот пока не настроен администрацией сайта.</p>
    <?php elseif (!empty($user['vk_bot_peer_id'])): ?>
        <div style="margin-top:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;"><span style="color:var(--g-success);font-weight:600;">✅ Подключено</span><form method="POST" action="/profile/vk/disconnect"><button type="submit" class="btn btn-secondary btn-sm">Отключить</button></form></div>
        <form method="POST" action="/profile/vk/login-toggle" style="margin-top:10px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--g-text-secondary);cursor:pointer;">
                <input type="checkbox" name="enabled" value="1" onchange="this.form.submit()" <?= !empty($user['login_code_via_vk']) ? 'checked' : '' ?>>
                Присылать код входа в VK (в дополнение к email)
            </label>
        </form>
    <?php else: ?>
        <?php if (!empty($user['vk_bot_link_code'])): ?>
            <div style="margin-top:12px;padding:12px;border-radius:10px;background:var(--g-bg-main);font-size:13px;line-height:1.6;">
                Откройте сообщения сообщества VK и отправьте боту этот код:
                <div style="font-size:18px;font-weight:700;letter-spacing:1px;margin-top:6px;"><?= e($user['vk_bot_link_code']) ?></div>
                <?php $vkGroupId = setting('vk_group_id',''); if ($vkGroupId): ?>
                    <a href="https://vk.me/club<?= e($vkGroupId) ?>" target="_blank" class="btn btn-primary" style="margin-top:10px;display:inline-block;">Открыть сообщения VK</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="POST" action="/profile/vk/connect" style="margin-top:12px;"><button type="submit" class="btn btn-primary">Подключить VK</button></form>
        <?php endif; ?>
    <?php endif; ?>
    </div>

    <?php if (!$hasRealEmail): ?>
    <div class="card" style="padding:20px;margin-bottom:20px"><h3 style="font-size:15px;font-weight:600;margin-bottom:10px">Привязать email и пароль</h3><form method="POST" action="/profile/link-email" style="display:grid;gap:10px;max-width:360px"><input type="email" name="email" class="input" placeholder="Email" required><input type="password" name="password" class="input" placeholder="Пароль" required minlength="6"><input type="password" name="password_confirm" class="input" placeholder="Повторите пароль" required minlength="6"><button class="btn btn-primary" style="width:fit-content">Привязать</button></form></div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
