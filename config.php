<?php
// Логи вместо белого экрана: фатальные ошибки пишутся в файл, а не пользователю.
// Вывод ошибок на экран выключен всегда — на проде это ломает заголовки и верстку.
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/logs/php-errors.log');
@ini_set('max_execution_time', '30');
@ini_set('ignore_user_abort', '1');
error_reporting(E_ALL);

// Один раз на процесс: регистрируем обработчик фатальных ошибок (OOM, исчерпан
// лимит времени, невызовное исключение) и сообщаем пользователю что-то внятное.
if (!defined('GALBRAITH_FATAL_HANDLER')) {
    define('GALBRAITH_FATAL_HANDLER', true);
    @register_shutdown_function(static function () {
        $err = error_get_last();
        if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        @file_put_contents($dir . '/php-errors.log', '[' . date('Y-m-d H:i:s') . '] ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'] . PHP_EOL, FILE_APPEND | LOCK_EX);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:20px;border:1px solid #ffcccc;border-radius:10px;background:#fff7f7;color:#222">';
        echo '<b>Страница не загрузилась.</b><p>Ошибка записана в лог, покажите её администратору сайта.</p></div>';
    });
}

session_start();

// ===== Bootstrap self-check: turn a blank 500 into a readable diagnostic =====
// This only ever shows if something is genuinely wrong with the hosting environment
// (missing PHP extension, no write permission, etc.) — it does not affect normal operation.
if (!defined('GALBRAITH_BOOTSTRAP_CHECK')) {
    define('GALBRAITH_BOOTSTRAP_CHECK', true);
    $missingExt = [];
    foreach (['sqlite3', 'mbstring', 'curl', 'openssl'] as $ext) {
        if (!extension_loaded($ext)) $missingExt[] = $ext;
    }
    $dbFile = __DIR__ . '/database.sqlite';
    $dbDir = __DIR__;
    $dbProblem = null;
    if (file_exists($dbFile) && !is_writable($dbFile)) {
        $dbProblem = "Файл database.sqlite существует, но недоступен для записи (нет прав). Выставьте права 664 или 666 на файл.";
    } elseif (!file_exists($dbFile) && !is_writable($dbDir)) {
        $dbProblem = "database.sqlite ещё не создан, а папка сайта недоступна для записи. Выставьте права 755 или 775 на папку " . $dbDir;
    }
    if ($missingExt || $dbProblem) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Настройка сервера</title>';
        echo '<style>body{font-family:sans-serif;max-width:700px;margin:60px auto;padding:0 20px;color:#222;line-height:1.6}
              h1{font-size:20px}code{background:#f2f2f2;padding:2px 6px;border-radius:4px}
              .box{background:#fff3f3;border:1px solid #ffcccc;border-radius:8px;padding:20px;margin-top:16px}</style>';
        echo '</head><body><h1>⚠️ Сайт не может запуститься: проблема окружения хостинга</h1>';
        echo '<div class="box">';
        if ($missingExt) {
            echo '<p><strong>Не включены необходимые расширения PHP:</strong> ' . implode(', ', $missingExt) . '.</p>';
            echo '<p>Включите их в панели управления хостингом (обычно раздел «PHP» / «Выбор версии PHP» → «Расширения»), либо попросите поддержку хостинга включить: <code>' . implode('</code>, <code>', $missingExt) . '</code>.</p>';
        }
        if ($dbProblem) {
            echo '<p><strong>Проблема с базой данных:</strong> ' . $dbProblem . '</p>';
        }
        echo '</div></body></html>';
        exit;
    }
}

define('BASE_URL', '');
define('ADMIN_EMAIL', 'admin@galbraith-yt.ru');
define('DB_PATH', __DIR__ . '/database.sqlite');
define('UPLOAD_DIR', __DIR__ . '/assets/uploads/');
define('UPLOAD_URL', '/assets/uploads/');

// --- Site URL (used to build OAuth redirect_uri). Auto-detected, override if behind a proxy. ---
if (!defined('SITE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443 ? 'https' : 'http';
    define('SITE_URL', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

// --- OAuth: Google ---
// Получить в https://console.cloud.google.com/apis/credentials
// Authorized redirect URI указать: SITE_URL . '/auth/google/callback'
define('GOOGLE_CLIENT_ID', '280040418714-v0aor98492sb50fhd24k5pb6u7k3db3f.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');

// --- OAuth: VK ID (id.vk.com, протокол OAuth 2.1 + PKCE) ---
// Получить в https://id.vk.com -> Мои приложения -> Создать
// Доверенный Redirect URI указать: SITE_URL . '/auth/vk/callback'
define('VK_CLIENT_ID', '54713279');
define('VK_CLIENT_SECRET', ''); // может не требоваться при PKCE, но некоторые типы приложений VK его запрашивают

// --- Роли пользователей ---
define('ROLES', ['admin', 'moderator', 'support', 'trader', 'premium', 'user']);

// --- Ключ для шифрования чувствительных данных (реквизиты карт торговцев) ---
// ВАЖНО: смените на свою случайную строку (32+ символов) перед боевым запуском
// и никогда не меняйте её после того, как в БД появятся зашифрованные данные — иначе они перестанут расшифровываться.
define('ENCRYPTION_KEY', 'CHANGE-ME-32-char-random-secret!');

// Auto-create upload dirs
$uploadDirs = ['covers', 'screenshots', 'logos'];
foreach ($uploadDirs as $d) {
    $path = UPLOAD_DIR . $d;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

// Default settings
$default_settings = [
    'accent_default' => '#E81C5A',
    'bg_main_default' => '#0D0D0F',
    'bg_card_default' => '#16161A',
    'text_main_default' => '#F0F0F5',
    'text_secondary_default' => '#8E8E93',
    'bg_main_light' => '#F5F5F7',
    'bg_card_light' => '#FFFFFF',
    'text_main_light' => '#1D1D1F',
    'text_secondary_light' => '#6E6E73',
    'social_yt' => 'https://www.youtube.com/@galbraith1629',
    'social_vk' => 'https://vk.ru/galbraith_yt',
    'vk_contact' => 'https://vk.ru/galbraith_off',
    'vk_bot_token' => '',
    'vk_group_id' => '',
    'vk_group_name' => '',
    'vk_confirmation_code' => '',
    'vk_callback_secret' => '',
    'vk_bot_enabled' => '0',
    'vk_api_version' => '5.199',
    'donate_details' => '2200 1234 5678 9012 (Иванов И.И.)',
    'donate_thanks_text' => '<p>Спасибо за поддержку проекта! Каждый донат мотивирует выпускать новый контент.</p>',
    'admin_email' => 'admin@galbraith-yt.ru',
];

// Настройки, добавленные в версии 2.0 (торговая площадка, гарант, бонусы)
$default_settings_v2 = [
    'commission_percent' => '10',       // глобальный процент комиссии сайта
    'auto_confirm_days' => '3',         // автоподтверждение получения товара, дней
    'bonus_enabled' => '1',             // включена ли бонусная система
    'bonus_threshold' => '10',          // сколько бонусов нужно для скидки 10%
    'bonus_rub_per_point' => '100',     // сколько рублей = 1 бонус (0 = не начислять автоматически)
    'become_trader_text' => 'Хотите стать торговцем? Напишите мне в ВК!',
];

// Настройки v2.1: бонусная система торговцев, задания, рефералка, дисциплина
$default_settings_v21 = [
    'trader_bonus_enabled' => '1',
    // Автоначисления
    'tb_per_completed_order' => '1',
    'tb_per_order_over_amount' => '1000',   // порог суммы заказа
    'tb_per_order_over_bonus' => '1',       // бонус за превышение порога
    'tb_per_positive_review' => '1',
    'tb_monthly_active_products_threshold' => '10',
    'tb_monthly_active_products_bonus' => '1',
    'tb_monthly_orders30_threshold' => '5',
    'tb_monthly_orders30_bonus' => '2',
    'tb_per_dispute_won' => '0.5',
    // Реферальная программа
    'referral_days' => '30',
    'referral_first_purchase_bonus' => '2',
    'referral_purchase_threshold_amount' => '3000',
    'referral_purchase_threshold_bonus' => '1',
    'referral_trader_signup_bonus' => '5',
    'referral_3purchases_bonus' => '3',
    // Магазин бонусов (трата)
    'cost_commission_reduction' => '5',      // бонусов за -1% комиссии
    'cost_free_withdrawal' => '10',
    'cost_top_rank' => '15',
    'top_rank_days' => '7',
    'cost_extra_slot' => '20',
    'bonus_to_rub_rate' => '10',             // 1 бонус = N руб.
    'trader_product_limit' => '10',
    'withdrawal_fee_percent' => '0',
    // Дисциплина
    'penalty_expiry_days' => '30',
    'penalty_max_active' => '5',
    'penalty_commission_step' => '1',
    'penalty_max_commission' => '50',
    // Маркетплейс
    'max_pinned_products' => '5',
];

// Настройки v3.0: email-регистрация, SMTP, модерация товаров, бонусы покупателей
$default_settings_v3 = [
    // SMTP (пусто = использовать системную mail())
'smtp_host' => 'smtp.yandex.ru',
'smtp_port' => '465',
'smtp_security' => 'ssl',
'smtp_username' => 'galbraith-sup@yandex.ru',
'smtp_password' => 'itensetfinxdmofr',
    'smtp_from_name' => 'Galbraith Support',
    // Модерация
    'commission_percent_free' => '0', // всегда 0, зафиксировано для бесплатных товаров
    // Бонусы покупателей: 1 бонус = 1% скидки, до buyer_bonus_max_percent (использованные бонусы списываются,
    // остаток сохраняется — уточнённое правило v3.0, заменяет прежнее all-or-nothing поведение v2.0)
    'buyer_bonus_max_percent' => '10',
];
