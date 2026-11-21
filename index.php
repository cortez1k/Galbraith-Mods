<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

/**
 * VK Callback API — полностью изолированный вход.
 *
 * Этот блок обязан находиться ДО любых include сайта: Callback VK не должен
 * зависеть от сессии, OAuth, режима техработ, шаблонов или тяжёлых модулей.
 */
function galbraithVkDb() {
    static $db = null;
    if ($db !== null) return $db;
    $db = new SQLite3(__DIR__ . '/database.sqlite');
    $db->busyTimeout(5000);
    return $db;
}

function galbraithVkSetting($key, $default = '') {
    try {
        $db = galbraithVkDb();
        $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key LIMIT 1');
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        return $row && array_key_exists('value', $row) ? (string)$row['value'] : $default;
    } catch (Throwable $e) {
        error_log('VK webhook settings read failed: ' . $e->getMessage());
        return $default;
    }
}

function galbraithVkSetSetting($key, $value) {
    try {
        $db = galbraithVkDb();
        $stmt = $db->prepare('INSERT INTO settings (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':value', (string)$value, SQLITE3_TEXT);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('VK webhook setting write failed: ' . $e->getMessage());
    }
}

function galbraithVkApi($method, array $params = []) {
    $token = trim(galbraithVkSetting('vk_bot_token', ''));
    if ($token === '') return [null, 'VK token is empty'];
    $params['access_token'] = $token;
    $params['v'] = galbraithVkSetting('vk_api_version', '5.199');
    $url = 'https://api.vk.com/method/' . rawurlencode($method);
    $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Galbraith VK Bot/2.0',
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) return [null, 'curl: ' . ($err ?: 'network error')];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [null, 'VK returned invalid JSON (HTTP ' . $http . ')'];
    if (!empty($data['error'])) {
        $e = $data['error'];
        return [null, (string)($e['error_msg'] ?? 'VK API error') . ' (code ' . (int)($e['error_code'] ?? 0) . ')'];
    }
    return [$data['response'] ?? null, null];
}

function galbraithVkSend($peerId, $message) {
    $peerId = (int)$peerId;
    if ($peerId <= 0 || trim((string)$message) === '') return [false, 'invalid peer/message'];
    $params = [
        'peer_id' => $peerId,
        'random_id' => random_int(-2147483648, 2147483647),
        'message' => (string)$message,
    ];
    $groupId = (int)galbraithVkSetting('vk_group_id', '0');
    if ($groupId > 0) $params['group_id'] = $groupId;
    [$response, $error] = galbraithVkApi('messages.send', $params);
    if ($response === null) return [false, $error ?: 'VK messages.send failed'];
    return [true, $response];
}

function galbraithVkRememberContact($peerId, $userId = null) {
    $peerId = (int)$peerId;
    if ($peerId <= 0) return;
    try {
        $db = galbraithVkDb();
        $check = $db->prepare('SELECT id FROM vk_contacts WHERE peer_id = :peer LIMIT 1');
        $check->bindValue(':peer', (string)$peerId, SQLITE3_TEXT);
        $row = $check->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $stmt = $db->prepare('UPDATE vk_contacts SET last_seen_at=CURRENT_TIMESTAMP, user_id=COALESCE(:uid,user_id) WHERE peer_id=:peer');
            $stmt->bindValue(':uid', $userId ? (int)$userId : null, $userId ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':peer', (string)$peerId, SQLITE3_TEXT);
            $stmt->execute();
        } else {
            $stmt = $db->prepare('INSERT INTO vk_contacts(peer_id,user_id) VALUES(:peer,:uid)');
            $stmt->bindValue(':peer', (string)$peerId, SQLITE3_TEXT);
            $stmt->bindValue(':uid', $userId ? (int)$userId : null, $userId ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->execute();
        }
    } catch (Throwable $e) {
        error_log('VK contact save failed: ' . $e->getMessage());
    }
}

function galbraithVkProcessMessage(array $update) {
    // VK Callback API (5.x) sends message_new as:
    // object.message.peer_id / object.message.text.
    // Older integrations sometimes used object.peer_id / object.text,
    // so support both layouts.
    $object = is_array($update['object'] ?? null) ? $update['object'] : [];
    $message = is_array($object['message'] ?? null) ? $object['message'] : $object;

    $peerId = (int)($message['peer_id'] ?? $message['from_id'] ?? 0);
    $text = trim((string)($message['text'] ?? ''));
    if ($peerId <= 0) return;
    if ($text === '') return;

    galbraithVkRememberContact($peerId);

    $normalized = strtoupper(preg_replace('/\\s+/', '', $text));
    try {
        $db = galbraithVkDb();
        $stmt = $db->prepare("SELECT id FROM users WHERE UPPER(REPLACE(REPLACE(vk_bot_link_code,' ',''), CHAR(9), ''))=:code LIMIT 1");
        $stmt->bindValue(':code', $normalized, SQLITE3_TEXT);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ($user) {
            $uid = (int)$user['id'];
            $up = $db->prepare('UPDATE users SET vk_bot_peer_id=:peer, vk_bot_link_code=NULL WHERE id=:id');
            $up->bindValue(':peer', (string)$peerId, SQLITE3_TEXT);
            $up->bindValue(':id', $uid, SQLITE3_INTEGER);
            $up->execute();
            galbraithVkRememberContact($peerId, $uid);
            [$ok, $sendError] = galbraithVkSend($peerId, 'Готово! Уведомления Galbraith теперь будут приходить вам сюда.');
            if (!$ok) error_log('VK link reply failed: ' . $sendError);
            return;
        }
    } catch (Throwable $e) {
        error_log('VK message DB processing failed: ' . $e->getMessage());
    }

    [$ok, $sendError] = galbraithVkSend($peerId, 'Чтобы подключить уведомления, откройте профиль на сайте Galbraith → «VK-уведомления» → «Подключить». Затем отправьте боту полученный код.');
    if (!$ok) error_log('VK help reply failed: ' . $sendError);
}

function galbraithVkWebhook() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if ($method === 'GET') {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'VK_WEBHOOK_OK';
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST');
        exit;
    }

    $raw = file_get_contents('php://input');
    $update = json_decode($raw ?: '', true);
    if (!is_array($update)) {
        // VK лучше вернуть 200, чем заставлять его бесконечно повторять мусорный запрос.
        http_response_code(200);
        echo 'ok';
        exit;
    }

    $type = (string)($update['type'] ?? '');
    $incomingGroup = (int)($update['group_id'] ?? 0);
    $configuredGroup = (int)galbraithVkSetting('vk_group_id', '0');
    if ($configuredGroup > 0 && $incomingGroup > 0 && $configuredGroup !== $incomingGroup) {
        http_response_code(200);
        echo 'ok';
        exit;
    }

    if ($type === 'confirmation') {
        $code = trim(galbraithVkSetting('vk_confirmation_code', ''));
        if ($code === '') {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'VK confirmation code is not configured on the site';
            exit;
        }
        if ($incomingGroup > 0 && $configuredGroup <= 0) {
            galbraithVkSetSetting('vk_group_id', (string)$incomingGroup);
        }
        galbraithVkSetSetting('vk_callback_verified', '1');
        header('Content-Type: text/plain; charset=utf-8');
        echo $code;
        exit;
    }

    if ($type === 'message_new') {
        $secret = trim(galbraithVkSetting('vk_callback_secret', ''));
        if ($secret !== '' && !hash_equals($secret, (string)($update['secret'] ?? ''))) {
            error_log('VK webhook rejected: secret mismatch');
            http_response_code(200);
            echo 'ok';
            exit;
        }
        if ($incomingGroup > 0 && $configuredGroup <= 0) {
            galbraithVkSetSetting('vk_group_id', (string)$incomingGroup);
        }
        galbraithVkSetSetting('vk_callback_verified', '1');
        galbraithVkSetSetting('vk_bot_enabled', '1');

        // Обрабатываем сообщение в том же PHP-процессе.
        // На PHP-FPM/LiteSpeed после ACK продолжаем выполнение, а на обычном
        // PHP сначала обрабатываем событие, чтобы flush() не оборвал выполнение.
        ignore_user_abort(true);

        if (function_exists('fastcgi_finish_request')) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Length: 2');
            echo 'ok';
            fastcgi_finish_request();
            galbraithVkProcessMessage($update);
        } elseif (function_exists('litespeed_finish_request')) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Length: 2');
            echo 'ok';
            litespeed_finish_request();
            galbraithVkProcessMessage($update);
        } else {
            // Без fastcgi_finish_request() сначала выполняем бизнес-логику,
            // затем гарантированно отдаём VK корректный ACK.
            galbraithVkProcessMessage($update);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ok';
        }
        exit;
    }

    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ok';
    exit;
}

$galbraithVkPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($galbraithVkPath === 'vk/webhook') {
    galbraithVkWebhook();
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/oauth.php';
require_once __DIR__ . '/includes/marketplace.php';
require_once __DIR__ . '/includes/trader_bonus.php';
require_once __DIR__ . '/includes/email_auth.php';
require_once __DIR__ . '/includes/social.php';
require_once __DIR__ . '/includes/moderation.php';
require_once __DIR__ . '/includes/auto_delivery.php';
require_once __DIR__ . '/includes/maintenance.php';
require_once __DIR__ . '/includes/vk_bot.php';
require_once __DIR__ . '/includes/support.php';
require_once __DIR__ . '/includes/nick.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');

attemptRememberLogin();

captureReferralParam();

enforceMaintenanceMode($uri);

// Лёгкая проверка "не вышла ли премьера, на которую подписались" — без cron,
// на каждый запрос, как и остальные фоновые задачи в этом проекте.
processDuePremiereReminders();

$parts = explode('/', $uri);

