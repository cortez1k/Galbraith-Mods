<?php
// Объявление: все сборки из каталога исправлены и готовы к использованию.
$ready = [];
foreach (getMaterials(null, null, null, 'new') as $m) {
    if (empty($m['is_premiere'])) $ready[] = $m;   // материалы на премьере ещё недоступны — в список не берём
}
$total = count($ready);
$shown = array_slice($ready, 0, 10);
$more = $total - count($shown);

$pageTitle = 'Все сборки исправлены';
$pageDesc = 'Все сборки из каталога Galbraith исправлены и готовы к использованию.';
include __DIR__ . '/../templates/header.php';
?>
<style>
.fx { position: relative; overflow: hidden; isolation: isolate; margin: 8px 0 0; border-radius: var(--g-radius); border: 1px solid var(--g-border-strong); color: var(--g-text); text-align: center; padding: 64px 24px 56px; background: radial-gradient(ellipse at 50% 0%, rgba(232,28,90,.08), transparent 55%), var(--g-card); }
.fx::before { content: ''; position: absolute; inset: 0; z-index: -2;
    background-image: linear-gradient(rgba(20,18,14,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(20,18,14,.05) 1px, transparent 1px);
    background-size: 44px 44px; -webkit-mask-image: radial-gradient(ellipse at center, #000 20%, transparent 75%); mask-image: radial-gradient(ellipse at center, #000 20%, transparent 75%); }
.fx::after { content: ''; position: absolute; left: 50%; top: 22%; width: 760px; height: 760px; margin-left: -380px; z-index: -1; pointer-events: none;
    background: radial-gradient(circle, rgba(232,28,90,.12) 0%, transparent 62%); animation: g-glow-breathe 6s ease-in-out infinite; }
.fx-scan { position: absolute; left: 0; right: 0; top: 0; height: 120px; z-index: -1; pointer-events: none;
    background: linear-gradient(180deg, transparent, rgba(232,28,90,.14), transparent); animation: fx-scan 5s linear infinite; }
@keyframes fx-scan { from { transform: translateY(-120px); } to { transform: translateY(640px); } }

.fx-badge { position: relative; width: 132px; height: 132px; margin: 0 auto 28px; }
.fx-badge .ring { position: absolute; inset: 0; border-radius: 50%; border: 2px solid var(--g-accent); opacity: 0; animation: fx-ring 2.6s ease-out infinite; }
.fx-badge .ring:nth-child(2) { animation-delay: .9s; } .fx-badge .ring:nth-child(3) { animation-delay: 1.8s; }
@keyframes fx-ring { 0% { transform: scale(.7); opacity: .85; } 100% { transform: scale(1.9); opacity: 0; } }
.fx-badge svg { position: relative; width: 100%; height: 100%; filter: drop-shadow(0 0 22px rgba(232,28,90,.7)); }
.fx-badge .c { stroke-dasharray: 340; stroke-dashoffset: 340; animation: fx-draw .9s .15s var(--g-ease) forwards; }
.fx-badge .t { stroke-dasharray: 80; stroke-dashoffset: 80; animation: fx-draw .6s .85s var(--g-ease) forwards; }
.fx-badge .f { opacity: 0; animation: g-fade-in .5s 1.2s forwards; }
@keyframes fx-draw { to { stroke-dashoffset: 0; } }

.fx-tag { display: inline-flex; align-items: center; gap: 8px; font-family: var(--g-font-mono); font-size: 12px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--g-success); background: rgba(47,224,176,.1); border: 1px solid rgba(47,224,176,.4); padding: 6px 14px; border-radius: 9999px; margin-bottom: 18px; }
.fx-tag i { width: 7px; height: 7px; border-radius: 50%; background: var(--g-success); animation: fx-blink 1.4s ease-in-out infinite; }
@keyframes fx-blink { 50% { opacity: .25; } }
.fx h1 { font-size: 66px; line-height: 1; text-transform: uppercase; margin-bottom: 14px; animation: g-fade-in-up .8s .3s var(--g-ease) both; }
.fx h1 em { font-style: normal; color: var(--g-accent-light); text-shadow: 0 0 18px rgba(232,28,90,.3); position: relative; display: inline-block; overflow: hidden; vertical-align: bottom; }
.fx h1 em::after { content: ''; position: absolute; top: 0; bottom: 0; width: 40%; left: -60%; background: linear-gradient(100deg, transparent, rgba(255,255,255,.55), transparent); transform: skewX(-18deg); animation: fx-shine 3.4s 1.6s ease-in-out infinite; }
@keyframes fx-shine { 0% { left: -60%; } 40%, 100% { left: 130%; } }
.fx .sub { font-size: 18px; line-height: 1.55; color: var(--g-text-secondary); max-width: 600px; margin: 0 auto 30px; animation: g-fade-in-up .8s .45s var(--g-ease) both; }
.fx .sub b { color: var(--g-text); }
.fx-stats { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; margin-bottom: 32px; animation: g-fade-in-up .8s .6s var(--g-ease) both; }
.fx-stat { min-width: 150px; padding: 16px 22px; border-radius: var(--g-radius-sm); background: rgba(255,255,255,.65); border: 1px solid var(--g-border-strong); backdrop-filter: blur(4px); }
.fx-stat b { display: block; font-family: var(--g-font-display); font-size: 38px; line-height: 1; color: var(--g-text); font-variant-numeric: tabular-nums; }
.fx-stat span { display: block; margin-top: 6px; font-size: 12px; letter-spacing: .05em; text-transform: uppercase; color: var(--g-text-secondary); }
.fx-cta { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; animation: g-fade-in-up .8s .75s var(--g-ease) both; }
#fxConfetti { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 3; }

.fx-list { margin-top: 28px; background: var(--g-card); border: 1px solid var(--g-border); border-radius: var(--g-radius); padding: 22px; }
.fx-list h2 { font-size: 20px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.fx-list h2 small { font-family: var(--g-font-mono); font-size: 12px; font-weight: 600; color: var(--g-success); letter-spacing: .06em; }
.fx-row { display: flex; align-items: center; gap: 14px; padding: 12px 14px; border-radius: var(--g-radius-sm); border: 1px solid transparent; opacity: 0; transform: translateX(-14px); transition: background var(--g-t-fast), border-color var(--g-t-fast); }
.fx-list.in .fx-row { animation: fx-row .5s var(--g-ease) forwards; animation-delay: calc(var(--i) * 90ms + 200ms); }
@keyframes fx-row { to { opacity: 1; transform: none; } }
.fx-row:hover { background: var(--g-card-hover); border-color: var(--g-border-strong); }
.fx-row .ck { flex: 0 0 26px; height: 26px; border-radius: 50%; background: rgba(47,224,176,.14); border: 1px solid rgba(47,224,176,.5); color: var(--g-success); display: flex; align-items: center; justify-content: center; }
.fx-row .nm { flex: 1; font-weight: 700; font-size: 14.5px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fx-row .st { font-family: var(--g-font-mono); font-size: 11.5px; font-weight: 600; letter-spacing: .08em; color: var(--g-success); }
.fx-more { text-align: center; font-size: 13px; color: var(--g-text-secondary); padding: 12px 0 0; }
.fx-help { margin-top: 18px; text-align: center; font-size: 13.5px; color: var(--g-text-secondary); }
.fx-help a { color: var(--g-accent-light); font-weight: 700; cursor: pointer; }

@media (max-width: 640px) { .fx { padding: 44px 16px 40px; } .fx h1 { font-size: 38px; } .fx .sub { font-size: 15.5px; } .fx-badge { width: 104px; height: 104px; } .fx-row .st { display: none; } }
</style>

<section class="fx" id="fx">
    <div class="fx-scan"></div>
    <canvas id="fxConfetti"></canvas>
    <div class="fx-badge" aria-hidden="true">
        <span class="ring"></span><span class="ring"></span><span class="ring"></span>
        <svg viewBox="0 0 120 120" fill="none">
            <circle class="f" cx="60" cy="60" r="52" fill="rgba(232,28,90,.16)"/>
            <circle class="c" cx="60" cy="60" r="52" stroke="#E81C5A" stroke-width="5" stroke-linecap="round" transform="rotate(-90 60 60)"/>
            <path class="t" d="M38 62l16 16 30-34" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <span class="fx-tag"><i></i> Обновление завершено</span>
    <h1>Всё <em>исправлено</em></h1>
    <p class="sub">Все сборки из каталога <b>исправлены и готовы к использованию</b>. Скачивайте, ставьте и играйте — без ошибок и танцев с бубном.</p>
    <div class="fx-stats">
        <div class="fx-stat"><b data-count="<?= (int)$total ?>">0</b><span>материалов готово</span></div>
        <div class="fx-stat"><b><i data-count="100" style="font-style:normal;">0</i>%</b><span>сборок исправлено</span></div>
    </div>
    <div class="fx-cta">
        <a href="/catalog" class="btn btn-primary btn-lg">Перейти в каталог</a>
        <?php if (!empty($shown)): ?><a href="#fxList" class="btn btn-secondary btn-lg">Что исправлено</a><?php endif; ?>
    </div>
</section>

<?php if (!empty($shown)): ?>
<section class="fx-list" id="fxList">
    <h2>Статус сборок <small>● ВСЕ РАБОТАЮТ</small></h2>
    <!--ROWS-->
    <?php foreach ($shown as $i => $m): ?>
    <a href="/mod/<?= e($m['slug']) ?>" class="fx-row" style="--i:<?= (int)$i ?>">
        <span class="ck"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></span>
        <span class="nm"><?= e($m['title']) ?></span>
        <span class="st">ГОТОВО</span>
    </a>
    <?php endforeach; ?>
    <!--/ROWS-->
    <?php if ($more > 0): ?><div class="fx-more">…и ещё <?= (int)$more ?> в каталоге — все тоже исправлены</div><?php endif; ?>
</section>
<?php endif; ?>

<p class="fx-help">Что-то работает не так? <a onclick="var l=document.getElementById('swLauncher');if(l)l.click();">Напишите в поддержку</a> — разберёмся.</p>

<script>
(function () {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Счётчики
    document.querySelectorAll('[data-count]').forEach(function (el) {
        var end = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (reduce || !end) { el.textContent = end; return; }
        var t0 = null, dur = 1400;
        setTimeout(function () {
            (function step(ts) {
                if (t0 === null) t0 = ts;
                var p = Math.min((ts - t0) / dur, 1), e = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(end * e);
                if (p < 1) requestAnimationFrame(step);
            })(performance.now());
        }, 700);
    });

    // Появление списка
    var list = document.getElementById('fxList');
    if (list) {
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (en) { if (en[0].isIntersecting) { list.classList.add('in'); io.disconnect(); } }, { threshold: 0.15 });
            io.observe(list);
        } else { list.classList.add('in'); }
    }

    // Конфетти (один залп)
    var cv = document.getElementById('fxConfetti');
    if (!cv || reduce) return;
    var host = document.getElementById('fx'), ctx = cv.getContext('2d'), parts = [];
    function size() { cv.width = host.clientWidth; cv.height = host.clientHeight; }
    size();
    var colors = ['#E81C5A', '#FF5C8A', '#ffffff', '#2FE0B0', '#FFB454'];
    function burst(x, y, n) {
        for (var i = 0; i < n; i++) {
            var a = Math.random() * Math.PI * 2, v = 4 + Math.random() * 9;
            parts.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 5, w: 5 + Math.random() * 6, h: 3 + Math.random() * 5,
                r: Math.random() * 6, vr: (Math.random() - .5) * .4, c: colors[(Math.random() * colors.length) | 0], life: 1 });
        }
    }
    setTimeout(function () { burst(cv.width / 2, cv.height * 0.3, 140); }, 1100);
    setTimeout(function () { burst(cv.width * 0.2, cv.height * 0.45, 60); burst(cv.width * 0.8, cv.height * 0.45, 60); }, 1500);
    (function loop() {
        ctx.clearRect(0, 0, cv.width, cv.height);
        parts = parts.filter(function (p) { return p.life > 0 && p.y < cv.height + 20; });
        parts.forEach(function (p) {
            p.vy += 0.22; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.r += p.vr; p.life -= 0.006;
            ctx.save(); ctx.globalAlpha = Math.max(p.life, 0); ctx.translate(p.x, p.y); ctx.rotate(p.r);
            ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore();
        });
        if (parts.length || performance.now() < 4000) requestAnimationFrame(loop);
    })();
    window.addEventListener('resize', size);
})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
