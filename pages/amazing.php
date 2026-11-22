<?php
$pageTitle = 'AMAZING On';
$pageDesc = 'Открытие нового сервера AMAZING';
$isCountdown = true;
$openAt = '2026-12-01 10:00:00'; // Пример даты для подсчета
include __DIR__ . '/../templates/header.php';
?>
    <section class="az-hero glass" style="position:relative; padding:6rem 0; overflow:hidden; background-image:url('/assets/img/amazing/hero.webp'); background-size:cover; background-position:center;">
        <div class="bg-glow g-glow-breathe" style="inset:0; z-index:1; background:radial-gradient(circle at 50% 50%, var(--accent-dim), transparent 60%);"></div>
        <div class="container" style="position:relative; z-index:2; text-align:center;">
            <h1 style="font-family:Rajdhani, Manrope, sans-serif; font-size:4rem; margin:0 0 1rem;">AMAZING On</h1>
            <p style="color:var(--text-2); font-size:1.2rem; margin:0 0 2rem; max-width:600px; margin:0 auto 2rem;">Скоро открытие нового сервера. Готовьтесь к приключениям.</p>
            <div class="countdown" style="display:flex; justify-content:center; gap:1rem; font-family:JetBrains Mono, monospace;">
                <div class="countdown-unit"><div class="countdown-num" id="cd-days">00</div><div class="countdown-label">Дней</div></div>
                <div class="countdown-unit"><div class="countdown-num" id="cd-hours">00</div><div class="countdown-label">Часов</div></div>
                <div class="countdown-unit"><div class="countdown-num" id="cd-mins">00</div><div class="countdown-label">Минут</div></div>
                <div class="countdown-unit"><div class="countdown-num" id="cd-secs">00</div><div class="countdown-label">Секунд</div></div>
            </div>
        </div>
    </section>
<?php include __DIR__ . '/../templates/footer.php'; ?>
<script>
    // Логика подсчета времени должна быть здесь для работы
    function updateCountdown(targetDate) {
        const now = new Date().getTime();
        const distance = targetDate - now;
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        document.getElementById('cd-days').innerText = String(days).padStart(2, '0');
        document.getElementById('cd-hours').innerText = String(hours).padStart(2, '0');
        document.getElementById('cd-mins').innerText = String(minutes).padStart(2, '0');
        document.getElementById('cd-secs').innerText = String(seconds).padStart(2, '0');

        if (distance < 0) {
            clearInterval(timer);
            document.querySelector('.countdown').innerHTML = '<div style="color:var(--accent); font-weight:700;">СЕРВЕР ОТКРЫТ!</div>';
        }
    }
    const targetDate = new Date('<?= $openAt ?>').getTime();
    const timer = setInterval(() => updateCountdown(targetDate), 1000);
    updateCountdown(targetDate);
</script>