// API / POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Seller: auto-delivery stock management
    // These endpoints are intentionally handled here because the stock page
    // submits POST requests to nested /seller/products/{id}/stock/... URLs.
    if (preg_match('/^seller\/products\/(\d+)\/stock\/(toggle|message|add|position)$/', $uri, $m)) {
        requireRole('trader');
        $productId = (int)$m[1];
        $action = $m[2];

        $stmt = getDB()->prepare('SELECT * FROM trader_products WHERE id = ? AND user_id = ?');
        $stmt->execute([$productId, $_SESSION['user_id']]);
        $product = $stmt->fetch();
        if (!$product) {
            redirect('/seller/products');
        }

        if ($action === 'toggle') {
            $enabled = !empty($product['auto_delivery']) ? 0 : 1;
            getDB()->prepare('UPDATE trader_products SET auto_delivery = ? WHERE id = ? AND user_id = ?')
                ->execute([$enabled, $productId, $_SESSION['user_id']]);
            $_SESSION['flash'] = $enabled ? 'Автовыдача включена' : 'Автовыдача выключена';
        } elseif ($action === 'message') {
            getDB()->prepare('UPDATE trader_products SET auto_delivery_message = ? WHERE id = ? AND user_id = ?')
                ->execute([trim((string)($_POST['auto_delivery_message'] ?? '')), $productId, $_SESSION['user_id']]);
            $_SESSION['flash'] = 'Сообщение для автовыдачи сохранено';
        } elseif ($action === 'add') {
            if (getAutoDeliveryContent($productId) !== '') {
                $_SESSION['flash'] = 'Сначала очистите постоянную позицию';
            } else {
                $count = addStockItems($productId, $_POST['items'] ?? '');
                $_SESSION['flash'] = $count > 0 ? 'Добавлено позиций: ' . $count : 'Не добавлено ни одной позиции';
            }
        } elseif ($action === 'position') {
            setConstantPosition($productId, $_POST['position'] ?? '');
            $_SESSION['flash'] = trim((string)($_POST['position'] ?? '')) !== ''
                ? 'Постоянная позиция сохранена'
                : 'Постоянная позиция очищена';
        }

        redirect('/seller/products/' . $productId . '/stock');
    }

    if (preg_match('/^seller\/products\/(\d+)\/stock\/(\d+)\/delete$/', $uri, $m)) {
        requireRole('trader');
        $productId = (int)$m[1];
        $stockId = (int)$m[2];

        $stmt = getDB()->prepare('SELECT id FROM trader_products WHERE id = ? AND user_id = ?');
        $stmt->execute([$productId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            redirect('/seller/products');
        }

        deleteStockItem($stockId, $productId);
        $_SESSION['flash'] = 'Позиция удалена';
        redirect('/seller/products/' . $productId . '/stock');
    }

    // Order POST routes: keep all order actions out of the GET router (prevents 404s).
    if (preg_match('/^orders\/(\d+)\/receipt-email$/', $uri, $m)) {
        requireLogin();
        $orderId = (int)$m[1];
        $order = getOrderById($orderId);
        $uid = (int)$_SESSION['user_id'];
        if (!$order || (int)$order['buyer_id'] !== $uid || !receiptStatusAllowed($order['status'])) {
            http_response_code(403);
            exit('Нет доступа к квитанции.');
        }
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash'] = 'Сессия формы истекла. Повторите попытку.';
            redirect('/orders/' . $orderId . '/receipt');
        }
        $email = trim((string)($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = 'Укажите корректный email.';
            redirect('/orders/' . $orderId . '/receipt');
        }
        $sent = sendSystemEmail(
            $email,
            'Квитанция по заказу #' . $orderId . ' — Galbraith',
            receiptHtml($order)
        );
        $_SESSION['flash'] = $sent ? 'Квитанция отправлена на ' . $email . '.' : 'Не удалось отправить письмо. Проверьте настройки почты.';
        redirect('/orders/' . $orderId . '/receipt');
    }

    // Orders: create an order from the product checkout form.
    if ($uri === 'orders/create') {
        requireLogin();
        $productId = (int)($_POST['product_id'] ?? 0);
        $bonusesToUse = (int)($_POST['bonuses_to_use'] ?? 0);
        $promoCode = trim((string)($_POST['promo_code'] ?? ''));
        $result = createOrder((int)$_SESSION['user_id'], $productId, $bonusesToUse, $promoCode);
        if (!empty($result['error'])) {
            $_SESSION['flash'] = $result['error'];
            $product = getTraderProductById($productId);
            redirect($product ? '/product/' . $product['id'] : '/marketplace');
        }
        redirect('/orders/' . (int)$result['order_id']);
    }

    // Order chat and buyer actions. These used to fall through to the GET
    // router, which resulted in a 404 on POST /orders/{id}/message.
    if (preg_match('/^orders\/(\d+)\/message$/', $uri, $m)) {
        requireLogin();
        $orderId = (int)$m[1];
        $order = getOrderById($orderId);
        $uid = (int)$_SESSION['user_id'];
        if (!$order || !in_array($uid, [(int)$order['buyer_id'], (int)$order['seller_id']], true)) {
            http_response_code(403);
            exit('Нет доступа к заказу.');
        }
        if (in_array($order['status'], ['completed', 'refunded', 'cancelled'], true)) {
            $_SESSION['flash'] = 'В этот заказ уже нельзя отправлять сообщения.';
            redirect('/orders/' . $orderId);
        }

        $message = trim((string)($_POST['message'] ?? ''));
        $filePath = null;
        if (!empty($_FILES['attachment']['tmp_name']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $mime = function_exists('mime_content_type') ? mime_content_type($_FILES['attachment']['tmp_name']) : ($_FILES['attachment']['type'] ?? '');
            if (!in_array($mime, $allowed, true) || (int)$_FILES['attachment']['size'] > 8 * 1024 * 1024) {
                $_SESSION['flash'] = 'Можно отправлять только изображения до 8 МБ.';
                redirect('/orders/' . $orderId);
            }
            $filePath = uploadFile($_FILES['attachment'], 'chat');
        }
        if ($message === '' && !$filePath) {
            $_SESSION['flash'] = 'Введите сообщение или прикрепите изображение.';
            redirect('/orders/' . $orderId);
        }
        addOrderMessage($orderId, $uid, $message, false, $filePath, false);
        redirect('/orders/' . $orderId);
    }

    if (preg_match('/^orders\/(\d+)\/dispute$/', $uri, $m)) {
        requireLogin();
        $orderId = (int)$m[1];
        if (!openDispute($orderId, (int)$_SESSION['user_id'])) {
            $_SESSION['flash'] = 'Не удалось открыть спор.';
        } else {
            $_SESSION['flash'] = 'Спор открыт. Администратор рассмотрит заказ.';
        }
        redirect('/orders/' . $orderId);
    }

    if (preg_match('/^orders\/(\d+)\/confirm$/', $uri, $m)) {
        requireLogin();
        $orderId = (int)$m[1];
        $order = getOrderById($orderId);
        if (!$order || (int)$order['buyer_id'] !== (int)$_SESSION['user_id']) {
            http_response_code(403);
            exit('Нет доступа к заказу.');
        }
        if (!completeOrder($orderId, 'buyer')) {
            $_SESSION['flash'] = 'Не удалось подтвердить получение товара.';
        } else {
            $_SESSION['flash'] = 'Получение товара подтверждено. Заказ завершён.';
        }
        redirect('/orders/' . $orderId);
    }

    if (preg_match('/^orders\/(\d+)\/review$/', $uri, $m)) {
        requireLogin();
        $orderId = (int)$m[1];
        $result = submitReview(
            $orderId,
            (int)$_SESSION['user_id'],
            (int)($_POST['rating'] ?? 5),
            (string)($_POST['comment'] ?? '')
        );
        $_SESSION['flash'] = $result['error'] ?? 'Отзыв отправлен.';
        redirect('/orders/' . $orderId);
    }


    // Registration by email
    if ($uri === 'register') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $_SESSION['register_error'] = 'Сессия формы истекла. Повторите попытку.'; redirect('/register'); }
        $result = registerWithEmail($_POST['login'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '', $_POST['password_confirm'] ?? '', !empty($_POST['personal_data_consent']));
        if (!empty($result['error'])) { $_SESSION['register_error'] = $result['error']; redirect('/register'); }
        $_SESSION['register_success'] = true;
        redirect('/register');
    }
    // Login by email/login + password
    if ($uri === 'login-email') {
        $result = loginWithEmail($_POST['identifier'] ?? '', $_POST['password'] ?? '');
        if (!empty($result['error'])) {
            $_SESSION['auth_error'] = $result['error'];
            if (!empty($result['unverified_user_id'])) { $_SESSION['unverified_user_id'] = $result['unverified_user_id']; }
            redirect('/login');
        }
        startLoginVerification($result['user_id']);
        redirect('/login-code');
    }
    if ($uri === 'login-code') {
        $uid=(int)($_SESSION['pending_login_user_id']??0); $started=(int)($_SESSION['pending_login_started']??0);
        if(!$uid || time()-$started>900){clearLoginVerification();$_SESSION['auth_error']='Сессия подтверждения входа истекла.';redirect('/login');}
        $user=getDB()->prepare('SELECT * FROM users WHERE id=?'); $user->execute([$uid]); $u=$user->fetch();
        if(!$u){clearLoginVerification();redirect('/login');}
        $emailOk=verifyLoginCode($uid,$_POST['email_code']??''); $totpOk=empty($u['twofa_enabled']) || verifyTOTPCode($u['totp_secret']??'',$_POST['totp_code']??'');
        if(!$emailOk || !$totpOk){$_SESSION['auth_error']='Неверный код подтверждения'.($emailOk?'':' из письма').($totpOk?'':' или 2FA');redirect('/login-code');}
        clearLoginVerification(); loginSiteUser($uid); redirect('/profile');
    }
    // Resend verification email
    if ($uri === 'resend-verification') {
        if (!empty($_SESSION['unverified_user_id'])) {
            sendVerificationEmail($_SESSION['unverified_user_id']);
            unset($_SESSION['unverified_user_id']);
            $_SESSION['auth_error'] = 'Письмо отправлено повторно, проверьте почту';
        }
        redirect('/login');
    }
    // Forgot password
    if ($uri === 'forgot-password') {
        requestPasswordReset($_POST['email'] ?? '');
        $_SESSION['forgot_sent'] = true;
        redirect('/forgot-password');
    }
    // Reset password
    if (preg_match('/^reset-password\/([a-f0-9]+)$/', $uri, $m)) {
        $result = resetPasswordWithToken($m[1], $_POST['password'] ?? '', $_POST['password_confirm'] ?? '');
        if (!empty($result['error'])) { $_SESSION['reset_error'] = $result['error']; redirect('/reset-password/' . $m[1]); }
        $_SESSION['reset_success'] = true;
        redirect('/login');
    }
    // Link email/password to an OAuth-only account
    if ($uri === 'profile/link-email') {
        requireLogin();
        $result = linkEmailPassword($_SESSION['user_id'], $_POST['email'] ?? '', $_POST['password'] ?? '', $_POST['password_confirm'] ?? '');
        $_SESSION['flash'] = $result['error'] ?? 'Email привязан. Проверьте почту для подтверждения.';
        redirect('/profile');
    }
    // Favorites toggle
    if (preg_match('/^favorites\/(\d+)\/toggle$/', $uri, $m)) {
        requireLogin();
        toggleFavorite($_SESSION['user_id'], (int)$m[1]);
        $back = $_POST['back'] ?? '/marketplace';
        redirect($back);
    }
    // Subscription toggle
    if (preg_match('/^subscribe\/(\d+)\/toggle$/', $uri, $m)) {
        requireLogin();
        toggleSubscription($_SESSION['user_id'], (int)$m[1]);
        $back = $_POST['back'] ?? '/marketplace';
        redirect($back);
    }
    // Comments
    if (preg_match('/^comments\/(\d+)$/', $uri, $m)) {
        $materialId = (int)$m[1];
        $mat = getMaterialById($materialId);
        $back = '/mod/' . ($mat ? $mat['slug'] : '');
        if (empty($_SESSION['user_id'])) redirect('/login');
        $u = currentUser();
        $content = trim($_POST['content'] ?? '');
        $content = mb_substr($content, 0, 500);
        $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
        if ($parentId) {
            $ps = getDB()->prepare('SELECT id FROM comments WHERE id = ? AND material_id = ?');
            $ps->execute([$parentId, $materialId]);
            if (!$ps->fetch()) $parentId = null;
        }
        if ($u && $mat && $content !== '' && !isBanned()) {
            addComment($materialId, mb_substr($u['name'] ?? 'Пользователь', 0, 30), $content, (int)$u['id'], $parentId);
        }
        redirect($back . '#comments');
    }
    if (preg_match('/^materials\/(\d+)\/remind$/', $uri, $m)) {
        $materialId = (int)$m[1];
        $mat = getMaterialById($materialId);
        $back = '/mod/' . ($mat ? $mat['slug'] : '');
        if (empty($_SESSION['user_id'])) redirect('/login');
        if ($mat && !empty($mat['publish_at']) && strtotime($mat['publish_at']) > time()) {
            togglePremiereReminder($materialId, (int)$_SESSION['user_id']);
        }
        redirect($back);
    }
    if (preg_match('/^materials\/(\d+)\/rate$/', $uri, $m)) {
        $materialId = (int)$m[1];
        $mat = getMaterialById($materialId);
        $back = '/mod/' . ($mat ? $mat['slug'] : '');
        if (empty($_SESSION['user_id'])) redirect('/login');
        $isPrem = $mat && !empty($mat['publish_at']) && strtotime($mat['publish_at']) > time();
        $rating = (int)($_POST['rating'] ?? 0);
        if ($mat && !$isPrem && $rating >= 1 && $rating <= 5 && !isBanned()) {
            rateMaterial($materialId, (int)$_SESSION['user_id'], $rating);
        }
        redirect($back . '#rating');
    }
    // Tickets
    if ($uri === 'tickets') {
        if (!isBanned()) {
            $db = getDB();
            $stmt = $db->prepare('INSERT INTO tickets (subject, contact, message) VALUES (?, ?, ?)');
            $stmt->execute([$_POST['subject'] ?? '', $_POST['contact'] ?? '', $_POST['message'] ?? '']);
        }
        redirect('/');
    }
    // Download a free trader product (task-gated)
    if (preg_match('/^product\/(\d+)\/download$/', $uri, $m)) {
        $product = getTraderProductById((int)$m[1]);
        if ($product && (float)$product['price'] <= 0 && $product['status'] === 'approved') {
            $required = array_column(getRequiredTasksForProduct($product['id']), 'id');
            $completed = json_decode($_COOKIE['completed_tasks'] ?? '[]', true) ?: [];
            if (!$required || count(array_intersect($required, $completed)) === count($required)) {
                getDB()->prepare('UPDATE trader_products SET downloads_count = downloads_count + 1 WHERE id = ?')->execute([$product['id']]);
                header('Location: ' . $product['file_url']);
                exit;
            }
        }
        redirect('/product/' . ($product['slug'] ?? ''));
    }
    if (preg_match('/^product\/(\d+)\/ask$/', $uri, $m)) {
        requireLogin();
        $product = getTraderProductById((int)$m[1]);
        if ($product && trim($_POST['message'] ?? '')) {
            sendSellerQuestion($product['user_id'], $_SESSION['user_id'], $product['id'], $_POST['message'], null);
            $_SESSION['flash'] = 'Чат создан. Открывайте его в сообщениях.';
        }
        redirect('/product/' . ($product['slug'] ?? ''));
    }
    if ($uri === 'chat/send') {
        requireLogin();
        header('Content-Type: application/json; charset=utf-8');
        $product=getTraderProductById((int)($_POST['product_id']??0)); $buyerId=(int)($_POST['buyer_id']??0); $uid=(int)$_SESSION['user_id'];
        if(!$product){echo json_encode(['success'=>false,'error'=>'Товар не найден']);exit;}
        $sellerId=(int)$product['user_id'];
        if($uid!==$sellerId && $uid!==$buyerId){echo json_encode(['success'=>false,'error'=>'Нет доступа к чату']);exit;}
        if($uid===$sellerId)$targetBuyer=$buyerId;else $targetBuyer=$uid;
        $path=null;
        if(!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error']===UPLOAD_ERR_OK){
            $allowed=['image/jpeg','image/png','image/webp','image/gif'];
            $mime=function_exists('mime_content_type')?mime_content_type($_FILES['attachment']['tmp_name']):($_FILES['attachment']['type']??'');
            if(!in_array($mime,$allowed,true) || $_FILES['attachment']['size']>8*1024*1024){echo json_encode(['success'=>false,'error'=>'Можно отправлять только изображения до 8 МБ']);exit;}
            $path=uploadFile($_FILES['attachment'],'chat');
        }
        $ok=sendSellerChatMessage($sellerId,$targetBuyer,$product['id'],$uid,$_POST['message']??'',$path,false);
        echo json_encode(['success'=>(bool)$ok,'error'=>$ok?'':'Введите сообщение или прикрепите изображение']);exit;
    }
    if ($uri === 'profile/update') {
        requireLogin();
        $r=saveUserProfile($_SESSION['user_id'], $_POST['name']??'', $_FILES['avatar']??null);
        $_SESSION['flash']=$r['error']??'Профиль обновлён'; redirect('/profile');
    }
    if ($uri === 'profile/2fa') {
        requireLogin(); $action=$_POST['action']??'';
        if($action==='enable' || $action==='start'){startUser2FA($_SESSION['user_id']);$_SESSION['flash']='Отсканируйте QR-код в приложении-аутентификаторе и введите код для подтверждения.';}
        elseif($action==='confirm'){
            if(confirmUser2FA($_SESSION['user_id'],$_POST['code']??'')){$_SESSION['flash']='2FA включена и подтверждена.';}
            else{$_SESSION['flash']='Неверный код. Попробуйте ещё раз (проверьте время на телефоне).';}
        }
        elseif($action==='cancel'){cancelUser2FA($_SESSION['user_id']);$_SESSION['flash']='Подключение 2FA отменено.';}
        elseif($action==='disable' && verifyTOTPCode(currentUser()['totp_secret']??'',$_POST['code']??'')){setUser2FA($_SESSION['user_id'],false);$_SESSION['flash']='2FA отключена.';}
        elseif($action==='disable'){$_SESSION['flash']='Неверный код 2FA.';}
        redirect('/profile');
    }
    // VK: сгенерировать код привязки / отвязать
    if ($uri === 'profile/vk/connect') {
        requireLogin();
        generateVkLinkCode($_SESSION['user_id']);
        redirect('/profile/settings');
    }
    if ($uri === 'profile/vk/disconnect') {
        requireLogin();
        vkBotDisconnect($_SESSION['user_id']);
        $_SESSION['flash'] = 'VK-уведомления отключены';
        redirect('/profile/settings');
    }
    if ($uri === 'profile/vk/login-toggle') {
        requireLogin();
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        getDB()->prepare('UPDATE users SET login_code_via_vk = ? WHERE id = ?')->execute([$enabled, $_SESSION['user_id']]);
        redirect('/profile/settings');
    }
    // Admin: change a user's role (generates 2FA secret automatically for moderator)
    if (preg_match('/^admin\/users\/(\d+)\/role$/', $uri, $m)) {
        requireAdmin();
        $db = getDB();
        $newRole = $_POST['role'] ?? 'user';
        if (in_array($newRole, ROLES, true)) {
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, (int)$m[1]]);
            if ($newRole === 'moderator') {
                $stmt = $db->prepare('SELECT totp_secret FROM users WHERE id = ?');
                $stmt->execute([(int)$m[1]]);
                if (empty($stmt->fetch()['totp_secret'] ?? null)) {
                    $secret = generateTOTPSecret();
                    $db->prepare('UPDATE users SET totp_secret = ?, twofa_enabled = 1 WHERE id = ?')->execute([$secret, (int)$m[1]]);
                }
            }
            logAdminAction('change_role', 'User ' . $m[1] . ' -> ' . $newRole);
        }
        redirect('/admin/users/' . $m[1]);
    }
    // Admin: set a user's extra roles (beyond the primary one) — replaces the whole set each save
    if (preg_match('/^admin\/users\/(\d+)\/extra-roles$/', $uri, $m)) {
        requireAdmin();
        $userId = (int)$m[1];
        $db = getDB();
        $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $primaryRole = $stmt->fetchColumn();
        $requested = array_values(array_intersect((array)($_POST['roles'] ?? []), ROLES));
        $requested = array_diff($requested, [$primaryRole]); // основную роль сюда не дублируем
        $db->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
        foreach ($requested as $role) {
            addExtraRole($userId, $role);
        }
        logAdminAction('set_extra_roles', 'User ' . $userId . ' -> ' . implode(',', $requested));
        redirect('/admin/users/' . $userId);
    }
    if (preg_match('/^admin\/users\/(\d+)\/delete$/', $uri, $m)) {
        requireAdmin();
        if ((int)$m[1] === (int)($_SESSION['admin_id'] ?? 0)) {
            $_SESSION['flash'] = 'Нельзя удалить собственный аккаунт';
            redirect('/admin/users');
        }
        deleteUserAccount((int)$m[1]);
        logAdminAction('delete_user', 'User ' . $m[1]);
        redirect('/admin/users');
    }
    if (preg_match('/^admin\/users\/(\d+)\/edit$/', $uri, $m)) {
        requireAdmin();
        $db = getDB();
        $commission = $_POST['commission_percent'] !== '' ? (float)$_POST['commission_percent'] : null;
        $db->prepare('UPDATE users SET full_name = ?, contact_info = ?, commission_percent = ? WHERE id = ?')
            ->execute([$_POST['full_name'] ?? '', $_POST['contact_info'] ?? '', $commission, (int)$m[1]]);
        if (!empty($_POST['card_number'])) {
            $db->prepare('UPDATE users SET card_number = ? WHERE id = ?')->execute([encryptSecret($_POST['card_number']), (int)$m[1]]);
        }
        logAdminAction('edit_user', 'User ' . $m[1]);
        redirect('/admin/users/' . $m[1]);
    }
    // Admin: update marketplace/escrow/bonus settings
    if ($uri === 'admin/settings/marketplace') {
        requireAdmin();
        foreach (['commission_percent', 'auto_confirm_days', 'bonus_enabled', 'bonus_threshold', 'bonus_rub_per_point', 'become_trader_text'] as $key) {
            if (isset($_POST[$key])) setSetting($key, $_POST[$key]);
        }
        setSetting('bonus_enabled', isset($_POST['bonus_enabled']) ? '1' : '0');
        logAdminAction('update_marketplace_settings', '');
        redirect('/admin/settings');
    }
    // Admin: update v2.1 trader-bonus / referral / discipline / marketplace settings
    if ($uri === 'admin/settings/trader-bonus') {
        requireAdmin();
        $keys = [
            'trader_bonus_enabled', 'tb_per_completed_order', 'tb_per_order_over_amount', 'tb_per_order_over_bonus',
            'tb_per_positive_review', 'tb_monthly_active_products_threshold', 'tb_monthly_active_products_bonus',
            'tb_monthly_orders30_threshold', 'tb_monthly_orders30_bonus', 'tb_per_dispute_won',
            'referral_days', 'referral_first_purchase_bonus', 'referral_purchase_threshold_amount', 'referral_purchase_threshold_bonus',
            'referral_trader_signup_bonus', 'referral_3purchases_bonus',
            'cost_commission_reduction', 'cost_free_withdrawal', 'cost_top_rank', 'top_rank_days',
            'bonus_to_rub_rate', 'withdrawal_fee_percent',
            'penalty_expiry_days', 'penalty_max_active', 'penalty_commission_step', 'penalty_max_commission',
            'max_pinned_products',
        ];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) setSetting($key, $_POST[$key]);
        }
        setSetting('trader_bonus_enabled', isset($_POST['trader_bonus_enabled']) ? '1' : '0');
        logAdminAction('update_trader_bonus_settings', '');
        redirect('/admin/settings');
    }
    // Seller: activate a bonus-shop option
    if ($uri === 'seller/bonuses/activate') {
        requireRole('trader');
        $result = activateBonusOption($_SESSION['user_id'], $_POST['option_type'] ?? '', (int)($_POST['qty'] ?? 1));
        $_SESSION['flash'] = $result['error'] ?? 'Опция активирована';
        redirect('/seller/bonuses');
    }
    // Admin: create/update a bonus task
    if ($uri === 'admin/bonus-tasks/create' || preg_match('/^admin\/bonus-tasks\/(\d+)\/edit$/', $uri, $m)) {
        requireAdmin();
        $db = getDB();
        $data = [
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? ''),
            $_POST['condition_type'] ?? 'orders_completed',
            (int)($_POST['condition_value'] ?? 1),
            (float)($_POST['bonus_amount'] ?? 0),
            $_POST['deadline_days'] !== '' ? (int)$_POST['deadline_days'] : null,
            isset($_POST['is_active']) ? 1 : 0,
            isset($_POST['is_repeatable']) ? 1 : 0,
        ];
        if (!empty($m[1])) {
            $db->prepare('UPDATE bonus_tasks SET title=?, description=?, condition_type=?, condition_value=?, bonus_amount=?, deadline_days=?, is_active=?, is_repeatable=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute(array_merge($data, [(int)$m[1]]));
            logAdminAction('update_bonus_task', 'ID ' . $m[1]);
        } else {
            $db->prepare('INSERT INTO bonus_tasks (title, description, condition_type, condition_value, bonus_amount, deadline_days, is_active, is_repeatable) VALUES (?,?,?,?,?,?,?,?)')
                ->execute($data);
            logAdminAction('create_bonus_task', $data[0]);
        }
        redirect('/admin/bonus-tasks');
    }
    // Admin: manually add a referral link
    if ($uri === 'admin/referrals/add') {
        requireAdmin();
        $days = (int)setting('referral_days', 30);
        try {
            getDB()->prepare('INSERT INTO referrals (referrer_id, referred_user_id, expires_at, status) VALUES (?, ?, ?, "active")')
                ->execute([(int)$_POST['referrer_id'], (int)$_POST['referred_user_id'], date('Y-m-d H:i:s', time() + $days * 86400)]);
            getDB()->prepare('UPDATE users SET referred_by = ? WHERE id = ?')->execute([(int)$_POST['referrer_id'], (int)$_POST['referred_user_id']]);
            logAdminAction('add_referral', $_POST['referrer_id'] . ' -> ' . $_POST['referred_user_id']);
        } catch (Throwable $e) {
            $_SESSION['flash'] = 'Не удалось создать связь (возможно, у пользователя уже есть реферер)';
        }
        redirect('/admin/referrals');
    }
    // Admin: marketplace ordering — pin/unpin/move
    if (preg_match('/^admin\/marketplace\/(\d+)\/pin$/', $uri, $m)) {
        requireAdmin();
        $r = pinProduct((int)$m[1]);
        if (!empty($r['error'])) $_SESSION['flash'] = $r['error'];
        logAdminAction('pin_product', 'ID ' . $m[1]);
        redirect('/admin/marketplace');
    }
    if (preg_match('/^admin\/marketplace\/(\d+)\/unpin$/', $uri, $m)) {
        requireAdmin();
        unpinProduct((int)$m[1]);
        logAdminAction('unpin_product', 'ID ' . $m[1]);
        redirect('/admin/marketplace');
    }
    if (preg_match('/^admin\/marketplace\/(\d+)\/move$/', $uri, $m)) {
        requireAdmin();
        moveProductOrder((int)$m[1], $_POST['direction'] ?? 'up');
        logAdminAction('reorder_product', 'ID ' . $m[1] . ' ' . ($_POST['direction'] ?? ''));
        redirect('/admin/marketplace');
    }
    if (preg_match('/^admin\/marketplace\/(\d+)\/toggle$/', $uri, $m)) {
        requireAdmin();
        getDB()->prepare("UPDATE trader_products SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ? AND status = 'approved'")
            ->execute([(int)$m[1]]);
        logAdminAction('toggle_product_visibility', 'ID ' . $m[1]);
        redirect('/admin/marketplace');
    }
    // Admin: trader bonus adjust, penalties, delete/restore trader
    if (preg_match('/^admin\/traders\/(\d+)\/bonus-adjust$/', $uri, $m)) {
        requireAdmin();
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount != 0) {
            addTraderBonus((int)$m[1], $amount, 'manual', $_POST['reason'] ?: 'Начисление администратором');
            logAdminAction('adjust_trader_bonus', 'User ' . $m[1] . ': ' . $amount);
        }
        redirect('/admin/traders/' . $m[1]);
    }
    if (preg_match('/^admin\/traders\/(\d+)\/penalty\/issue$/', $uri, $m)) {
        requireAdmin();
        issuePenalty((int)$m[1], $_POST['reason'] ?? '', $_POST['comment'] ?? '', $_SESSION['admin_id']);
        logAdminAction('issue_penalty', 'User ' . $m[1] . ': ' . ($_POST['reason'] ?? ''));
        redirect('/admin/traders/' . $m[1]);
    }
    if (preg_match('/^admin\/traders\/(\d+)\/penalty\/(\d+)\/remove$/', $uri, $m)) {
        requireAdmin();
        removePenalty((int)$m[2], $_POST['comment'] ?? 'Снято администратором');
        logAdminAction('remove_penalty', 'Penalty ' . $m[2]);
        redirect('/admin/traders/' . $m[1]);
    }
    if (preg_match('/^admin\/traders\/(\d+)\/penalty\/remove-one$/', $uri, $m)) {
        requireAdmin();
        removeOnePenaltyForUser((int)$m[1]);
        logAdminAction('give_chance', 'User ' . $m[1]);
        redirect('/admin/traders/' . $m[1]);
    }
    if (preg_match('/^admin\/traders\/(\d+)\/delete-trader$/', $uri, $m)) {
        requireAdmin();
        deleteTrader((int)$m[1]);
        logAdminAction('delete_trader', 'User ' . $m[1]);
        redirect('/admin/traders/' . $m[1]);
    }
    if (preg_match('/^admin\/traders\/(\d+)\/restore-trader$/', $uri, $m)) {
        requireAdmin();
        restoreTrader((int)$m[1]);
        logAdminAction('restore_trader', 'User ' . $m[1]);
        redirect('/admin/traders/' . $m[1]);
    }
    // Admin: approve/reject a product on moderation
    if (preg_match('/^admin\/moderation\/(\d+)\/approve$/', $uri, $m)) {
        requireAdmin();
        approveProduct((int)$m[1]);
        logAdminAction('approve_product', 'ID ' . $m[1]);
        redirect('/admin/moderation/' . $m[1]);
    }
    if (preg_match('/^admin\/moderation\/(\d+)\/reject$/', $uri, $m)) {
        requireAdmin();
        $comment = trim($_POST['comment'] ?? '');
        if ($comment === '') { $_SESSION['flash'] = 'Укажите причину отклонения'; redirect('/admin/moderation/' . $m[1]); }
        rejectProduct((int)$m[1], $comment);
        logAdminAction('reject_product', 'ID ' . $m[1] . ': ' . $comment);
        redirect('/admin/moderation/' . $m[1]);
    }
    // Admin: edit a product's fields directly during moderation (no re-notification to seller)
    if (preg_match('/^admin\/moderation\/(\d+)\/edit$/', $uri, $m)) {
        requireAdmin();
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM trader_products WHERE id = ?');
        $stmt->execute([(int)$m[1]]);
        $oldProduct = $stmt->fetch();
        if ($oldProduct) {
            $db->prepare('UPDATE trader_products SET title=?, description=?, category_id=?, price=?, file_url=?, youtube_url=?, game_version=?, file_size=?, version=version+1, updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([trim($_POST['title'] ?? ''), $_POST['description'] ?? '', $_POST['category_id'] ?: null, (float)($_POST['price'] ?? 0), $_POST['file_url'] ?? '', $_POST['youtube_url'] ?: null, $_POST['game_version'] ?: null, $_POST['file_size'] ?: null, (int)$m[1]]);
            $stmt = $db->prepare('SELECT * FROM trader_products WHERE id = ?');
            $stmt->execute([(int)$m[1]]);
            logProductHistory((int)$m[1], $_SESSION['admin_id'], $oldProduct, $stmt->fetch());
            logAdminAction('edit_product_moderation', 'ID ' . $m[1]);
        }
        redirect('/admin/moderation/' . $m[1]);
    }
    // Admin: set which subscription tasks are required for a specific free product
    if (preg_match('/^admin\/moderation\/(\d+)\/tasks$/', $uri, $m)) {
        requireAdmin();
        setProductTasks((int)$m[1], $_POST['task_ids'] ?? []);
        logAdminAction('set_product_tasks', 'ID ' . $m[1]);
        $_SESSION['flash'] = 'Задания для товара обновлены';
        redirect('/admin/moderation/' . $m[1]);
    }
    // Admin: SMTP settings
    if ($uri === 'admin/settings/smtp') {
        requireAdmin();
        foreach (['smtp_host', 'smtp_port', 'smtp_security', 'smtp_username', 'smtp_password', 'smtp_from_name', 'smtp_helo_domain'] as $key) {
            if (isset($_POST[$key])) setSetting($key, $_POST[$key]);
        }
        logAdminAction('update_smtp_settings', '');
        redirect('/admin/settings');
    }
    // Admin: SMTP test email
    if ($uri === 'admin/settings/smtp-test') {
        requireAdmin();
        $testTo = trim($_POST['test_email'] ?? '');
        $ok = false;
        if (filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $ok = sendSystemEmail($testTo, 'Тестовое письмо с Galbraith', '<p>Это тестовое письмо. Если вы его получили — SMTP настроен правильно и доставка на этот домен работает.</p>');
        }
        logAdminAction('smtp_test_email', $testTo . ' — ' . ($ok ? 'ok' : 'fail'));
        $params = $ok ? ['smtp_test' => 'ok', 'to' => $testTo] : ['smtp_test' => 'fail', 'err' => lastEmailError() ?: 'некорректный email'];
        redirect('/admin/settings?' . http_build_query($params));
    }
    // Live-предпросмотр цены в модалке покупки: промокод + бонусы + сумма для Donation Alerts
    if ($uri === 'api/order-preview') {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['user_id'])) { echo json_encode(['error' => 'Войдите в аккаунт']); exit; }
        $preview = previewOrderPricing(
            (int)($_POST['product_id'] ?? 0),
            $_SESSION['user_id'],
            (int)($_POST['bonuses_to_use'] ?? 0),
            $_POST['promo_code'] ?? ''
        );
        echo json_encode($preview, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($uri === 'admin/settings/da-commission') {
        requireAdmin();
        $pct = max(0, min(90, (float)($_POST['da_commission_percent'] ?? 15)));
        setSetting('da_commission_percent', (string)$pct);
        logAdminAction('update_da_commission', (string)$pct);
        redirect('/admin/settings');
    }
    // Admin: save/check VK Community Bot
    if ($uri === 'admin/settings/vk-bot') {
        requireAdmin();
        setSetting('vk_bot_token', trim($_POST['vk_bot_token'] ?? ''));
        setSetting('vk_callback_secret', trim($_POST['vk_callback_secret'] ?? ''));
        setSetting('vk_confirmation_code', trim($_POST['vk_confirmation_code'] ?? ''));
        setSetting('vk_bot_enabled', '0');
        setSetting('vk_callback_verified', '0');
        logAdminAction('update_vk_bot_token', '');
        redirect('/admin/settings');
    }
    if ($uri === 'admin/settings/vk-bot-check') {
        requireAdmin();
        $group = vkBotCheck();
        if ($group) {
            logAdminAction('vk_bot_check', 'id=' . ($group['id'] ?? '') . ' name=' . ($group['name'] ?? ''));
            redirect('/admin/settings?vk_check=ok');
        }
        logAdminAction('vk_bot_check', 'fail: ' . lastVkBotError());
        redirect('/admin/settings?vk_check=fail&err=' . urlencode(lastVkBotError() ?: ''));
    }
    // Admin: VK broadcast — создание очереди
    if ($uri === 'admin/vk-broadcast/create') {
        requireAdmin();
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $_SESSION['vk_broadcast_result'] = ['ok'=>false,'error'=>'Сессия формы истекла. Обновите страницу.']; redirect('/admin/vk-broadcast'); }
        $result = vkBroadcastCreate(
            trim((string)($_POST['title'] ?? '')),
            trim((string)($_POST['message'] ?? '')),
            trim((string)($_POST['url'] ?? '')),
            (int)($_SESSION['user_id'] ?? 0)
        );
        $_SESSION['vk_broadcast_result'] = $result['ok']
            ? ['ok'=>true, 'message'=>'Рассылка создана и поставлена в очередь.', 'total'=>$result['total']]
            : $result;
        if (!empty($result['ok'])) {
            logAdminAction('vk_broadcast_create', 'id=' . (int)$result['broadcast_id'] . ' total=' . (int)$result['total']);
            redirect('/admin/vk-broadcast?broadcast_id=' . (int)$result['broadcast_id']);
        }
        redirect('/admin/vk-broadcast');
    }
    // Admin: синхронизация диалогов сообщества с VK API.
    if ($uri === 'admin/vk-broadcast/sync') {
        requireAdmin();
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $_SESSION['vk_broadcast_result'] = ['ok'=>false,'error'=>'Сессия формы истекла.']; redirect('/admin/vk-broadcast'); }
        $sync = vkSyncConversationContacts(50);
        $_SESSION['vk_broadcast_result'] = $sync['ok']
            ? ['ok'=>true, 'message'=>'Диалоги VK синхронизированы. Найдено: ' . (int)$sync['seen'] . ', новых: ' . (int)$sync['added']]
            : ['ok'=>false, 'error'=>$sync['error'] ?: 'VK не вернул список диалогов'];
        redirect('/admin/vk-broadcast');
    }
    // Admin: пакетная обработка рассылки. Долгая операция выполняется без блокировки PHP-сессии,
    // поэтому кнопку паузы можно нажать, пока пакет ещё обрабатывается.
    if ($uri === 'admin/vk-broadcast/process') {
        requireAdmin();
        header('Content-Type: application/json; charset=utf-8');
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'CSRF-сессия истекла'], JSON_UNESCAPED_UNICODE); exit; }
        $id = (int)($_POST['id'] ?? 0);
        session_write_close();
        echo json_encode(vkBroadcastProcess($id, 15), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($uri === 'admin/vk-broadcast/pause' || $uri === 'admin/vk-broadcast/resume') {
        requireAdmin();
        header('Content-Type: application/json; charset=utf-8');
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'CSRF-сессия истекла'], JSON_UNESCAPED_UNICODE); exit; }
        $id = (int)($_POST['id'] ?? 0);
        $broadcast = $uri === 'admin/vk-broadcast/pause' ? vkBroadcastPause($id) : vkBroadcastResume($id);
        echo json_encode(['ok'=>(bool)$broadcast, 'broadcast'=>$broadcast], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Admin: buyer-bonus settings (v3.0 refinement)
    if ($uri === 'admin/settings/buyer-bonus') {
        requireAdmin();
        foreach (['bonus_threshold', 'bonus_rub_per_point', 'buyer_bonus_max_percent'] as $key) {
            if (isset($_POST[$key])) setSetting($key, $_POST[$key]);
        }
        setSetting('bonus_enabled', isset($_POST['bonus_enabled']) ? '1' : '0');
        logAdminAction('update_buyer_bonus_settings', '');
        redirect('/admin/settings');
    }
    // Admin: maintenance mode
    if ($uri === 'admin/settings/maintenance') {
        requireAdmin();
        $action = $_POST['maintenance_action'] ?? 'update';
        setSetting('maintenance_bypass_code', trim($_POST['maintenance_bypass_code'] ?? ''));
        if ($action === 'enable') {
            enableMaintenanceMode(trim($_POST['maintenance_message'] ?? ''));
            logAdminAction('enable_maintenance_mode', '');
        } elseif ($action === 'disable') {
            disableMaintenanceMode();
            logAdminAction('disable_maintenance_mode', '');
        } else {
            setSetting('maintenance_message', trim($_POST['maintenance_message'] ?? ''));
            logAdminAction('update_maintenance_settings', '');
        }
        redirect('/admin/settings');
    }
    // Download track
    if (preg_match('/^download\/(\d+)\/track$/', $uri, $m)) {
        $materialId = (int)$m[1];
        // Премьера: до наступления publish_at скачивание закрыто на сервере
        $premMat = getMaterialById($materialId);
        if ($premMat && !empty($premMat['publish_at']) && strtotime($premMat['publish_at']) > time()) {
            redirect('/mod/' . $premMat['slug']);
        }
        $completed = json_decode($_COOKIE['completed_tasks'] ?? '[]', true);
        $required = array_column(getTasks(), 'id');
        $allDone = count(array_intersect($required, $completed)) === count($required);
        if ($allDone) {
            $db = getDB();
            $db->prepare('UPDATE materials SET downloads_count = downloads_count + 1 WHERE id = ?')->execute([$materialId]);
            if (!empty($_SESSION['user_id'])) {
                try {
                    $db->prepare('INSERT INTO admin_material_downloads (user_id, material_id) VALUES (?, ?)')->execute([$_SESSION['user_id'], $materialId]);
                } catch (Throwable $e) { /* already logged for this user+material, ignore */ }
                evaluateTraderTasks($_SESSION['user_id']);
            }
            $mat = getMaterialById($materialId);
            if ($mat) { header('Location: ' . $mat['file_url']); exit; }
        }
        redirect('/');
    }
}

