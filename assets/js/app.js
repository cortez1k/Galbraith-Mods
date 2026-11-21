(function() {
    // Theme persistence
    const theme = JSON.parse(localStorage.getItem('galbraith_theme') || '{}');
    if (theme.light) document.body.classList.add('light-mode');
    if (theme.accent) updateAccent(theme.accent, false);
    if (theme.bg) updateBg(theme.bg, false);
    if (theme.card) updateCard(theme.card, false);

    // Init pickers
    const accentPicker = document.getElementById('accentPicker');
    const bgPicker = document.getElementById('bgPicker');
    const cardPicker = document.getElementById('cardPicker');
    if (accentPicker) accentPicker.value = theme.accent || '#E81C5A';
    if (bgPicker) bgPicker.value = theme.bg || '#0E0E0E';
    if (cardPicker) cardPicker.value = theme.card || '#0F0F0F';
    updateToggleDot();
})();

function toggleThemeModal() {
    const m = document.getElementById('themeModal');
    m.classList.toggle('active');
}

function toggleLightMode() {
    document.body.classList.toggle('light-mode');
    const isLight = document.body.classList.contains('light-mode');
    saveTheme('light', isLight);
    updateToggleDot();
}

function updateToggleDot() {
    const dot = document.getElementById('themeToggleDot');
    const btn = document.getElementById('themeToggleBtn');
    if (!dot || !btn) return;
    const isLight = document.body.classList.contains('light-mode');
    dot.style.left = isLight ? '24px' : '2px';
    dot.style.background = isLight ? '#34C759' : 'var(--g-text)';
    btn.style.borderColor = isLight ? '#34C759' : 'var(--g-border)';
}

function updateAccent(val, save = true) {
    document.documentElement.style.setProperty('--g-accent', val);
    const el = document.getElementById('accentValue');
    if (el) el.textContent = val;
    if (save) saveTheme('accent', val);
}

function updateBg(val, save = true) {
    document.documentElement.style.setProperty('--g-bg', val);
    if (save) saveTheme('bg', val);
}

function updateCard(val, save = true) {
    document.documentElement.style.setProperty('--g-card', val);
    if (save) saveTheme('card', val);
}

function resetTheme() {
    localStorage.removeItem('galbraith_theme');
    document.body.classList.remove('light-mode');
    document.documentElement.style.removeProperty('--g-accent');
    document.documentElement.style.removeProperty('--g-bg');
    document.documentElement.style.removeProperty('--g-card');
    const ap = document.getElementById('accentPicker');
    const bp = document.getElementById('bgPicker');
    const cp = document.getElementById('cardPicker');
    if (ap) ap.value = '#E81C5A';
    if (bp) bp.value = '#0E0E0E';
    if (cp) cp.value = '#0F0F0F';
    const av = document.getElementById('accentValue');
    if (av) av.textContent = '#E81C5A';
    updateToggleDot();
}

function saveTheme(key, val) {
    const t = JSON.parse(localStorage.getItem('galbraith_theme') || '{}');
    t[key] = val;
    localStorage.setItem('galbraith_theme', JSON.stringify(t));
}

function showToast(msg, duration) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), duration || 3000);
}

// Task tracking via cookies
function completeTask(taskId) {
    let completed = JSON.parse(getCookie('completed_tasks') || '[]');
    if (!completed.includes(taskId)) {
        showToast('⏳ Проверяем выполнение задания... Ожидайте 10 секунд', 10000);
        setTimeout(() => {
            completed.push(taskId);
            setCookie('completed_tasks', JSON.stringify(completed), 365);
            showToast('✅ Задание выполнено! Обновляю страницу...');
            setTimeout(() => location.reload(), 1500);
        }, 10000);
    }
}

function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
}

function setCookie(name, value, days) {
    const d = new Date();
    d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/';
}

// Мини-таймеры "через ..." на карточках премьерных материалов
(function() {
    function fmt(diff) {
        if (diff <= 0) return 'уже вышло';
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        if (d > 0) return 'через ' + d + 'д ' + h + 'ч';
        if (h > 0) return 'через ' + h + 'ч ' + m + 'м';
        return 'через ' + m + 'м';
    }
    function tickAll() {
        document.querySelectorAll('.countdown-mini[data-target]').forEach(function(el) {
            var target = new Date(el.dataset.target).getTime();
            el.textContent = fmt(target - Date.now());
        });
    }
    if (document.querySelectorAll('.countdown-mini[data-target]').length) {
        tickAll();
        setInterval(tickAll, 30000);
    }
})();

// Плавное появление карточек/блоков при скролле
(function() {
    function initReveal() {
        const targets = document.querySelectorAll('.card, .chat-card, .alert, .comment');
        if (!targets.length) return;
        if (!('IntersectionObserver' in window)) {
            targets.forEach(el => el.classList.add('reveal-in'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    setTimeout(() => el.classList.add('reveal-in'), Math.min(i * 40, 320));
                    io.unobserve(el);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
        targets.forEach(el => { el.classList.add('reveal'); io.observe(el); });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReveal);
    } else {
        initReveal();
    }
})();

function toggleHeaderPopup(id){const p=document.getElementById(id);if(!p)return;document.querySelectorAll('.header-popup.active').forEach(x=>{if(x!==p)x.classList.remove('active')});p.classList.toggle('active')}
document.addEventListener('click',e=>{if(e.target.closest('.header-popup-wrap'))return;document.querySelectorAll('.header-popup.active').forEach(p=>p.classList.remove('active'))});
function markNotificationsRead(){fetch('/notifications/read',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(()=>{document.getElementById('notificationBadge')?.remove();document.querySelectorAll('.notification-item.unread').forEach(x=>x.classList.remove('unread'))})}
