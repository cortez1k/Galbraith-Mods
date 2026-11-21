<?php
$id = (int)($_GET['id'] ?? 0);
$material = getMaterialById($id);
if (!$material) { redirect('/'); }
$pageTitle = 'Подтверждение оплаты';
include __DIR__ . '/../templates/header.php';
?>

<div style="max-width:500px;margin:0 auto;text-align:center;padding:40px 16px;">
    <div style="width:64px;height:64px;border-radius:50%;background:rgba(232,28,90,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--g-accent)" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
    </div>
    <h1 style="font-size:24px;font-weight:600;margin-bottom:8px;">Форма покупки</h1>
    <p style="color:var(--g-text-secondary);font-size:14px;margin-bottom:24px;">Скопируйте текст ниже и отправьте автору</p>

    <div id="payForm" style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;text-align:left;font-family:monospace;font-size:14px;line-height:1.6;margin-bottom:20px;">
        <div>Товар: <?= e($material['title']) ?></div>
        <div>Способ оплаты: (укажите в сообщении)</div>
        <div>Дата покупки: <?= (new DateTime('now', new DateTimeZone('Europe/Moscow')))->format('d.m.Y H:i') ?> (МСК)</div>
    </div>

    <!-- Donation Alerts commission warning -->
    <div style="background:rgba(255,159,10,0.08);border:1px solid rgba(255,159,10,0.25);border-radius:var(--g-radius);padding:18px 20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:14px;">
        <span style="font-size:22px;flex-shrink:0;">⚠️</span>
        <div>
            <div style="font-size:15px;font-weight:600;color:#FF9F0A;margin-bottom:6px;">Важно при оплате через Donation Alerts</div>
            <div style="font-size:14px;color:var(--g-text-secondary);line-height:1.6;">
                При оплате через <strong>Donation Alerts</strong> вы обязаны покрыть комиссию платформы в размере <strong><?= e(formatPercent(setting('da_commission_percent','15'))) ?>%</strong>. 
                Если сумма доната меньше стоимости товара с учётом комиссии — вам придётся <strong>доплатить разницу</strong>.
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-bottom:16px;">
        <button class="btn btn-primary" onclick="copyForm()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            Скопировать форму
        </button>
        <a href="<?= e(setting('vk_contact', 'https://vk.ru/galbraith_off')) ?>" target="_blank" class="btn btn-secondary">Написать в ВК</a>
    </div>

    <p style="color:var(--g-text-secondary);font-size:13px;">Вставьте скопированную форму в сообщение, укажите ваш контакт для связи. Я отвечу в течение 24 часов. Не спамьте, пожалуйста.</p>
</div>

<script>
function copyForm() {
    const text = document.getElementById('payForm').innerText;
    navigator.clipboard.writeText(text).then(() => showToast('Форма скопирована!'));
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