// Routes
if ($uri === '' || $uri === 'index.php') {
    require __DIR__ . '/pages/home.php';
} elseif ($uri === 'generator-nikov' || strpos($uri, 'generator-nikov/') === 0) {
    $nickSlug = $uri === 'generator-nikov' ? '' : substr($uri, strlen('generator-nikov/'));
    $nickAll = nickProjects();
    if (!isset($nickAll[$nickSlug]) || ($nickSlug === '' && $uri !== 'generator-nikov')) {
        http_response_code(404);
        $pageTitle = '404';
        include __DIR__ . '/templates/header.php';
        echo '<div style="text-align:center;padding:80px 16px;"><h1 style="font-size:48px;font-weight:700;margin-bottom:16px;">404</h1><p style="color:var(--g-text-secondary);">Страница не найдена</p><a href="/generator-nikov" class="btn btn-primary" style="margin-top:24px;">К генератору ников</a></div>';
        include __DIR__ . '/templates/footer.php';
    } else {
        require __DIR__ . '/pages/nick.php';
    }
} elseif (strpos($uri, 'api/nick-') === 0) {
    nickHandleApi($uri);
} elseif ($uri === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo galbraithSitemapXml();
    exit;
} elseif ($uri === 'robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo galbraithRobotsTxt();
    exit;
} elseif ($uri === 'admin/nicks') {
    requireAdmin();
    require __DIR__ . '/admin/nicks.php';
} elseif ($uri === 'fixed') {
    require __DIR__ . '/pages/fixed.php';
} elseif ($uri === 'amazing') {
    require __DIR__ . '/pages/amazing.php';
} elseif ($uri === 'catalog') {
    require __DIR__ . '/pages/catalog.php';
} elseif ($uri === 'login-code') {
    if(empty($_SESSION['pending_login_user_id'])) redirect('/login'); require __DIR__ . '/pages/login-code.php';
} elseif ($uri === 'login') {
    require __DIR__ . '/pages/login.php';
} elseif ($uri === 'register') {
    require __DIR__ . '/pages/register.php';
} elseif (preg_match('/^verify-email\/([a-f0-9]+)$/', $uri, $m)) {
    $result = verifyEmailToken($m[1]);
    $_SESSION['verify_result'] = $result;
    if (!empty($result['user_id'])) { loginSiteUser($result['user_id']); }
    require __DIR__ . '/pages/verify-email.php';
} elseif ($uri === 'forgot-password') {
    require __DIR__ . '/pages/forgot-password.php';
} elseif (preg_match('/^reset-password\/([a-f0-9]+)$/', $uri, $m)) {
    $_GET['token'] = $m[1];
    require __DIR__ . '/pages/reset-password.php';
} elseif (preg_match('/^notifications\/(\d+)$/',$uri,$m)) {
    requireLogin(); $st=getDB()->prepare('SELECT * FROM notifications WHERE id=? AND user_id=?');$st->execute([(int)$m[1],$_SESSION['user_id']]);$n=$st->fetch();if(!$n)redirect('/profile');getDB()->prepare('UPDATE notifications SET is_read=1 WHERE id=?')->execute([$n['id']]);$data=json_decode($n['data']??'{}',true)?:[];$url='/profile';if(!empty($data['order_id']))$url='/orders/'.(int)$data['order_id'];elseif($n['type']==='seller_question')$url=hasRole('trader')?'/seller/messages':'/profile';redirect($url);
} elseif ($uri === 'notifications/read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    getDB()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$_SESSION['user_id']]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
} elseif ($uri === 'api/order-chat') {
    requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $o = getOrderById((int)($_GET['id'] ?? 0));
    $uid = (int)$_SESSION['user_id'];
    if (!$o || !in_array($uid, [(int)$o['buyer_id'], (int)$o['seller_id']], true)) {
        echo json_encode(['success'=>false]); exit;
    }
    $html = renderOrderChatMessages(getOrderMessages($o['id']), $uid);
    echo json_encode(['success'=>true,'html'=>$html], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
} elseif ($uri === 'api/chat') {
    requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $product=getTraderProductById((int)($_GET['product_id']??0)); $buyerId=(int)($_GET['buyer_id']??0); $uid=(int)$_SESSION['user_id'];
    if(!$product || ($uid!==(int)$product['user_id'] && $uid!==$buyerId)){echo json_encode(['success'=>false,'error'=>'Нет доступа']);exit;}
    $msgs=getSellerChatMessages($product['id'],$buyerId,(int)$product['user_id']); $out=[];
    foreach($msgs as $m){$out[]=['id'=>(int)$m['id'],'sender_id'=>(int)$m['sender_id'],'sender_name'=>$m['sender_name']??'Пользователь','message'=>$m['message']??'','is_admin'=>(int)($m['is_admin']??0),'time'=>date('H:i',strtotime($m['created_at'])),'attachment_path'=>$m['attachment_path']??null,'attachment_url'=>!empty($m['attachment_path'])?UPLOAD_URL.$m['attachment_path']:null];}
    echo json_encode(['success'=>true,'user_id'=>$uid,'messages'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
} elseif ($uri === 'chat') {
    requireLogin(); require __DIR__ . '/pages/chat.php';
} elseif ($uri === 'api/support-chat') {
    requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $chatId = (int)($_GET['chat_id'] ?? 0);
    $chat = getSupportChatById($chatId);
    $uid = (int)$_SESSION['user_id'];
    $isStaff = in_array(staffRole(), ['admin', 'support'], true) && checkAdminSession();
    if (!$chat || (!$isStaff && (int)$chat['user_id'] !== $uid)) { echo json_encode(['success' => false, 'error' => 'Нет доступа']); exit; }
    $includeInternal = $isStaff;
    $msgs = getSupportChatMessages($chatId, $includeInternal);
    $out = [];
    foreach ($msgs as $m) {
        $out[] = ['id' => (int)$m['id'], 'sender_name' => $m['sender_name'] ?? 'Пользователь', 'message' => $m['message'] ?? '', 'is_agent' => (int)$m['is_agent'], 'is_internal_note' => (int)$m['is_internal_note'], 'time' => date('H:i', strtotime($m['created_at'])), 'attachment_path' => $m['attachment_path'] ?? null, 'attachment_url' => !empty($m['attachment_path']) ? UPLOAD_URL . $m['attachment_path'] : null];
    }
    echo json_encode(['success' => true, 'messages' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
} elseif ($uri === 'api/support-widget') {
    // Виджет поддержки: чат создаётся лениво — только когда пользователь пишет первое сообщение.
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id'])) { echo json_encode(['success' => true, 'guest' => true, 'messages' => []]); exit; }
    $st = getDB()->prepare('SELECT * FROM support_chats WHERE user_id = ?'); $st->execute([(int)$_SESSION['user_id']]);
    $chat = $st->fetch(); $out = [];
    if ($chat) foreach (getSupportChatMessages($chat['id'], false) as $m) {
        $out[] = ['id' => (int)$m['id'], 'is_agent' => (int)$m['is_agent'], 'message' => $m['message'] ?? '', 'time' => date('H:i', strtotime($m['created_at'])), 'attachment_url' => !empty($m['attachment_path']) ? UPLOAD_URL . $m['attachment_path'] : null];
    }
    echo json_encode(['success' => true, 'guest' => false, 'messages' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
} elseif ($uri === 'api/support-widget/send') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false, 'error' => 'Войдите, чтобы написать в поддержку']); exit; }
    if (isBanned()) { echo json_encode(['success' => false, 'error' => 'Доступ ограничен']); exit; }
    $message = mb_substr(trim($_POST['message'] ?? ''), 0, 2000);
    if ($message === '') { echo json_encode(['success' => false, 'error' => 'Введите сообщение']); exit; }
    $chat = getOrCreateSupportChat((int)$_SESSION['user_id']);
    addSupportMessage($chat['id'], (int)$_SESSION['user_id'], $message, null, false);
    echo json_encode(['success' => true]); exit;
} elseif ($uri === 'support') {
    requireLogin(); require __DIR__ . '/pages/support.php';
} elseif ($uri === 'support/send') {
    requireLogin();
    header('Content-Type: application/json; charset=utf-8');
    $chatId = (int)($_POST['chat_id'] ?? 0);
    $chat = getSupportChatById($chatId);
    $uid = (int)$_SESSION['user_id'];
    if (!$chat || (int)$chat['user_id'] !== $uid) { echo json_encode(['success' => false, 'error' => 'Нет доступа']); exit; }
    $message = trim($_POST['message'] ?? '');
    $attachment = !empty($_FILES['attachment']['name']) ? uploadFile($_FILES['attachment'], 'chat') : null;
    if ($message === '' && !$attachment) { echo json_encode(['success' => false, 'error' => 'Введите сообщение или прикрепите изображение']); exit; }
    addSupportMessage($chatId, $uid, $message, $attachment, false);
    echo json_encode(['success' => true]); exit;
} elseif ($uri === 'support/chats') {
    requireSupportStaff();
    require __DIR__ . '/support/chats.php';
} elseif (preg_match('/^support\/chats\/(new|mine|closed)$/', $uri, $m)) {
    requireSupportStaff();
    $_GET['tab'] = $m[1];
    require __DIR__ . '/support/chats.php';
} elseif (preg_match('/^support\/chats\/(\d+)\/view$/', $uri, $m)) {
    requireSupportStaff();
    $_GET['id'] = $m[1];
    require __DIR__ . '/support/chat_view.php';
} elseif (preg_match('/^support\/chats\/(\d+)\/claim$/', $uri, $m)) {
    requireSupportStaff();
    claimSupportChat((int)$m[1], $_SESSION['admin_id']);
    redirect('/support/chats/' . $m[1] . '/view');
} elseif (preg_match('/^support\/chats\/(\d+)\/close$/', $uri, $m)) {
    requireSupportStaff();
    closeSupportChat((int)$m[1]);
    redirect('/support/chats/mine');
} elseif (preg_match('/^support\/chats\/(\d+)\/reply$/', $uri, $m)) {
    requireSupportStaff();
    $chatId = (int)$m[1];
    $isNote = !empty($_POST['is_note']);
    $message = trim($_POST['message'] ?? '');
    $attachment = !empty($_FILES['attachment']['name']) ? uploadFile($_FILES['attachment'], 'chat') : null;
    if ($message !== '' || $attachment) {
        addSupportMessage($chatId, $_SESSION['admin_id'], $message, $attachment, true, $isNote);
    }
    redirect('/support/chats/' . $chatId . '/view');
} elseif ($uri === 'api/notifications') {
    requireLogin(); header('Content-Type: application/json; charset=utf-8'); $items=getNotifications($_SESSION['user_id'],30); $out=[];
    foreach($items as $n){$data=json_decode($n['data']??'{}',true)?:[];$out[]=['id'=>(int)$n['id'],'type'=>$n['type'],'title'=>notificationSubject($n['type']),'text'=>notificationPreview($n['type'],$data),'icon'=>notificationIcon($n['type']),'time'=>formatNotificationTime($n['created_at']),'created_at_utc'=>notificationUtcIso($n['created_at']),'read'=>(int)$n['is_read']];}
    echo json_encode(['success'=>true,'items'=>$out,'unread'=>getUnreadNotificationsCount($_SESSION['user_id'])],JSON_UNESCAPED_UNICODE);exit;
} elseif ($uri === 'auth/google') {
    $url = googleLoginUrl();
    if (!$url) { $_SESSION['auth_error'] = 'Вход через Google временно недоступен'; redirect('/login'); }
    redirect($url);
} elseif ($uri === 'auth/google/callback') {
    $result = handleGoogleCallback();
    if (!empty($result['error'])) { $_SESSION['auth_error'] = $result['error']; redirect('/login'); }
    $ou=getDB()->prepare('SELECT * FROM users WHERE id=?');$ou->execute([$result['user_id']]);$ou=$ou->fetch();
    if($ou && strpos($ou['email'],'@google.placeholder')===false && empty($ou['email_verified_at'])){sendVerificationEmail($ou['id']);}
    loginSiteUser($result['user_id']);
    $_SESSION['flash']='Вход через Google выполнен. Если email ещё не подтверждён, мы отправили письмо с подтверждением.';
    redirect('/profile');
} elseif ($uri === 'auth/vk') {
    $url = vkLoginUrl();
    if (!$url) { $_SESSION['auth_error'] = 'Вход через VK временно недоступен'; redirect('/login'); }
    redirect($url);
} elseif ($uri === 'auth/vk/callback') {
    $result = handleVkCallback();
    if (!empty($result['error'])) { $_SESSION['auth_error'] = $result['error']; redirect('/login'); }
    loginSiteUser($result['user_id']);
    redirect('/profile');
} elseif ($uri === 'logout') {
    logoutSiteUser();
    redirect('/');
} elseif ($uri === 'profile') {
    requireLogin();
    require __DIR__ . '/pages/profile.php';
} elseif ($uri === 'profile/settings') {
    requireLogin();
    require __DIR__ . '/pages/settings.php';
} elseif ($uri === 'chats') {
    requireLogin();
    require __DIR__ . '/pages/chats.php';
} elseif ($uri === 'profile/bonuses') { requireLogin(); require __DIR__ . '/pages/bonuses.php';
} elseif ($uri === 'profile/favorites') {
    requireLogin();
    require __DIR__ . '/pages/favorites.php';
} elseif ($uri === 'profile/subscriptions') {
    requireLogin();
    require __DIR__ . '/pages/subscriptions.php';
} elseif (preg_match('/^trader\/(\d+)$/', $uri, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/pages/trader_profile.php';
} elseif (preg_match('/^mod\/(.+)$/', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/pages/material.php';
} elseif (preg_match('/^page\/(.+)$/', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/pages/page.php';
} elseif (in_array($uri, ['privacy-policy','personal-data-consent','terms'], true)) {
    $map=['privacy-policy'=>['Политика конфиденциальности','privacy-policy'],'personal-data-consent'=>['Согласие на обработку персональных данных','personal-data-consent'],'terms'=>['Пользовательское соглашение','terms']];
    [$pageTitle,$slug]=$map[$uri]; $st=getDB()->prepare('SELECT * FROM legal_documents WHERE slug=?'); $st->execute([$slug]); $doc=$st->fetch();
    if(!$doc){http_response_code(404);exit('Документ не найден');} include __DIR__.'/pages/legal.php';
} elseif (preg_match('#^orders/(\d+)/receipt$#', $uri, $m)) {
    requireLogin(); $order=getOrderById((int)$m[1]); if(!$order || (int)$order['buyer_id']!==(int)$_SESSION['user_id'] || !receiptStatusAllowed($order['status'])) redirect('/orders');
    $pageTitle='Квитанция об оплате'; include __DIR__.'/pages/receipt.php';
} elseif (preg_match('#^orders/(\d+)/receipt-pdf$#', $uri, $m)) {
    requireLogin(); $order=getOrderById((int)$m[1]); if(!$order || (int)$order['buyer_id']!==(int)$_SESSION['user_id'] || !receiptStatusAllowed($order['status'])) redirect('/orders');
    $tmpDir=sys_get_temp_dir(); $base='gal_receipt_'.bin2hex(random_bytes(8)); $htmlFile=$tmpDir.'/'.$base.'.html'; $pdfFile=$tmpDir.'/'.$base.'.pdf';
    $doc='<!doctype html><html lang="ru"><meta charset="utf-8"><title>Квитанция #'.(int)$order['id'].'</title><style>body{font-family:Arial,sans-serif;max-width:760px;margin:40px auto;color:#111}.box{border:1px solid #ddd;border-radius:14px;padding:24px}.row{display:flex;justify-content:space-between;gap:20px;padding:10px 0;border-top:1px solid #eee}.muted{color:#666;font-size:12px}</style><div class="box">'.receiptHtml($order).'<p class="muted">Это подтверждение/квитанция сайта, а не кассовый чек ККТ.</p></div></html>';
    file_put_contents($htmlFile,$doc); $chromium=trim((string)@shell_exec('command -v chromium 2>/dev/null || command -v chromium-browser 2>/dev/null || command -v google-chrome 2>/dev/null'));
    if($chromium){$cmd=escapeshellarg($chromium).' --headless --no-sandbox --disable-gpu --print-to-pdf='.escapeshellarg($pdfFile).' '.escapeshellarg('file://'.$htmlFile).' 2>&1'; @shell_exec($cmd);}
    if(is_file($pdfFile)&&filesize($pdfFile)>100){header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="receipt-'.$order['id'].'.pdf"');header('Content-Length: '.filesize($pdfFile));readfile($pdfFile);@unlink($htmlFile);@unlink($pdfFile);exit;}
    @unlink($htmlFile);@unlink($pdfFile);http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Генератор PDF не доступен на сервере. Откройте страницу квитанции и используйте «Печать → Сохранить как PDF».';exit;
} elseif (preg_match('/^payment-success\/(\d+)$/', $uri, $m)) {
    $_GET['id'] = $m[1];
    $premMat = getMaterialById((int)$m[1]);
    if ($premMat && !empty($premMat['publish_at']) && strtotime($premMat['publish_at']) > time()) {
        redirect('/mod/' . $premMat['slug']);
    }
    if (!empty($_SESSION['user_id'])) {
        try {
            getDB()->prepare('INSERT INTO admin_material_purchases (user_id, material_id, status) VALUES (?, ?, "claimed")')->execute([$_SESSION['user_id'], (int)$m[1]]);
        } catch (Throwable $e) { /* already claimed by this user, ignore */ }
        evaluateTraderTasks($_SESSION['user_id']);
    }
    require __DIR__ . '/pages/payment-success.php';
} elseif ($uri === 'admin/login') {
    require __DIR__ . '/admin/login.php';
} elseif ($uri === 'admin/logout') {
    unset($_SESSION['admin_id'], $_SESSION['admin_role']);
    session_regenerate_id(true);
    redirect('/admin/login');
} elseif ($uri === 'admin') {
    requireAdmin();
    require __DIR__ . '/admin/dashboard.php';
} elseif ($uri === 'admin/materials') {
    requireAdmin();
    require __DIR__ . '/admin/materials.php';
} elseif ($uri === 'admin/materials/create') {
    requireAdmin();
    require __DIR__ . '/admin/materials_create.php';
} elseif (preg_match('/^admin\/materials\/(\d+)\/edit$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/materials_edit.php';
} elseif (preg_match('/^admin\/materials\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    $db = getDB();
    $db->prepare('DELETE FROM materials WHERE id = ?')->execute([$m[1]]);
    redirect('/admin/materials');
} elseif ($uri === 'admin/categories') {
    requireAdmin();
    require __DIR__ . '/admin/categories.php';
} elseif (preg_match('/^admin\/categories\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    $db = getDB();
    $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$m[1]]);
    redirect('/admin/categories');
} elseif ($uri === 'admin/tasks') {
    requireAdmin();
    require __DIR__ . '/admin/tasks.php';
} elseif (preg_match('/^admin\/tasks\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    $db = getDB();
    $db->prepare('DELETE FROM tasks WHERE id = ?')->execute([$m[1]]);
    redirect('/admin/tasks');
} elseif ($uri === 'admin/pages') {
    requireAdmin();
    require __DIR__ . '/admin/pages.php';
} elseif ($uri === 'admin/pages/create') {
    requireAdmin();
    require __DIR__ . '/admin/pages_create.php';
} elseif (preg_match('/^admin\/pages\/(\d+)\/edit$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/pages_edit.php';
} elseif (preg_match('/^admin\/pages\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    $db = getDB();
    $db->prepare('DELETE FROM pages WHERE id = ?')->execute([$m[1]]);
    redirect('/admin/pages');
} elseif ($uri === 'admin/tickets') {
    requireAdmin();
    require __DIR__ . '/admin/tickets.php';
} elseif (preg_match('/^admin\/tickets\/(\d+)$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/tickets_show.php';
} elseif (preg_match('/^admin\/tickets\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    $db = getDB();
    $db->prepare('DELETE FROM tickets WHERE id = ?')->execute([$m[1]]);
    redirect('/admin/tickets');
} elseif ($uri === 'admin/news') {
    requireAdmin();
    require __DIR__ . '/admin/news.php';
} elseif ($uri === 'admin/news/create') {
    requireAdmin();
    require __DIR__ . '/admin/news_create.php';
} elseif (preg_match('/^admin\/news\/(\d+)\/edit$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/news_edit.php';
} elseif (preg_match('/^admin\/news\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    $db = getDB();
    $db->prepare('DELETE FROM news WHERE id = ?')->execute([$m[1]]);
    redirect('/admin/news');
} elseif ($uri === 'admin/comments') {
    requireAdmin();
    require __DIR__ . '/admin/comments.php';
} elseif (preg_match('/^admin\/comments\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    $db = getDB();
    $db->prepare('DELETE FROM comments WHERE id = ?')->execute([$m[1]]);
    redirect('/admin/comments');
} elseif ($uri === 'admin/totp') {
    requireAdmin();
    require __DIR__ . '/admin/totp.php';
} elseif ($uri === 'admin/password') {
    requireAdmin();
    require __DIR__ . '/admin/password.php';
} elseif ($uri === 'admin/vk-broadcast') {
    requireAdmin();
    require __DIR__ . '/admin/vk_broadcast.php';
} elseif ($uri === 'admin/settings') {
    requireAdmin();
    require __DIR__ . '/admin/settings.php';

// ===== v2.0: Marketplace (public) =====
} elseif ($uri === 'marketplace') {
    require __DIR__ . '/pages/marketplace.php';
} elseif (preg_match('/^product\/(.+)$/', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/pages/product.php';

// ===== v2.0: Orders (buyer + seller shared view) =====
} elseif ($uri === 'orders') {
    requireLogin();
    require __DIR__ . '/pages/orders.php';
} elseif (preg_match('/^orders\/(\d+)$/', $uri, $m)) {
    requireLogin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/pages/order.php';

// ===== v2.0: Seller panel =====
} elseif ($uri === 'seller') {
    requireRole('trader');
    require __DIR__ . '/seller/dashboard.php';
} elseif ($uri === 'seller/products') {
    requireRole('trader');
    require __DIR__ . '/seller/products.php';
} elseif ($uri === 'seller/products/create') {
    requireRole('trader');
    handleTraderProductForm(null);
} elseif (preg_match('/^seller\/products\/(\d+)\/edit$/', $uri, $m)) {
    requireRole('trader');
    handleTraderProductForm((int)$m[1]);
} elseif (preg_match('/^seller\/products\/(\d+)\/toggle$/', $uri, $m) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('trader');
    getDB()->prepare("UPDATE trader_products SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ? AND user_id = ? AND status = 'approved'")
        ->execute([(int)$m[1], $_SESSION['user_id']]);
    redirect('/seller/products');
} elseif (preg_match('/^seller\/products\/(\d+)\/delete$/', $uri, $m)) {
    requireRole('trader');
    getDB()->prepare('DELETE FROM trader_products WHERE id = ? AND user_id = ?')->execute([(int)$m[1], $_SESSION['user_id']]);
    redirect('/seller/products');
} elseif (preg_match('/^seller\/products\/(\d+)\/submit$/', $uri, $m)) {
    requireRole('trader');
    if (isBanned()) {
        $_SESSION['flash'] = 'Ваш аккаунт заблокирован, публикация товаров недоступна';
    } elseif (submitProductForModeration((int)$m[1], $_SESSION['user_id'])) {
        $_SESSION['flash'] = 'Товар отправлен на модерацию. Ожидайте решения администратора.';
    } else {
        $_SESSION['flash'] = 'Не удалось отправить товар на модерацию';
    }
    redirect('/seller/products');
} elseif ($uri === 'seller/orders') {
    requireRole('trader');
    require __DIR__ . '/seller/orders.php';
} elseif ($uri === 'seller/finances') {
    requireRole('trader');
    require __DIR__ . '/seller/finances.php';
} elseif ($uri === 'seller/withdraw' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('trader');
    $result = requestWithdrawal($_SESSION['user_id'], $_POST['amount'] ?? 0);
    $_SESSION['flash'] = $result['error'] ?? 'Заявка на вывод создана';
    redirect('/seller/finances');
} elseif ($uri === 'seller/settings') {
    requireRole('trader');
    require __DIR__ . '/seller/settings.php';
} elseif (preg_match('/^seller\/products\/(\d+)\/stock$/', $uri, $m)) {
    requireRole(['trader', 'admin']);
    $_GET['id'] = $m[1];
    require __DIR__ . '/seller/product_stock.php';
} elseif ($uri === 'seller/reviews') {
    requireRole('trader');
    require __DIR__ . '/seller/reviews.php';
} elseif (preg_match('/^seller\/reviews\/(\d+)\/reply$/', $uri, $m)) {
    requireRole('trader');
    $response = trim($_POST['response'] ?? '');
    if ($response !== '') {
        getDB()->prepare('UPDATE reviews SET response = ? WHERE id = ? AND seller_id = ? AND response IS NULL')
            ->execute([$response, (int)$m[1], $_SESSION['user_id']]);
    }
    redirect('/seller/reviews');
} elseif ($uri === 'seller/messages') {
    requireRole('trader');
    require __DIR__ . '/seller/messages.php';
} elseif ($uri === 'seller/templates') {
    requireRole('trader');
    require __DIR__ . '/seller/templates.php';
} elseif ($uri === 'seller/templates/create') {
    requireRole('trader');
    handleTraderProductTemplateForm(null);
} elseif (preg_match('/^seller\/templates\/(\d+)\/edit$/', $uri, $m)) {
    requireRole('trader');
    handleTraderProductTemplateForm((int)$m[1]);
} elseif (preg_match('/^seller\/templates\/(\d+)\/delete$/', $uri, $m)) {
    requireRole('trader');
    deleteTraderProductTemplate((int)$m[1], $_SESSION['user_id']);
    $_SESSION['flash'] = 'Шаблон удалён';
    redirect('/seller/templates');

// ===== v2.0: Moderator panel =====
} elseif ($uri === 'moderator') {
    requireModerator();
    require __DIR__ . '/moderator/dashboard.php';
} elseif ($uri === 'moderator/tickets') {
    requireModerator();
    require __DIR__ . '/moderator/tickets.php';
} elseif (preg_match('/^moderator\/tickets\/(\d+)$/', $uri, $m)) {
    requireModerator();
    $_GET['id'] = $m[1];
    require __DIR__ . '/moderator/tickets_show.php';
} elseif ($uri === 'moderator/comments') {
    requireModerator();
    require __DIR__ . '/moderator/comments.php';
} elseif (preg_match('/^moderator\/comments\/(\d+)\/delete$/', $uri, $m)) {
    requireModerator();
    getDB()->prepare('DELETE FROM comments WHERE id = ?')->execute([(int)$m[1]]);
    logAdminAction('delete_comment', 'ID ' . $m[1]);
    redirect('/moderator/comments');
} elseif ($uri === 'moderator/banlist') {
    requireModerator();
    require __DIR__ . '/moderator/banlist.php';
} elseif (preg_match('/^moderator\/banlist\/(\d+)\/ban$/', $uri, $m)) {
    requireModerator();
    banUser((int)$m[1], (int)($_POST['days'] ?? 0), $_POST['reason'] ?? '');
    logAdminAction('ban_user', 'ID ' . $m[1]);
    redirect('/moderator/banlist');
} elseif (preg_match('/^moderator\/banlist\/(\d+)\/unban$/', $uri, $m)) {
    requireModerator();
    unbanUser((int)$m[1]);
    logAdminAction('unban_user', 'ID ' . $m[1]);
    redirect('/moderator/banlist');
} elseif ($uri === 'moderator/disputes') {
    requireModerator();
    require __DIR__ . '/moderator/disputes.php';
} elseif (preg_match('/^moderator\/disputes\/(\d+)\/resolve$/', $uri, $m)) {
    requireModerator();
    resolveDispute((int)$m[1], $_POST['decision'] ?? '');
    logAdminAction('resolve_dispute', 'Order ' . $m[1] . ': ' . ($_POST['decision'] ?? ''));
    redirect('/moderator/disputes');
} elseif ($uri === 'moderator/chats') {
    requireModerator();
    require __DIR__ . '/moderator/chats.php';
} elseif (preg_match('/^moderator\/chats\/(\d+)$/', $uri, $m)) {
    requireModerator();
    $_GET['id'] = $m[1];
    require __DIR__ . '/moderator/chat_view.php';

// ===== v2.0: Admin extensions =====
} elseif ($uri === 'admin/promos/create') {
    requireAdmin();
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $discountType = ($_POST['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    $discountValue = max(0, (float)($_POST['discount_value'] ?? 0));
    $maxUses = max(0, (int)($_POST['max_uses'] ?? 0));
    $minOrder = max(0, (float)($_POST['min_order_amount'] ?? 0));
    $expiresAt = trim((string)($_POST['expires_at'] ?? ''));
    $sellerId = (int)($_POST['seller_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($code === '' || $discountValue <= 0) {
        $_SESSION['flash'] = 'Укажите код и размер скидки';
    } elseif ($discountType === 'percent' && $discountValue > 100) {
        $_SESSION['flash'] = 'Процентная скидка не может быть больше 100%';
    } else {
        try {
            $db = getDB();
            $check = $db->prepare('SELECT id FROM promo_codes WHERE UPPER(code) = ? LIMIT 1');
            $check->execute([$code]);
            if ($check->fetch()) {
                $_SESSION['flash'] = 'Такой промокод уже существует';
            } else {
                $sellerId = $sellerId > 0 ? $sellerId : null;
                $productId = $productId > 0 ? $productId : null;
                $expiresAt = $expiresAt !== '' ? date('Y-m-d H:i:s', strtotime($expiresAt)) : null;
                $db->prepare('INSERT INTO promo_codes (code, discount_type, discount_value, max_uses, min_order_amount, expires_at, seller_id, product_id, is_active, used_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0)')
                    ->execute([$code, $discountType, $discountValue, $maxUses, $minOrder, $expiresAt, $sellerId, $productId]);
                logAdminAction('create_promo', $code);
                $_SESSION['flash'] = 'Промокод создан';
            }
        } catch (Throwable $e) {
            error_log('Promo create failed: ' . $e->getMessage());
            $_SESSION['flash'] = 'Не удалось создать промокод';
        }
    }
    redirect('/admin/promos');
} elseif (preg_match('/^admin\/promos\/(\d+)\/toggle$/', $uri, $m)) {
    requireAdmin();
    getDB()->prepare('UPDATE promo_codes SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([(int)$m[1]]);
    logAdminAction('toggle_promo', 'ID ' . $m[1]);
    redirect('/admin/promos');
} elseif (preg_match('/^admin\/promos\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    getDB()->prepare('DELETE FROM promo_codes WHERE id = ?')->execute([(int)$m[1]]);
    logAdminAction('delete_promo', 'ID ' . $m[1]);
    redirect('/admin/promos');
} elseif ($uri === 'admin/promos') { requireAdmin(); require __DIR__ . '/admin/promos.php';
} elseif (preg_match('/^admin\/promos\/(\d+)\/usages$/', $uri, $m)) { requireAdmin(); $_GET['id'] = (int)$m[1]; require __DIR__ . '/admin/promo_usages.php';
} elseif ($uri === 'admin/users') {
    requireAdmin();
    require __DIR__ . '/admin/users.php';
} elseif (preg_match('/^admin\/users\/(\d+)$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/users_show.php';
} elseif ($uri === 'admin/orders') {
    requireAdmin();
    require __DIR__ . '/admin/orders.php';
} elseif ($uri === 'admin/seller-chats') {
    requireAdmin();
    require __DIR__ . '/admin/seller_chats.php';
} elseif ($uri === 'admin/auto-delivery') {
    requireAdmin();
    require __DIR__ . '/admin/auto_delivery_overview.php';
} elseif ($uri === 'admin/seller-chats/view') {
    requireAdmin();
    require __DIR__ . '/admin/seller_chat_view.php';
} elseif (preg_match('/^admin\/orders\/(\d+)$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/orders_show.php';
} elseif (preg_match('/^admin\/orders\/(\d+)\/status$/', $uri, $m)) {
    requireAdmin();
    $action = $_POST['status_action'] ?? '';
    if ($action === 'confirm_payment') { confirmOrderPayment((int)$m[1]); logAdminAction('order_confirm_payment', 'Order ' . $m[1]); }
    elseif ($action === 'cancel') { cancelOrder((int)$m[1]); logAdminAction('order_cancel', 'Order ' . $m[1]); }
    elseif ($action === 'in_progress') { markOrderInProgress((int)$m[1]); logAdminAction('order_in_progress', 'Order ' . $m[1]); }
    elseif ($action === 'complete') { completeOrder((int)$m[1], 'admin'); logAdminAction('order_complete', 'Order ' . $m[1]); }
    elseif ($action === 'refund') {
        getDB()->prepare('UPDATE orders SET status = "refunded", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$m[1]]);
        addOrderMessage((int)$m[1], null, 'Администратор оформил возврат средств покупателю.', true);
        logAdminAction('order_refund', 'Order ' . $m[1]);
    }
    redirect('/admin/orders/' . $m[1]);
} elseif (preg_match('/^admin\/orders\/(\d+)\/message$/', $uri, $m)) {
    requireAdmin();
    if (trim($_POST['message'] ?? '')) {
        addOrderMessage((int)$m[1], $_SESSION['admin_id'], trim($_POST['message']), false, null, true);
    }
    redirect('/admin/orders/' . $m[1]);
} elseif ($uri === 'admin/withdrawals') {
    requireAdmin();
    require __DIR__ . '/admin/withdrawals.php';
} elseif (preg_match('/^admin\/withdrawals\/(\d+)\/process$/', $uri, $m)) {
    requireAdmin();
    processWithdrawal((int)$m[1], ($_POST['decision'] ?? '') === 'approve', $_POST['comment'] ?? null);
    logAdminAction('process_withdrawal', 'ID ' . $m[1]);
    redirect('/admin/withdrawals');
} elseif ($uri === 'admin/reviews') {
    requireAdmin();
    require __DIR__ . '/admin/reviews.php';
} elseif (preg_match('/^admin\/reviews\/(\d+)\/toggle$/', $uri, $m)) {
    requireAdmin();
    getDB()->prepare('UPDATE reviews SET is_hidden = 1 - is_hidden WHERE id = ?')->execute([(int)$m[1]]);
    logAdminAction('toggle_review', 'ID ' . $m[1]);
    redirect('/admin/reviews');
} elseif (preg_match('/^admin\/reviews\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    getDB()->prepare('DELETE FROM reviews WHERE id = ?')->execute([(int)$m[1]]);
    logAdminAction('delete_review', 'ID ' . $m[1]);
    redirect('/admin/reviews');
} elseif ($uri === 'admin/bonuses') {
    requireAdmin();
    require __DIR__ . '/admin/bonuses.php';
} elseif (preg_match('/^admin\/bonuses\/(\d+)\/adjust$/', $uri, $m)) {
    requireAdmin();
    $amount = (int)($_POST['amount'] ?? 0);
    if ($amount !== 0) {
        addBonusTransaction((int)$m[1], $amount, 'manual', $_POST['reason'] ?: 'Начисление администратором');
        logAdminAction('adjust_bonus', 'User ' . $m[1] . ': ' . $amount);
    }
    redirect('/admin/bonuses');
} elseif ($uri === 'admin/logs') {
    requireAdmin();
    require __DIR__ . '/admin/logs.php';

// ===== v2.1: seller bonus system, tasks, referrals, discipline =====
} elseif ($uri === 'seller/bonuses') {
    requireRole('trader');
    checkMonthlyTraderBonuses($_SESSION['user_id']);
    evaluateTraderTasks($_SESSION['user_id']);
    require __DIR__ . '/seller/bonuses.php';
} elseif ($uri === 'seller/referrals') {
    requireRole('trader');
    require __DIR__ . '/seller/referrals.php';
} elseif ($uri === 'seller/referrals/update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('trader');
    $result = setReferralCode($_SESSION['user_id'], $_POST['referral_code'] ?? '');
    $_SESSION['flash'] = $result['error'] ?? 'Код обновлён';
    redirect('/seller/referrals');
} elseif ($uri === 'seller/discipline') {
    requireRole('trader');
    require __DIR__ . '/seller/discipline.php';

// ===== v2.1: admin — traders (finances/bonuses/discipline) =====
} elseif ($uri === 'admin/traders') {
    requireAdmin();
    require __DIR__ . '/admin/traders.php';
} elseif (preg_match('/^admin\/traders\/(\d+)$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/traders_show.php';

// ===== v2.1: admin — bonus tasks =====
} elseif ($uri === 'admin/bonus-tasks') {
    requireAdmin();
    require __DIR__ . '/admin/bonus_tasks.php';
} elseif ($uri === 'admin/bonus-tasks/create') {
    requireAdmin();
    $_GET['id'] = null;
    require __DIR__ . '/admin/bonus_task_form.php';
} elseif (preg_match('/^admin\/bonus-tasks\/(\d+)\/edit$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/bonus_task_form.php';
} elseif (preg_match('/^admin\/bonus-tasks\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    getDB()->prepare('UPDATE bonus_tasks SET is_deleted = 1 WHERE id = ?')->execute([(int)$m[1]]);
    logAdminAction('delete_bonus_task', 'ID ' . $m[1]);
    redirect('/admin/bonus-tasks');

// ===== v2.1: admin — referral program =====
} elseif ($uri === 'admin/referrals') {
    requireAdmin();
    require __DIR__ . '/admin/referrals.php';
} elseif (preg_match('/^admin\/referrals\/(\d+)\/delete$/', $uri, $m)) {
    requireAdmin();
    getDB()->prepare('DELETE FROM referrals WHERE id = ?')->execute([(int)$m[1]]);
    logAdminAction('delete_referral', 'ID ' . $m[1]);
    redirect('/admin/referrals');

// ===== v2.1: admin — marketplace ordering / pinning =====
} elseif ($uri === 'admin/marketplace') {
    requireAdmin();
    require __DIR__ . '/admin/marketplace_manage.php';

// ===== v3.0: admin — product moderation =====
} elseif ($uri === 'admin/moderation') {
    requireAdmin();
    require __DIR__ . '/admin/moderation.php';
} elseif (preg_match('/^admin\/moderation\/(\d+)$/', $uri, $m)) {
    requireAdmin();
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/moderation_show.php';
} elseif ($uri === 'admin/statistics') {
    requireAdmin();
    require __DIR__ . '/admin/statistics.php';
} else {
    http_response_code(404);
    $pageTitle = '404';
    include __DIR__ . '/templates/header.php';
    echo '<div style="text-align:center;padding:80px 16px;"><h1 style="font-size:48px;font-weight:700;margin-bottom:16px;">404</h1><p style="color:var(--g-text-secondary);">Страница не найдена</p><a href="/" class="btn btn-primary" style="margin-top:24px;">На главную</a></div>';
    include __DIR__ . '/templates/footer.php';
}
