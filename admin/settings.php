<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['accent_default','bg_main_default','bg_card_default','text_main_default','text_secondary_default','bg_main_light','bg_card_light','text_main_light','text_secondary_light','social_yt','social_vk','vk_contact','admin_email','donate_thanks_text','donate_details','youtube_last_video_id','banner_icon','banner_brand','banner_brand_tag','banner_highlight','banner_message','banner_button_text','banner_button_url'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) setSetting($k, $_POST[$k]);
    }
    setSetting('banner_enabled', !empty($_POST['banner_enabled']) ? '1' : '0');
    if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo = uploadFile($_FILES['logo'], 'logos');
        if ($logo) setSetting('logo_path', $logo);
    }
    if (!empty($_FILES['favicon']['tmp_name']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $fav = uploadFile($_FILES['favicon'], 'logos');
        if ($fav) setSetting('favicon_path', $fav);
    }
    redirect('/admin/settings');
}
$pageTitle = 'Настройки';
include __DIR__ . '/../templates/admin_header.php';
?>

<h1 style="font-size:22px;font-weight:600;margin-bottom:20px;">Настройки сайта</h1>

<form method="POST" enctype="multipart/form-data">
    <div style="max-width:700px;display:grid;gap:16px;">
        <h3 style="font-size:16px;font-weight:600;">Цвета (тёмная тема)</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Акцент</label><input type="color" name="accent_default" class="input" value="<?= e(setting('accent_default', '#E81C5A')) ?>" style="height:40px;padding:4px;"></div>
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Фон</label><input type="color" name="bg_main_default" class="input" value="<?= e(setting('bg_main_default', '#F6F5F2')) ?>" style="height:40px;padding:4px;"></div>
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Карточки</label><input type="color" name="bg_card_default" class="input" value="<?= e(setting('bg_card_default', '#FFFFFF')) ?>" style="height:40px;padding:4px;"></div>
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Текст</label><input type="color" name="text_main_default" class="input" value="<?= e(setting('text_main_default', '#17140F')) ?>" style="height:40px;padding:4px;"></div>
        </div>

        <h3 style="font-size:16px;font-weight:600;margin-top:8px;">Цвета (светлая тема)</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Фон</label><input type="color" name="bg_main_light" class="input" value="<?= e(setting('bg_main_light', '#F5F5F7')) ?>" style="height:40px;padding:4px;"></div>
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Карточки</label><input type="color" name="bg_card_light" class="input" value="<?= e(setting('bg_card_light', '#FFFFFF')) ?>" style="height:40px;padding:4px;"></div>
        </div>

        <h3 style="font-size:16px;font-weight:600;margin-top:8px;">Контакты и соцсети</h3>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">YouTube</label><input type="url" name="social_yt" class="input" value="<?= e(setting('social_yt')) ?>"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">VK</label><input type="url" name="social_vk" class="input" value="<?= e(setting('social_vk')) ?>"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">VK для связи</label><input type="url" name="vk_contact" class="input" value="<?= e(setting('vk_contact')) ?>"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Email админа</label><input type="email" name="admin_email" class="input" value="<?= e(setting('admin_email')) ?>"></div>

        <h3 style="font-size:16px;font-weight:600;margin-top:8px;">YouTube — последний ролик</h3>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">ID видео (из URL после v=)</label><input type="text" name="youtube_last_video_id" class="input" value="<?= e(setting('youtube_last_video_id')) ?>" placeholder="например: dQw4w9WgXcQ"></div>
        <p style="font-size:12px;color:var(--g-text-secondary);">Вставьте ID ролика — название и превью подтянутся автоматически.</p>

        <h3 style="font-size:16px;font-weight:600;margin-top:8px;">Донат</h3>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Текст благодарности (HTML)</label><textarea name="donate_thanks_text" class="input" rows="3"><?= e(setting('donate_thanks_text')) ?></textarea></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Реквизиты</label><textarea name="donate_details" class="input" rows="2"><?= e(setting('donate_details')) ?></textarea></div>

        <h3 style="font-size:16px;font-weight:600;margin-top:8px;">Верхний баннер-объявление</h3>
        <p style="font-size:12px;color:var(--g-text-secondary);margin-top:-8px;">Показывается сплошной полосой над шапкой сайта у всех посетителей и продавцов на всех страницах (в панелях админки/модерации/поддержки не показывается).</p>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius-sm);">
            <input type="checkbox" name="banner_enabled" id="bannerEnabled" value="1" <?= setting('banner_enabled','0') === '1' ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--g-accent);">
            <label for="bannerEnabled" style="font-size:14px;font-weight:600;cursor:pointer;">Показывать баннер на сайте</label>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Иконка/эмодзи (необязательно)</label><input type="text" name="banner_icon" class="input" value="<?= e(setting('banner_icon')) ?>" placeholder="🦝" maxlength="4"></div>
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Бренд (жирный текст)</label><input type="text" name="banner_brand" class="input" value="<?= e(setting('banner_brand')) ?>" placeholder="РОССИЯ"></div>
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Плашка рядом с брендом</label><input type="text" name="banner_brand_tag" class="input" value="<?= e(setting('banner_brand_tag')) ?>" placeholder="ОНЛАЙН"></div>
            <div><label style="font-size:13px;color:var(--g-text-secondary);">Кнопка — текст</label><input type="text" name="banner_button_text" class="input" value="<?= e(setting('banner_button_text')) ?>" placeholder="УЗНАТЬ БОЛЬШЕ"></div>
        </div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Акцентный текст (розовый, жирный)</label><input type="text" name="banner_highlight" class="input" value="<?= e(setting('banner_highlight')) ?>" placeholder="ПОКОРИТЕ МОСКВУ"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Основной текст (белый, после акцентного)</label><input type="text" name="banner_message" class="input" value="<?= e(setting('banner_message')) ?>" placeholder="В НОВОЙ ИГРЕ ОТ СОЗДАТЕЛЕЙ MAJESTIC"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Кнопка — ссылка</label><input type="url" name="banner_button_url" class="input" value="<?= e(setting('banner_button_url')) ?>" placeholder="https://..."></div>

        <h3 style="font-size:16px;font-weight:600;margin-top:8px;">Логотип и фавикон</h3>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Логотип</label><input type="file" name="logo" class="input" accept="image/*"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Фавикон</label><input type="file" name="favicon" class="input" accept="image/*"></div>

        <div style="margin-top:8px;">
            <button type="submit" class="btn btn-primary">Сохранить настройки</button>
        </div>
    </div>
