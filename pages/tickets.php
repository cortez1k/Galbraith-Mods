<?php
$pageTitle = 'Сообщить о проблеме';
include __DIR__ . '/../templates/header.php';
?>

<div style="max-width:500px;margin:0 auto;padding:40px 16px;text-align:center;">
    <div style="font-size:48px;margin-bottom:16px;">🎫</div>
    <h1 style="font-size:24px;font-weight:700;margin-bottom:8px;">Сообщить о проблеме</h1>
    <p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:24px;">Опиши баг или задай вопрос. Отвечу в течение 24 часов.</p>
    
    <form action="/tickets" method="POST" style="text-align:left;">
        <div style="display:grid;gap:12px;">
            <div>
                <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Тема *</label>
                <input type="text" name="subject" class="input" placeholder="Например: не скачивается сборка" required>
            </div>
            <div>
                <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Ваш контакт *</label>
                <input type="text" name="contact" class="input" placeholder="VK или email" required>
            </div>
            <div>
                <label style="display:block;font-size:13px;color:var(--g-text-secondary);margin-bottom:6px;">Описание *</label>
                <textarea name="message" class="input" rows="5" placeholder="Подробно опиши проблему..." required></textarea>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:16px;">Отправить тикет</button>
    </form>
    
    <a href="/" class="btn btn-secondary" style="margin-top:12px;width:100%;">← На главную</a>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>