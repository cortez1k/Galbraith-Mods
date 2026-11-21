<?php
/**
 * Telegram-бот: дублирует уведомления сайта личными сообщениями в Telegram.
 * Работает через Bot API (без сторонних библиотек — httpGet/httpPostForm уже
 * есть в oauth.php) и вебхук, а не long polling: сайт живёт на обычном
 * PHP-хостинге, там нет фонового процесса, который мог бы держать соединение
 * с Telegram открытым постоянно.
 */

function telegramBotToken() {
    return trim((string)setting('telegram_bot_token', ''));
}

function telegramConfigured() {
    return telegramBotToken() !== '';
}

function telegramApiUrl($method) {
    return 'https://api.telegram.org/bot' . telegramBotToken() . '/' . $method;
}

/** Отправляет сообщение конкретному chat_id. Пишет результат (успех/ошибка,
 *  включая реальный текст ошибки curl или Telegram API) в telegram_log —
 *  раньше любая сетевая/SSL-ошибка на стороне хостинга молча проглатывалась
 *  (httpPostForm просто возвращала пустой '{}' при сбое curl), и не было
 *  никакого способа понять, почему уведомления не доходят. */
function tgSendMessage($chatId, $text) {
    if (!telegramConfigured() || !$chatId) return false;
    $ch = curl_init(telegramApiUrl('sendMessage'));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => 1]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        logTelegramAttempt($chatId, 'failed', 'curl: ' . ($curlError ?: 'неизвестная сетевая ошибка'));
        return false;
    }
    $data = json_decode($resp, true);
    if (!empty($data['ok'])) {
        logTelegramAttempt($chatId, 'sent');
        return true;
    }
    $err = 'HTTP ' . $httpCode . ': ' . ($data['description'] ?? substr($resp, 0, 200));
    logTelegramAttempt($chatId, 'failed', $err);
    error_log('Telegram sendMessage failed: ' . $err);
    return false;
}

function logTelegramAttempt($chatId, $status, $error = '') {
    try {
        getDB()->prepare('INSERT INTO telegram_log (chat_id, status, error) VALUES (?, ?, ?)')->execute([$chatId, $status, $error]);
    } catch (Throwable $e) {}
}

/** Проверяет токен и возвращает username бота (или null, если токен неверный/не задан). */
function telegramCheckBot() {
    global $__lastTelegramError;
    $__lastTelegramError = null;
    if (!telegramConfigured()) { $__lastTelegramError = 'Токен не задан'; return null; }
    $ch = curl_init(telegramApiUrl('getMe'));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true]);
    $resp = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($resp === false) { $__lastTelegramError = 'Сетевая ошибка: ' . ($curlError ?: 'не удалось подключиться к api.telegram.org'); return null; }
    $data = json_decode($resp, true);
    if (!empty($data['ok']) && !empty($data['result']['username'])) {
        return $data['result']['username'];
    }
    $__lastTelegramError = $data['description'] ?? 'Токен недействителен';
    return null;
}

function lastTelegramError() {
    global $__lastTelegramError;
    return $__lastTelegramError;
}

/** Регистрирует вебхук Telegram на /telegram/webhook текущего сайта. */
function telegramSetWebhook() {
    if (!telegramConfigured()) return false;
    $secret = telegramWebhookSecret();
    $resp = httpPostForm(telegramApiUrl('setWebhook'), [
        'url' => rtrim(SITE_URL, '/') . '/telegram/webhook',
        'secret_token' => $secret,
    ]);
    $data = json_decode($resp, true);
    return !empty($data['ok']);
}

/** Общий секрет для проверки заголовка X-Telegram-Bot-Api-Secret-Token у входящих запросов вебхука. */
function telegramWebhookSecret() {
    $secret = setting('telegram_webhook_secret', '');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(16));
        setSetting('telegram_webhook_secret', $secret);
    }
    return $secret;
}

/** Генерирует одноразовый код для привязки Telegram к аккаунту (кнопка в /profile). */
function generateTelegramLinkCode($userId) {
    $code = bin2hex(random_bytes(8));
    getDB()->prepare('UPDATE users SET telegram_link_code = ? WHERE id = ?')->execute([$code, $userId]);
    return $code;
}

function telegramDisconnect($userId) {
    getDB()->prepare('UPDATE users SET telegram_chat_id = NULL, telegram_link_code = NULL WHERE id = ?')->execute([$userId]);
}

/**
 * Обрабатывает входящий JSON-апдейт от Telegram. Понимает только команду
 * /start <код> — этого достаточно, чтобы привязать чат к аккаунту сайта.
 */
function handleTelegramUpdate($update) {
    $msg = $update['message'] ?? null;
    if (!$msg || empty($msg['text']) || empty($msg['chat']['id'])) return;
    $text = trim($msg['text']);
    $chatId = $msg['chat']['id'];

    if (preg_match('/^\/start\s+([a-f0-9]{16})$/', $text, $m)) {
        $code = $m[1];
        $stmt = getDB()->prepare('SELECT id, name FROM users WHERE telegram_link_code = ?');
        $stmt->execute([$code]);
        $user = $stmt->fetch();
        if ($user) {
            getDB()->prepare('UPDATE users SET telegram_chat_id = ?, telegram_link_code = NULL WHERE id = ?')
                ->execute([(string)$chatId, $user['id']]);
            tgSendMessage($chatId, '✅ Готово! Этот чат подключён к вашему аккаунту на Galbraith (' . $user['name'] . '). Теперь уведомления сайта будут дублироваться сюда.');
        } else {
            tgSendMessage($chatId, 'Код недействителен или уже использован. Получите новую ссылку в настройках профиля на сайте.');
        }
        return;
    }

    if ($text === '/start') {
        tgSendMessage($chatId, 'Привет! Чтобы подключить уведомления, откройте профиль на сайте Galbraith → «Telegram-уведомления» → «Подключить» и перейдите по ссылке оттуда.');
        return;
    }
}

/**
 * Отправляет уведомление в Telegram, если у пользователя привязан chat_id.
 * Вызывается из notify() наравне с email — best-effort, ошибки не всплывают.
 */
function sendTelegramNotification($userId, $type, $data) {
    if (!telegramConfigured()) return;
    try {
        $stmt = getDB()->prepare('SELECT telegram_chat_id, login_code_via_telegram FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !$row['telegram_chat_id']) return;
        // Код входа — отдельная настройка в профиле: можно отключить дублирование
        // в Telegram, если человек предпочитает получать его только на email.
        if ($type === 'login_code' && (int)($row['login_code_via_telegram'] ?? 1) === 0) return;
        $text = notificationSubject($type) . "\n\n" . strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", notificationContent($type, $data)));
        tgSendMessage($row['telegram_chat_id'], $text);
    } catch (Throwable $e) {
        error_log('Telegram notify failed: ' . $e->getMessage());
    }
}