</form>

<h2 style="font-size:18px;font-weight:600;margin:36px 0 16px;">Торговая площадка, гарант и бонусы</h2>
<form method="POST" action="/admin/settings/marketplace">
    <div style="max-width:700px;display:grid;gap:16px;">
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Глобальная комиссия сайта, % (используется, если у торговца не задана своя)</label><input type="number" step="0.01" name="commission_percent" class="input" value="<?= e(setting('commission_percent', 10)) ?>"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Срок автоподтверждения получения товара, дней</label><input type="number" name="auto_confirm_days" class="input" value="<?= e(setting('auto_confirm_days', 3)) ?>"></div>
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;"><input type="checkbox" name="bonus_enabled" <?= setting('bonus_enabled', '1') === '1' ? 'checked' : '' ?>> Бонусная система включена</label>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Порог бонусов для скидки 10%</label><input type="number" name="bonus_threshold" class="input" value="<?= e(setting('bonus_threshold', 10)) ?>"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Рублей за 1 бонус (0 = не начислять автоматически)</label><input type="number" step="0.01" name="bonus_rub_per_point" class="input" value="<?= e(setting('bonus_rub_per_point', 100)) ?>"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Текст плашки «Стать торговцем» на /marketplace</label><textarea name="become_trader_text" class="input" rows="2"><?= e(setting('become_trader_text')) ?></textarea></div>
        <div style="margin-top:8px;">
            <button type="submit" class="btn btn-primary">Сохранить настройки маркетплейса</button>
        </div>
    </div>
</form>

