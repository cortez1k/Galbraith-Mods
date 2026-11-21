/**
 * Единая система живых уведомлений сайта:
 * - опрашивает /api/notifications, показывает всплывающий тост на новые события
 * - звук через Web Audio API (никаких аудиофайлов — генерируем тон на лету)
 * - системное уведомление браузера (Notification API), если разрешено
 * Настройки — per-browser в localStorage (см. философию "сессия привязана
 * к браузеру": звук/пуши — тоже локальное, per-device предпочтение).
 */
(function () {
    'use strict';
    if (!document.body || !document.body.dataset || document.body.dataset.loggedIn !== '1') return;

    const SETTINGS_KEY = 'galbraith_notif_settings';
    const LAST_SEEN_KEY = 'galbraith_last_notif_id';
    const POLL_MS = 15000;

    function getSettings() {
        try {
            return Object.assign({ sound: 'soft', browserPush: false, volume: 0.5 }, JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}'));
        } catch (e) { return { sound: 'soft', browserPush: false, volume: 0.5 }; }
    }
    function saveSettings(s) { localStorage.setItem(SETTINGS_KEY, JSON.stringify(s)); }
    window.galbraithNotifSettings = { get: getSettings, save: saveSettings };

    // ---------- Звук: синтезируем тон через Web Audio API, файлы не нужны ----------
    let audioCtx = null;
    function ctx() { if (!audioCtx) { try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { return null; } } return audioCtx; }
    function tone(freq, start, dur, vol) {
        const c = ctx(); if (!c) return;
        const osc = c.createOscillator(); const gain = c.createGain();
        osc.type = 'sine'; osc.frequency.value = freq;
        gain.gain.setValueAtTime(0, c.currentTime + start);
        gain.gain.linearRampToValueAtTime(vol, c.currentTime + start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, c.currentTime + start + dur);
        osc.connect(gain); gain.connect(c.destination);
        osc.start(c.currentTime + start); osc.stop(c.currentTime + start + dur + 0.05);
    }
    const SOUND_PRESETS = {
        off: [],
        soft: [[660, 0, 0.16]],
        chime: [[523, 0, 0.14], [784, 0.1, 0.22]],
        alert: [[880, 0, 0.09], [880, 0.14, 0.09], [880, 0.28, 0.14]],
    };
    function playSound(name) {
        const s = getSettings();
        const preset = SOUND_PRESETS[name || s.sound];
        if (!preset || !preset.length) return;
        preset.forEach(([freq, start, dur]) => tone(freq, start, dur, s.volume));
    }
    window.galbraithPlaySound = playSound;

    // ---------- Тост ----------
    function ensureToastHost() {
        let host = document.getElementById('galbraithToastHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'galbraithToastHost';
            host.className = 'gtoast-host';
            document.body.appendChild(host);
        }
        return host;
    }
    function showToast(item) {
        const host = ensureToastHost();
        const el = document.createElement('a');
        el.href = '/notifications/' + item.id;
        el.className = 'gtoast';
        el.innerHTML = `<div class="gtoast-icon">${item.icon || '🔔'}</div><div class="gtoast-body"><div class="gtoast-title"></div><div class="gtoast-text"></div></div><button class="gtoast-close" type="button" aria-label="Закрыть">✕</button>`;
        el.querySelector('.gtoast-title').textContent = item.title || 'Уведомление';
        el.querySelector('.gtoast-text').textContent = item.text || '';
        host.appendChild(el);
        requestAnimationFrame(() => el.classList.add('gtoast-in'));
        let timer = setTimeout(() => dismiss(), 6000);
        function dismiss() { el.classList.remove('gtoast-in'); el.classList.add('gtoast-out'); setTimeout(() => el.remove(), 250); }
        el.addEventListener('mouseenter', () => clearTimeout(timer));
        el.addEventListener('mouseleave', () => { timer = setTimeout(dismiss, 2500); });
        el.querySelector('.gtoast-close').addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); clearTimeout(timer); dismiss(); });
    }

    // ---------- Системное уведомление браузера ----------
    function showBrowserNotification(item) {
        const s = getSettings();
        if (!s.browserPush || typeof Notification === 'undefined' || Notification.permission !== 'granted') return;
        if (document.visibilityState === 'visible') return; // не дублируем, пока вкладка активна — тоста достаточно
        try {
            const n = new Notification(item.title || 'Galbraith', { body: item.text || '', icon: '/assets/uploads/logos/favicon.ico', tag: 'galbraith-' + item.id });
            n.onclick = function () { window.focus(); window.location.href = '/notifications/' + item.id; };
        } catch (e) {}
    }

    window.galbraithRequestPushPermission = function (cb) {
        if (typeof Notification === 'undefined') { cb && cb(false, 'Браузер не поддерживает уведомления'); return; }
        Notification.requestPermission().then(function (perm) {
            const granted = perm === 'granted';
            const s = getSettings(); s.browserPush = granted; saveSettings(s);
            cb && cb(granted, granted ? null : 'Разрешение не выдано');
        });
    };

    // ---------- Живой опрос ----------
    // Время уведомлений отображаем в часовом поясе устройства пользователя.
    // Сервер передаёт UTC, поэтому часовой пояс/переходы на летнее время берёт сам браузер.
    function localizeNotificationTimes() {
        document.querySelectorAll('.notification-time-local').forEach(function (el) {
            const raw = el.getAttribute('data-utc');
            if (!raw) return;
            const date = new Date(raw);
            if (Number.isNaN(date.getTime())) return;
            const diff = Math.floor((Date.now() - date.getTime()) / 1000);
            if (diff < 60) { el.textContent = 'только что'; return; }
            if (diff < 3600) { el.textContent = Math.floor(diff / 60) + ' мин. назад'; return; }
            if (diff < 86400) { el.textContent = Math.floor(diff / 3600) + ' ч. назад'; return; }
            if (diff < 604800) { el.textContent = Math.floor(diff / 86400) + ' дн. назад'; return; }
            el.textContent = new Intl.DateTimeFormat(undefined, {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            }).format(date);
        });
    }

    function updateBadge(count) {
        const badge = document.getElementById('notificationBadge');
        const trigger = document.querySelector('.header-popup-trigger');
        if (count > 0) {
            if (badge) { badge.textContent = count > 99 ? '99+' : count; }
            else if (trigger) { const b = document.createElement('span'); b.className = 'header-badge'; b.id = 'notificationBadge'; b.textContent = count > 99 ? '99+' : count; trigger.appendChild(b); }
        } else if (badge) { badge.remove(); }
    }

    async function poll() {
        try {
            const r = await fetch('/api/notifications', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            if (!j.success) return;
            updateBadge(j.unread);
            let lastSeen = parseInt(localStorage.getItem(LAST_SEEN_KEY) || '0', 10);
            const fresh = j.items.filter(it => it.id > lastSeen && !it.read);
            if (fresh.length) {
                fresh.slice(0, 3).forEach(it => { showToast({ id: it.id, title: it.title, text: it.text, icon: it.icon }); showBrowserNotification({ id: it.id, title: it.title, text: it.text }); });
                if (fresh.length) playSound();
                const maxId = Math.max(...j.items.map(it => it.id), lastSeen);
                localStorage.setItem(LAST_SEEN_KEY, String(maxId));
            } else if (!lastSeen && j.items.length) {
                // первый визит в этом браузере — просто запоминаем текущий максимум, не спамим тостами за всю историю
                localStorage.setItem(LAST_SEEN_KEY, String(Math.max(...j.items.map(it => it.id))));
            }
        } catch (e) {}
    }

    localizeNotificationTimes();
    poll();
    setInterval(poll, POLL_MS);
    setInterval(localizeNotificationTimes, 60000);
})();
