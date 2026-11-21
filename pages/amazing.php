<?php
// Страница в честь открытия нового сервера AMAZING Online.
// Если нужен обратный отсчёт до открытия — впишите дату ниже (например '2026-10-20 18:00:00').
// Пустая строка = отсчёта нет, страница сразу в режиме «сервер открыт».
$openAt = '';

$isCountdown = $openAt !== '' && strtotime($openAt) > time();
$mats = getMaterials(4, null, null, 'new');

$pageTitle = 'Открытие нового сервера AMAZING Online';
$pageDesc = 'Новый сервер AMAZING Online открыт: сборки, АХК и моды для чистого старта на Galbraith.';
include __DIR__ . '/../templates/header.php';
?>
<style>
.az-hero { position: relative; overflow: hidden; border-radius: var(--g-radius); border: 1px solid var(--g-border-strong); min-height: 460px; margin: 8px 0 0; color: #fff;
    background: #0e0e0e url('/assets/img/amazing/hero.webp') right center / cover no-repeat; isolation: isolate; }
.az-hero::before { content: ''; position: absolute; inset: 0; z-index: -1;
    background: linear-gradient(90deg, #0e0e0e 0%, rgba(14,14,14,.93) 34%, rgba(14,14,14,.55) 62%, rgba(14,14,14,.15) 100%),
                linear-gradient(0deg, #0e0e0e 0%, transparent 38%); }
.az-hero::after { content: ''; position: absolute; left: -10%; top: -30%; width: 620px; height: 620px; z-index: -1; pointer-events: none;
    background: radial-gradient(circle, rgba(232,28,90,.32) 0%, transparent 66%); animation: g-glow-breathe 7s ease-in-out infinite; }
.az-hero-inner { position: relative; padding: 56px 44px 64px; max-width: 640px; }
.az-hero-inner > * { animation: g-fade-in-up .7s var(--g-ease) both; }
.az-hero-inner > *:nth-child(2) { animation-delay: .08s; } .az-hero-inner > *:nth-child(3) { animation-delay: .16s; }
.az-hero-inner > *:nth-child(4) { animation-delay: .24s; } .az-hero-inner > *:nth-child(5) { animation-delay: .32s; }
.az-eyebrow { display: inline-flex; align-items: center; gap: 8px; font-family: var(--g-font-mono); font-size: 12px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--g-accent-light); background: rgba(232,28,90,.14); border: 1px solid rgba(232,28,90,.4); padding: 6px 13px; border-radius: 9999px; margin-bottom: 20px; }
.az-eyebrow i { width: 7px; height: 7px; border-radius: 50%; background: var(--g-accent-light); animation: g-badge-pulse 1.6s ease-in-out infinite; }
.az-hero h1 { font-size: 52px; line-height: 1.02; font-weight: 700; margin-bottom: 16px; text-transform: uppercase; }
.az-hero h1 em { font-style: normal; display: block; color: var(--g-accent-light); text-shadow: 0 0 28px rgba(232,28,90,.55); }
.az-hero p.lead { font-size: 16px; line-height: 1.6; color: rgba(255,255,255,.78); max-width: 500px; margin-bottom: 26px; }
.az-cta { display: flex; gap: 12px; flex-wrap: wrap; }
.az-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 26px; }
.az-chip { font-size: 12px; font-weight: 700; letter-spacing: .03em; padding: 7px 13px; border-radius: 9999px; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.16); backdrop-filter: blur(4px); }
.az-mascot { position: absolute; right: 3%; bottom: 0; height: 104%; max-height: 520px; z-index: 0; pointer-events: none; animation: az-float 6s ease-in-out infinite; filter: drop-shadow(0 18px 40px rgba(232,28,90,.35)); }
@keyframes az-float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }

