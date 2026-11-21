<?php
/**
 * Minimal SMTP client using raw sockets (AUTH LOGIN, STARTTLS/SSL). No external
 * dependencies — Composer/PEAR mail libraries aren't available in this hosting
 * environment, so this implements just enough of RFC 5321 for transactional email.
 * Falls back to PHP's built-in mail() if SMTP isn't configured in admin settings.
 */

function smtpConfigured() {
    return (bool)setting('smtp_host', '');
}

/** Последняя ошибка отправки — можно прочитать сразу после sendSystemEmail(). */
function lastEmailError() {
    global $__lastEmailError;
    return $__lastEmailError;
}

/** Пишет попытку отправки в email_log — best-effort, не должно ронять запрос. */
function logEmailAttempt($to, $subject, $status, $error = '') {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO email_log (recipient, subject, status, error) VALUES (?, ?, ?, ?)')
            ->execute([$to, $subject, $status, $error]);
    } catch (Throwable $e) {
        // журналирование не должно мешать отправке
    }
}

/**
 * Sends an email. Returns true on success, false on failure (never throws —
 * callers treat email as best-effort and shouldn't break the request on failure).
 */
function sendSystemEmail($to, $subject, $bodyHtml) {
    global $__lastEmailError;
    $__lastEmailError = null;

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $__lastEmailError = 'Некорректный email получателя';
        logEmailAttempt($to, $subject, 'invalid', $__lastEmailError);
        return false;
    }
    if (smtpConfigured()) {
        try {
            $ok = smtpSend($to, $subject, $bodyHtml);
            logEmailAttempt($to, $subject, 'sent');
            return $ok;
        } catch (Throwable $e) {
            $__lastEmailError = $e->getMessage();
            error_log('SMTP send failed: ' . $e->getMessage());
            logEmailAttempt($to, $subject, 'failed', $e->getMessage());
            return false;
        }
    }
    $fromName = setting('smtp_from_name', 'Galbraith');
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <" . ADMIN_EMAIL . ">\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . bin2hex(random_bytes(16)) . "@" . emailHeloDomain() . ">\r\n";
    $ok = @mail($to, encodeMimeSubject($subject), $bodyHtml, $headers);
    logEmailAttempt($to, $subject, $ok ? 'sent' : 'failed', $ok ? '' : 'mail() вернул false');
    if (!$ok) $__lastEmailError = 'Системная функция mail() вернула ошибку (проверьте настройки хостинга)';
    return $ok;
}

function encodeMimeSubject($subject) {
    return "=?UTF-8?B?" . base64_encode($subject) . "?=";
}

/**
 * Доменное имя для EHLO/HELO и Message-ID. Раньше здесь бездумно бралось
 * $_SERVER['HTTP_HOST'] — это ненадёжно (может быть 'localhost' в CLI/cron,
 * IP-адрес, или домен без валидной A/PTR-записи), а несовпадающий/странный
 * EHLO — один из сигналов, по которым спам-фильтры сильнее режут почту,
 * приходящую на "чужие" (не Yandex) почтовые сервисы. Приоритет:
 * 1) явно заданный в настройках домен, 2) домен из smtp_username (обычно
 * совпадает с доменом отправителя и всегда валиден), 3) домен сайта, 4) localhost.
 */
function emailHeloDomain() {
    $explicit = trim((string)setting('smtp_helo_domain', ''));
    if ($explicit !== '') return $explicit;
    $username = (string)setting('smtp_username', '');
    if (strpos($username, '@') !== false) {
        return substr($username, strpos($username, '@') + 1);
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        return preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
    }
    return 'localhost';
}

function smtpSend($to, $subject, $bodyHtml) {
    $host = setting('smtp_host');
    $port = (int)setting('smtp_port', 587);
    $security = setting('smtp_security', 'tls'); // tls | ssl | none
    $username = setting('smtp_username');
    $password = setting('smtp_password');
    $fromName = setting('smtp_from_name', 'Galbraith');
    $fromEmail = $username ?: ADMIN_EMAIL;
    $heloDomain = emailHeloDomain();

    $transport = $security === 'ssl' ? 'ssl://' : '';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { throw new Exception("Не удалось подключиться к $host:$port — $errstr"); }
    stream_set_timeout($fp, 15);

    $expect = function ($fp, $code) {
        $resp = '';
        do {
            $line = fgets($fp, 512);
            if ($line === false) break;
            $resp .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        if (substr($resp, 0, 3) !== (string)$code) {
            throw new Exception("SMTP ожидал $code, получил: " . trim($resp));
        }
        return $resp;
    };
    $send = function ($fp, $cmd) { fwrite($fp, $cmd . "\r\n"); };

    $expect($fp, 220);
    $send($fp, 'EHLO ' . $heloDomain);
    $expect($fp, 250);

    if ($security === 'tls') {
        $send($fp, 'STARTTLS');
        $expect($fp, 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('Не удалось установить TLS-соединение');
        }
        $send($fp, 'EHLO ' . $heloDomain);
        $expect($fp, 250);
    }

    if ($username) {
        $send($fp, 'AUTH LOGIN');
        $expect($fp, 334);
        $send($fp, base64_encode($username));
        $expect($fp, 334);
        $send($fp, base64_encode($password));
        $expect($fp, 235);
    }

    $send($fp, 'MAIL FROM:<' . $fromEmail . '>');
    $expect($fp, 250);
    $send($fp, 'RCPT TO:<' . $to . '>');
    $expect($fp, 250);
    $send($fp, 'DATA');
    $expect($fp, 354);

    // Date/Message-ID отсутствовали в письме — это одна из типичных причин, по
    // которым внешние спам-фильтры (Gmail, Mail.ru и т.п.) занижают доверие к
    // письму сильнее, чем внутренняя доставка Yandex → Yandex. Добавляем оба.
    $headers = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: " . encodeMimeSubject($subject) . "\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . bin2hex(random_bytes(16)) . "@" . $heloDomain . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $body = str_replace("\n.", "\n..", $bodyHtml); // dot-stuffing per RFC 5321
    $send($fp, $headers . "\r\n" . $body . "\r\n.");
    $expect($fp, 250);
    $send($fp, 'QUIT');
    fclose($fp);
    return true;
}
