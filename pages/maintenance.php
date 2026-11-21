<?php
$message = setting('maintenance_message', 'Ведутся технические работы.');
$startedAt = setting('maintenance_started_at', '');
$hasBypassCode = setting('maintenance_bypass_code', '') !== '';
$error = $_SESSION['maintenance_error'] ?? null; unset($_SESSION['maintenance_error']);
?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Технические работы — Galbraith</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#0d0d14; color:#fff; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
        .box { max-width:460px; padding:40px 32px; text-align:center; }
        .icon { font-size:56px; margin-bottom:20px; animation:spin 3s linear infinite; display:inline-block; }
        @keyframes spin { from{transform:rotate(0deg);} to{transform:rotate(360deg);} }
        h1 { font-size:24px; margin-bottom:12px; }
        p { color:#9a9aa8; font-size:15px; line-height:1.6; margin-bottom:24px; }
        .timer { font-family:monospace; font-size:20px; font-weight:700; color:#E81C5A; background:rgba(232,28,90,0.12); border:1px solid rgba(232,28,90,0.3); border-radius:12px; padding:14px 20px; margin-bottom:24px; }
        .timer-label { font-size:12px; color:#9a9aa8; font-family:-apple-system,sans-serif; margin-bottom:6px; }
        form { margin-top:20px; display:flex; gap:8px; }
        input { flex:1; background:#1a1a24; border:1px solid #2a2a38; border-radius:8px; padding:10px 14px; color:#fff; font-size:14px; }
        button { background:#E81C5A; border:none; border-radius:8px; padding:10px 18px; color:#fff; font-weight:600; cursor:pointer; }
        .error { color:#ff5050; font-size:13px; margin-top:10px; }
    </style>
</head>
<body>
<div class="box">
    <div class="icon">🛠️</div>
    <h1>Технические работы</h1>
    <p><?= e($message) ?></p>

    <?php if ($startedAt): ?>
    <div class="timer">
        <div class="timer-label">Работы идут:</div>
        <div id="elapsed">—</div>
    </div>
    <script>
        var started = new Date("<?= date('Y-m-d\TH:i:s', strtotime($startedAt)) ?>").getTime();
        function tick() {
            var diff = Math.max(0, Math.floor((Date.now() - started) / 1000));
            var h = String(Math.floor(diff / 3600)).padStart(2, '0');
            var m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
            var s = String(diff % 60).padStart(2, '0');
            document.getElementById('elapsed').textContent = h + ':' + m + ':' + s;
        }
        tick();
        setInterval(tick, 1000);
    </script>
    <?php endif; ?>

    <?php if ($hasBypassCode): ?>
    <form method="POST" action="/maintenance-bypass">
        <input type="text" name="code" placeholder="Код доступа" autocomplete="off">
        <button type="submit">Войти</button>
    </form>
    <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