.az-count { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
.az-count .countdown-unit { background: rgba(0,0,0,.45); backdrop-filter: blur(4px); min-width: 70px; }

.az-section { margin-top: 44px; }
.az-grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.az-feature { padding: 24px 22px 26px; gap: 12px; }
.az-feature .az-num { position: absolute; right: 16px; top: 8px; font-family: var(--g-font-display); font-size: 54px; font-weight: 700; color: transparent; -webkit-text-stroke: 1px var(--g-border-strong); line-height: 1; }
.az-ico { width: 46px; height: 46px; border-radius: 12px; background: var(--g-accent-dim); border: 1px solid rgba(232,28,90,.4); display: flex; align-items: center; justify-content: center; color: var(--g-accent-light); transition: transform var(--g-t-base) var(--g-ease), background var(--g-t-base); }
.az-feature:hover .az-ico { transform: rotate(-6deg) scale(1.08); background: var(--g-accent); color: #fff; }
.az-feature h3 { font-size: 19px; }
.az-feature p { font-size: 13.5px; line-height: 1.55; color: var(--g-text-secondary); }

.az-steps { position: relative; display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; counter-reset: s; }
.az-steps::before { content: ''; position: absolute; left: 4%; right: 4%; top: 22px; height: 2px; background: linear-gradient(90deg, var(--g-accent), rgba(232,28,90,.1)); transform-origin: left; transform: scaleX(0); transition: transform 1.2s var(--g-ease); }
.az-steps.in::before { transform: scaleX(1); }
.az-step { position: relative; padding-top: 58px; }
.az-step::before { counter-increment: s; content: counter(s); position: absolute; top: 0; left: 0; width: 46px; height: 46px; border-radius: 50%; background: var(--g-bg); border: 2px solid var(--g-accent); display: flex; align-items: center; justify-content: center; font-family: var(--g-font-display); font-size: 20px; font-weight: 700; box-shadow: 0 0 18px rgba(232,28,90,.35); }
.az-step h4 { font-size: 17px; margin-bottom: 6px; }
.az-step p { font-size: 13.5px; line-height: 1.55; color: var(--g-text-secondary); }

.az-faq details { background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius-sm); margin-bottom: 10px; transition: border-color var(--g-t-base); }
.az-faq details[open] { border-color: var(--g-accent); }
.az-faq summary { cursor: pointer; list-style: none; padding: 16px 18px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.az-faq summary::-webkit-details-marker { display: none; }
.az-faq summary::after { content: '+'; font-size: 22px; color: var(--g-accent-light); transition: transform var(--g-t-base) var(--g-ease); }
.az-faq details[open] summary::after { transform: rotate(45deg); }
.az-faq details div { padding: 0 18px 16px; font-size: 14px; line-height: 1.6; color: var(--g-text-secondary); animation: g-fade-in .35s var(--g-ease); }

.az-final { position: relative; overflow: hidden; margin-top: 44px; padding: 40px; border-radius: var(--g-radius); border: 1px solid var(--g-accent); text-align: center; color: #fff;
    background: radial-gradient(ellipse at top, rgba(232,28,90,.28), #0e0e0e 70%); box-shadow: 0 0 40px rgba(232,28,90,.18); }
.az-final h2 { font-size: 32px; text-transform: uppercase; margin-bottom: 10px; }
.az-final p { color: rgba(255,255,255,.75); max-width: 520px; margin: 0 auto 22px; line-height: 1.6; }
.az-final .az-cta { justify-content: center; }
.az-note { margin-top: 22px; font-size: 12px; color: var(--g-text-secondary); text-align: center; }

.az-reveal { opacity: 0; transform: translateY(18px); transition: opacity .6s var(--g-ease), transform .6s var(--g-ease); }
.az-reveal.in { opacity: 1; transform: none; }

@media (max-width: 960px) { .az-grid4, .az-steps { grid-template-columns: repeat(2, 1fr); } .az-steps::before { display: none; } .az-mascot { opacity: .35; right: -6%; } }
@media (max-width: 600px) {
    .az-hero-inner { padding: 34px 20px 40px; } .az-hero h1 { font-size: 34px; } .az-mascot { display: none; }
    .az-grid4, .az-steps { grid-template-columns: 1fr; } .az-final { padding: 28px 18px; } .az-final h2 { font-size: 24px; }
}
</style>

<section class="az-hero">
    <div class="az-hero-inner">
        <span class="az-eyebrow"><i></i> <?= $isCountdown ? 'Скоро открытие' : 'Сервер открыт' ?></span>
        <h1>Новый сервер<em>AMAZING Online</em></h1>
        <p class="lead">Старт с нуля — лучшее время, чтобы занять дом, машину и своё место в экономике. Мы подготовили сборки, АХК и моды, чтобы войти в игру во всеоружии.</p>
        <div class="az-cta">
            <a href="/catalog" class="btn btn-primary btn-lg">Смотреть материалы</a>
            <a href="/marketplace" class="btn btn-secondary btn-lg">Торговая площадка</a>
        </div>
        <?php if ($isCountdown): ?>
        <div class="az-count" id="azCountdown" data-target="<?= e(date('c', strtotime($openAt))) ?>">
            <div class="countdown-unit"><span class="countdown-num" data-unit="days">00</span><span class="countdown-label">дней</span></div>
            <div class="countdown-unit"><span class="countdown-num" data-unit="hours">00</span><span class="countdown-label">часов</span></div>
            <div class="countdown-unit"><span class="countdown-num" data-unit="minutes">00</span><span class="countdown-label">минут</span></div>
            <div class="countdown-unit"><span class="countdown-num" data-unit="seconds">00</span><span class="countdown-label">секунд</span></div>
        </div>
        <?php endif; ?>
        <div class="az-chips"><span class="az-chip">Чистый старт</span><span class="az-chip">Свежая экономика</span><span class="az-chip">Первые дома и бизнесы</span></div>
    </div>
    <img class="az-mascot" src="/assets/img/amazing/mascot.webp" alt="" width="533" height="589">
</section>

<div class="hero-marquee" style="margin-top:14px;">
    <div class="hero-marquee-track">
        <span><i>●</i> НОВЫЙ СЕРВЕР <i>●</i> AMAZING ONLINE <i>●</i> ЧИСТЫЙ СТАРТ <i>●</i> ДОМА <i>●</i> МАШИНЫ <i>●</i> БИЗНЕС <i>●</i> НОВЫЙ СЕРВЕР <i>●</i> AMAZING ONLINE <i>●</i> ЧИСТЫЙ СТАРТ <i>●</i> ДОМА <i>●</i> МАШИНЫ <i>●</i> БИЗНЕС</span>
        <span aria-hidden="true"><i>●</i> НОВЫЙ СЕРВЕР <i>●</i> AMAZING ONLINE <i>●</i> ЧИСТЫЙ СТАРТ <i>●</i> ДОМА <i>●</i> МАШИНЫ <i>●</i> БИЗНЕС <i>●</i> НОВЫЙ СЕРВЕР <i>●</i> AMAZING ONLINE <i>●</i> ЧИСТЫЙ СТАРТ <i>●</i> ДОМА <i>●</i> МАШИНЫ <i>●</i> БИЗНЕС</span>
    </div>
</div>

<section class="az-section">
    <h2 class="section-title">Почему стоит зайти с первого дня</h2>
    <div class="az-grid4">
        <div class="card az-feature"><span class="az-num">01</span>
            <div class="az-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2z"/></svg></div>
            <h3>Чистый старт</h3><p>Все начинают с одной линии. Никаких отрывов по деньгам и имуществу — только ваши решения.</p></div>
        <div class="card az-feature"><span class="az-num">02</span>
            <div class="az-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M14.5 9.5C14 8.5 13 8 12 8c-1.5 0-2.5.8-2.5 2s1 1.7 2.5 2 2.5.8 2.5 2-1 2-2.5 2c-1 0-2-.5-2.5-1.5M12 6v2M12 16v2"/></svg></div>
            <h3>Свежая экономика</h3><p>Цены и рынок формируются заново — выгодные ниши раскупают в первые дни.</p></div>
        <div class="card az-feature"><span class="az-num">03</span>
            <div class="az-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></svg></div>
            <h3>Дома и бизнесы</h3><p>Лучшие участки и точки ещё свободны. Кто успел — тот и занял.</p></div>
        <div class="card az-feature"><span class="az-num">04</span>
            <div class="az-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13l2-6h14l2 6v4H3z"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/></svg></div>
            <h3>Первые машины</h3><p>Салоны открыты, парковки пустые. Самое время выбрать свою первую тачку.</p></div>
    </div>
</section>

<section class="az-section">
    <h2 class="section-title">Как подготовиться</h2>
    <div class="az-steps az-reveal" id="azSteps">
        <div class="az-step"><h4>Установи лаунчер</h4><p>Скачай актуальный лаунчер AMAZING Online с официального сайта проекта.</p></div>
        <div class="az-step"><h4>Выбери новый сервер</h4><p>В списке серверов найди новый и создай персонажа.</p></div>
        <div class="az-step"><h4>Забери сборку</h4><p>Открой каталог Galbraith, выбери сборку или АХК под свою версию игры.</p></div>
        <div class="az-step"><h4>Играй</h4><p>Выполни задания, скачай материал и вперёд — на старт.</p></div>
    </div>
</section>

<?php if ($mats): ?>
<section class="az-section">
    <h2 class="section-title">Подборка для нового сервера</h2>
    <div class="grid">
        <?php foreach ($mats as $m): $isPrem = !empty($m['is_premiere']); $isNew = !$isPrem && !empty($m['is_new']); ?>
        <a href="/mod/<?= e($m['slug']) ?>" class="card<?= $isPrem ? ' card-premiere' : '' ?>">
            <div class="card-img">
                <?php if ($m['cover_path']): ?><img src="<?= e(UPLOAD_URL . $m['cover_path']) ?>" alt="<?= e($m['title']) ?>"><?php endif; ?>
                <?php if ($isPrem): ?><span class="badge-premiere-tag">🚀 ПРЕМЬЕРА</span><?php elseif ($isNew): ?><span class="badge-new-tag">🆕 НОВИНКА</span><?php endif; ?>
                <span class="card-badge <?= $m['access_type'] === 'free' ? 'badge-free' : 'badge-paid' ?>"><?= $m['access_type'] === 'free' ? 'FREE' : formatPrice($m) ?></span>
            </div>
            <div class="card-body">
                <div class="card-title"><?= e($m['title']) ?></div>
                <div class="card-desc"><?= e($m['description']) ?></div>
                <div class="card-meta">
                    <?php if ($isPrem): ?><span class="countdown-mini" data-target="<?= e(date('c', strtotime($m['publish_at']))) ?>">скоро</span>
                    <?php else: ?><span><?= e($m['category_name'] ?? 'Без категории') ?> • <?= e($m['file_size'] ?: '—') ?></span><?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:18px;"><a href="/catalog" class="btn btn-secondary">Весь каталог →</a></div>
</section>
<?php endif; ?>

<section class="az-section az-faq">
    <h2 class="section-title">Частые вопросы</h2>
    <details class="az-reveal"><summary>Как скачать материал?</summary><div>Откройте страницу материала, выполните задания (если они есть) и нажмите «Скачать». Платные материалы помечены ценой.</div></details>
    <details class="az-reveal"><summary>Это бесплатно?</summary><div>Значительная часть каталога бесплатная — у таких материалов стоит бейдж FREE. Платные показывают цену на карточке.</div></details>
    <details class="az-reveal"><summary>Подойдёт ли материал к новому серверу?</summary><div>Смотрите «Версию игры» на странице материала. Если сомневаетесь — напишите в поддержку, подскажем.</div></details>
    <details class="az-reveal"><summary>Что делать, если что-то не работает?</summary><div>Напишите в чат поддержки в правом нижнем углу или создайте тикет — отвечаем в течение суток.</div></details>
</section>

<section class="az-final az-reveal">
    <h2>Не пропусти старт</h2>
    <p>Первые дни нового сервера решают многое. Забирай сборку заранее и заходи полностью готовым.</p>
    <div class="az-cta">
        <a href="/catalog" class="btn btn-primary btn-lg">Выбрать сборку</a>
        <button type="button" class="btn btn-secondary btn-lg" onclick="var l=document.getElementById('swLauncher');if(l)l.click();">Спросить в поддержке</button>
    </div>
</section>
<p class="az-note">Galbraith — независимый проект. AMAZING Online и связанные названия и изображения принадлежат их правообладателям.</p>

<script>
(function () {
    var els = document.querySelectorAll('.az-reveal');
    if (!('IntersectionObserver' in window)) { els.forEach(function (e) { e.classList.add('in'); }); }
    else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en, i) { if (en.isIntersecting) { setTimeout(function () { en.target.classList.add('in'); }, i * 70); io.unobserve(en.target); } });
        }, { threshold: 0.12 });
        els.forEach(function (e) { io.observe(e); });
    }
    var box = document.getElementById('azCountdown');
    if (box) {
        var target = new Date(box.dataset.target).getTime();
        var tick = function () {
            var d = target - Date.now();
            if (d <= 0) { location.reload(); return; }
            var v = { days: Math.floor(d / 864e5), hours: Math.floor(d % 864e5 / 36e5), minutes: Math.floor(d % 36e5 / 6e4), seconds: Math.floor(d % 6e4 / 1e3) };
            for (var k in v) { var el = box.querySelector('[data-unit="' + k + '"]'); if (el) el.textContent = String(v[k]).padStart(2, '0'); }
        };
        tick(); setInterval(tick, 1000);
    }
})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