<h2 style="font-size:18px;font-weight:600;margin:36px 0 16px;">Бонусы торговцев, рефералка и дисциплина</h2>
<form method="POST" action="/admin/settings/trader-bonus">
    <div style="max-width:700px;display:grid;gap:14px;">
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;"><input type="checkbox" name="trader_bonus_enabled" <?= setting('trader_bonus_enabled','1')==='1'?'checked':'' ?>> Бонусная система для торговцев включена</label>

        <h4 style="font-size:14px;font-weight:600;margin-top:8px;">Автоначисления</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Бонус за завершённый заказ</label><input type="number" step="0.01" name="tb_per_completed_order" class="input" value="<?= e(setting('tb_per_completed_order',1)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Порог суммы заказа для доп. бонуса, ₽</label><input type="number" name="tb_per_order_over_amount" class="input" value="<?= e(setting('tb_per_order_over_amount',1000)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Доп. бонус за превышение суммы</label><input type="number" step="0.01" name="tb_per_order_over_bonus" class="input" value="<?= e(setting('tb_per_order_over_bonus',1)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Бонус за отзыв 5★</label><input type="number" step="0.01" name="tb_per_positive_review" class="input" value="<?= e(setting('tb_per_positive_review',1)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Порог активных товаров (ежемес.)</label><input type="number" name="tb_monthly_active_products_threshold" class="input" value="<?= e(setting('tb_monthly_active_products_threshold',10)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Бонус за товары (ежемес.)</label><input type="number" step="0.01" name="tb_monthly_active_products_bonus" class="input" value="<?= e(setting('tb_monthly_active_products_bonus',1)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Порог заказов за 30 дней</label><input type="number" name="tb_monthly_orders30_threshold" class="input" value="<?= e(setting('tb_monthly_orders30_threshold',5)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Бонус за заказы за 30 дней</label><input type="number" step="0.01" name="tb_monthly_orders30_bonus" class="input" value="<?= e(setting('tb_monthly_orders30_bonus',2)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Бонус за спор, решённый в пользу продавца</label><input type="number" step="0.01" name="tb_per_dispute_won" class="input" value="<?= e(setting('tb_per_dispute_won',0.5)) ?>"></div>
        </div>

        <h4 style="font-size:14px;font-weight:600;margin-top:8px;">Реферальная программа</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Срок действия привязки, дней</label><input type="number" name="referral_days" class="input" value="<?= e(setting('referral_days',30)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Бонус за первую покупку реферала</label><input type="number" step="0.01" name="referral_first_purchase_bonus" class="input" value="<?= e(setting('referral_first_purchase_bonus',2)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Порог суммы покупки, ₽</label><input type="number" name="referral_purchase_threshold_amount" class="input" value="<?= e(setting('referral_purchase_threshold_amount',3000)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Доп. бонус за превышение суммы</label><input type="number" step="0.01" name="referral_purchase_threshold_bonus" class="input" value="<?= e(setting('referral_purchase_threshold_bonus',1)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Бонус за регистрацию торговцем + товар</label><input type="number" step="0.01" name="referral_trader_signup_bonus" class="input" value="<?= e(setting('referral_trader_signup_bonus',5)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Бонус за 3+ покупки за 30 дней</label><input type="number" step="0.01" name="referral_3purchases_bonus" class="input" value="<?= e(setting('referral_3purchases_bonus',3)) ?>"></div>
        </div>

        <h4 style="font-size:14px;font-weight:600;margin-top:8px;">Магазин бонусов</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Стоимость: -1% комиссии</label><input type="number" step="0.01" name="cost_commission_reduction" class="input" value="<?= e(setting('cost_commission_reduction',5)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Стоимость: бесплатный вывод</label><input type="number" step="0.01" name="cost_free_withdrawal" class="input" value="<?= e(setting('cost_free_withdrawal',10)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Стоимость: топ-продавец</label><input type="number" step="0.01" name="cost_top_rank" class="input" value="<?= e(setting('cost_top_rank',15)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Дней статуса топ-продавца</label><input type="number" name="top_rank_days" class="input" value="<?= e(setting('top_rank_days',7)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Курс: 1 бонус = ? ₽</label><input type="number" step="0.01" name="bonus_to_rub_rate" class="input" value="<?= e(setting('bonus_to_rub_rate',10)) ?>"></div>
            
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Комиссия на вывод средств, %</label><input type="number" step="0.01" name="withdrawal_fee_percent" class="input" value="<?= e(setting('withdrawal_fee_percent',0)) ?>"></div>
        </div>

        <h4 style="font-size:14px;font-weight:600;margin-top:8px;">Дисциплина</h4>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Срок действия взыскания, дней</label><input type="number" name="penalty_expiry_days" class="input" value="<?= e(setting('penalty_expiry_days',30)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Взысканий до блокировки</label><input type="number" name="penalty_max_active" class="input" value="<?= e(setting('penalty_max_active',5)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Шаг увеличения комиссии за взыскание, %</label><input type="number" step="0.01" name="penalty_commission_step" class="input" value="<?= e(setting('penalty_commission_step',1)) ?>"></div>
            <div><label style="font-size:12px;color:var(--g-text-secondary);">Максимальная комиссия, %</label><input type="number" step="0.01" name="penalty_max_commission" class="input" value="<?= e(setting('penalty_max_commission',50)) ?>"></div>
        </div>

        <h4 style="font-size:14px;font-weight:600;margin-top:8px;">Маркетплейс</h4>
        <div><label style="font-size:12px;color:var(--g-text-secondary);">Максимум закреплённых товаров</label><input type="number" name="max_pinned_products" class="input" value="<?= e(setting('max_pinned_products',5)) ?>"></div>

        <div style="margin-top:8px;">
            <button type="submit" class="btn btn-primary">Сохранить настройки бонусов и дисциплины</button>
        </div>
    </div>
</form>

<h2 style="font-size:18px;font-weight:600;margin:36px 0 16px;">Почта (SMTP)</h2>
<p style="color:var(--g-text-secondary);font-size:13px;margin-bottom:16px;">Если хост не указан, письма отправляются через системную функцию mail() (может не работать на некоторых хостингах).</p>
<form method="POST" action="/admin/settings/smtp">
    <div style="max-width:500px;display:grid;gap:14px;">
        <div><label style="font-size:13px;color:var(--g-text-secondary);">SMTP хост</label><input type="text" name="smtp_host" class="input" placeholder="smtp.yandex.ru" value="<?= e(setting('smtp_host','')) ?>"></div>
        <div style="display:flex;gap:12px;">
            <div style="flex:1;"><label style="font-size:13px;color:var(--g-text-secondary);">Порт</label><input type="number" name="smtp_port" class="input" value="<?= e(setting('smtp_port',587)) ?>"></div>
            <div style="flex:1;"><label style="font-size:13px;color:var(--g-text-secondary);">Безопасность</label>
                <select name="smtp_security" class="select">
                    <option value="tls" <?= setting('smtp_security','tls')==='tls'?'selected':'' ?>>TLS (587)</option>
                    <option value="ssl" <?= setting('smtp_security')==='ssl'?'selected':'' ?>>SSL (465)</option>
                    <option value="none" <?= setting('smtp_security')==='none'?'selected':'' ?>>Без шифрования</option>
                </select>
            </div>
        </div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Логин (email отправителя)</label><input type="text" name="smtp_username" class="input" value="<?= e(setting('smtp_username','')) ?>"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Пароль</label><input type="password" name="smtp_password" class="input" placeholder="<?= setting('smtp_password') ? '••••••••' : '' ?>" value=""></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Имя отправителя</label><input type="text" name="smtp_from_name" class="input" value="<?= e(setting('smtp_from_name','Galbraith Support')) ?>"></div>
        <div>
            <label style="font-size:13px;color:var(--g-text-secondary);">Домен для EHLO (необязательно)</label>
            <input type="text" name="smtp_helo_domain" class="input" value="<?= e(setting('smtp_helo_domain','')) ?>" placeholder="например, ваш-сайт.ru">
            <p style="font-size:12px;color:var(--g-text-secondary);margin-top:4px;">Если оставить пустым — используется домен из логина (обычно <code>yandex.ru</code>). Влияет на репутацию письма у внешних почтовых сервисов (Gmail, Mail.ru и т.п.).</p>
        </div>
        <button type="submit" class="btn btn-primary" style="width:fit-content;">Сохранить SMTP</button>
    </div>
</form>

<?php if (isset($_GET['smtp_test'])): ?>
    <?php if ($_GET['smtp_test'] === 'ok'): ?>
        <div style="max-width:500px;margin-top:14px;padding:12px 16px;border-radius:var(--g-radius-sm);background:rgba(52,199,89,0.1);border:1px solid rgba(52,199,89,0.3);font-size:14px;">✅ Тестовое письмо отправлено на <?= e($_GET['to'] ?? '') ?>. Проверьте папку «Входящие» и «Спам» у получателя.</div>
    <?php else: ?>
        <div style="max-width:500px;margin-top:14px;padding:12px 16px;border-radius:var(--g-radius-sm);background:rgba(255,59,48,0.1);border:1px solid rgba(255,59,48,0.3);font-size:14px;">❌ Не удалось отправить: <?= e($_GET['err'] ?? 'неизвестная ошибка') ?></div>
    <?php endif; ?>
<?php endif; ?>

<form method="POST" action="/admin/settings/smtp-test" style="max-width:500px;margin-top:14px;display:flex;gap:10px;align-items:flex-end;">
    <div style="flex:1;"><label style="font-size:13px;color:var(--g-text-secondary);">Отправить тестовое письмо на</label><input type="email" name="test_email" class="input" placeholder="you@gmail.com" required></div>
    <button type="submit" class="btn btn-secondary">Отправить тест</button>
</form>

<?php
$recentEmails = [];
try {
    $recentEmails = getDB()->query('SELECT recipient, subject, status, error, created_at FROM email_log ORDER BY id DESC LIMIT 15')->fetchAll();
} catch (Throwable $e) {}
?>
<?php if ($recentEmails): ?>
<div style="max-width:900px;margin-top:20px;overflow-x:auto;">
    <p style="font-size:13px;color:var(--g-text-secondary);margin-bottom:8px;">Последние попытки отправки писем:</p>
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="text-align:left;border-bottom:1px solid var(--g-border,#333);">
            <th style="padding:6px 8px;">Когда</th><th style="padding:6px 8px;">Кому</th><th style="padding:6px 8px;">Тема</th><th style="padding:6px 8px;">Статус</th><th style="padding:6px 8px;">Ошибка</th>
        </tr></thead>
        <tbody>
        <?php foreach ($recentEmails as $row): ?>
            <tr style="border-bottom:1px solid var(--g-border,#222);">
                <td style="padding:6px 8px;white-space:nowrap;"><?= e($row['created_at']) ?></td>
                <td style="padding:6px 8px;"><?= e($row['recipient']) ?></td>
                <td style="padding:6px 8px;"><?= e($row['subject']) ?></td>
                <td style="padding:6px 8px;"><?= $row['status'] === 'sent' ? '✅' : '❌' ?> <?= e($row['status']) ?></td>
                <td style="padding:6px 8px;color:#c00;"><?= e($row['error']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<h2 style="font-size:18px;font-weight:600;margin:36px 0 16px;">💬 VK-бот (уведомления в сообщениях сообщества)</h2>
<p style="font-size:13px;color:var(--g-text-secondary);max-width:700px;margin-bottom:14px;">Создайте сообщество VK и включите сообщения. В настройках API/ключей сообщества создайте ключ с правом отправки сообщений. Вставьте его сюда. Для приёма сообщений укажите Callback API URL <code><?= e(rtrim(SITE_URL, '/')) ?>/vk/webhook</code>.</p>
<form method="POST" action="/admin/settings/vk-bot">
    <div style="display:grid;gap:12px;max-width:600px;">
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Ключ сообщества VK</label><input type="password" name="vk_bot_token" class="input" value="<?= e(setting('vk_bot_token','')) ?>" placeholder="vk1.a...." autocomplete="off"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Код подтверждения Callback API</label><input type="text" name="vk_confirmation_code" class="input" value="<?= e(setting('vk_confirmation_code','')) ?>" placeholder="Код из настроек Callback API"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Секрет Callback API <span style="font-weight:400;">(необязательно)</span></label><input type="text" name="vk_callback_secret" class="input" value="<?= e(setting('vk_callback_secret','')) ?>" placeholder="Секретная строка"></div>
        <button type="submit" class="btn btn-primary" style="width:fit-content;">Сохранить настройки VK-бота</button>
    </div>
</form>
<?php if (vkBotConfigured()): ?>
    <form method="POST" action="/admin/settings/vk-bot-check" style="margin-top:12px;">
        <button type="submit" class="btn btn-secondary">Проверить ключ и подключить сообщество</button>
    </form>
    <?php $vkGroupName = setting('vk_group_name',''); $vkGroupId = setting('vk_group_id',''); $vkCallbackVerified = setting('vk_callback_verified','0') === '1'; ?>
    <?php if ($vkGroupId): ?>
        <p style="font-size:13px;color:#34C759;margin-top:10px;">✅ API сообщества доступен<?= $vkGroupName ? ': ' . e($vkGroupName) : '' ?> (ID <?= e($vkGroupId) ?>)</p>
        <p style="font-size:13px;margin-top:6px;color:<?= $vkCallbackVerified ? '#34C759' : 'var(--g-text-secondary)' ?>;">
            <?= $vkCallbackVerified ? '✅ Callback API подтверждён и сайт уже принимал событие VK.' : '⏳ Callback API ещё не подтверждён: после установки URL нажмите «Подтвердить» в VK.' ?>
        </p>
    <?php endif; ?>
    <?php if (isset($_GET['vk_check'])): ?>
        <?php if ($_GET['vk_check'] === 'fail'): ?>
            <p style="font-size:13px;color:var(--g-danger);margin-top:10px;">❌ Не удалось подключиться: <?= e($_GET['err'] ?? 'проверьте ключ') ?></p>
        <?php elseif ($_GET['vk_check'] === 'ok'): ?>
            <p style="font-size:13px;color:#34C759;margin-top:10px;">✅ Ключ VK принят, отправка уведомлений включена.</p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<h2 style="font-size:18px;font-weight:600;margin:36px 0 16px;">💳 Комиссия Donation Alerts</h2>
<form method="POST" action="/admin/settings/da-commission" style="max-width:300px;margin-bottom:20px;">
    <label style="font-size:13px;color:var(--g-text-secondary);">Процент комиссии, который видит покупатель</label>
    <div style="display:flex;gap:10px;margin-top:6px;">
        <input type="number" step="0.1" min="0" max="90" name="da_commission_percent" class="input" value="<?= e(setting('da_commission_percent','15')) ?>">
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
</form>

<h2 style="font-size:18px;font-weight:600;margin:36px 0 16px;">Бонусы покупателей</h2>
<form method="POST" action="/admin/settings/buyer-bonus">
    <div style="max-width:500px;display:grid;gap:14px;">
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;"><input type="checkbox" name="bonus_enabled" <?= setting('bonus_enabled','1')==='1'?'checked':'' ?>> Бонусы покупателей включены</label>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Рублей за 1 бонус</label><input type="number" name="bonus_rub_per_point" class="input" value="<?= e(setting('bonus_rub_per_point',100)) ?>"></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Максимальный процент скидки бонусами</label><input type="number" name="buyer_bonus_max_percent" class="input" value="<?= e(setting('buyer_bonus_max_percent',10)) ?>"></div>
        <button type="submit" class="btn btn-primary" style="width:fit-content;">Сохранить</button>
    </div>
</form>

<h2 style="font-size:18px;font-weight:600;margin:36px 0 16px;">🛠️ Технические работы</h2>
<?php $maintOn = setting('maintenance_mode','0') === '1'; ?>
<div style="max-width:600px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;padding:12px 16px;border-radius:var(--g-radius-sm);background:<?= $maintOn ? 'rgba(255,59,48,0.1)' : 'rgba(52,199,89,0.1)' ?>;border:1px solid <?= $maintOn ? 'rgba(255,59,48,0.3)' : 'rgba(52,199,89,0.3)' ?>;">
        <strong><?= $maintOn ? '🔴 Сайт сейчас закрыт на технические работы' : '🟢 Сайт работает в обычном режиме' ?></strong>
        <?php if ($maintOn && setting('maintenance_started_at')): ?><span style="color:var(--g-text-secondary);font-size:13px;">(с <?= date('d.m.Y H:i', strtotime(setting('maintenance_started_at'))) ?>)</span><?php endif; ?>
    </div>
    <form method="POST" action="/admin/settings/maintenance" style="display:grid;gap:14px;">
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Сообщение для посетителей</label><textarea name="maintenance_message" class="input" rows="3"><?= e(setting('maintenance_message')) ?></textarea></div>
        <div><label style="font-size:13px;color:var(--g-text-secondary);">Код доступа для обхода (необязательно — оставьте пустым, чтобы никто, кроме staff, не мог зайти)</label><input type="text" name="maintenance_bypass_code" class="input" value="<?= e(setting('maintenance_bypass_code')) ?>" placeholder="например, letme1n"></div>
        <div style="display:flex;gap:10px;">
            <?php if ($maintOn): ?>
                <button type="submit" name="maintenance_action" value="disable" class="btn btn-primary">Выключить технические работы</button>
                <button type="submit" name="maintenance_action" value="update" class="btn btn-secondary">Сохранить сообщение/код</button>
            <?php else: ?>
                <button type="submit" name="maintenance_action" value="enable" class="btn btn-danger" onclick="return confirm('Закрыть сайт на технические работы для всех, кроме админов/модераторов?')">Включить технические работы</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
