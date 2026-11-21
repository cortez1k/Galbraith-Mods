<?php
/**
 * VK Bot core for Galbraith.
 * Webhook ingress is isolated in index.php; this file is used by the site
 * for outbound notifications, linking, admin checks and broadcasts.
 */
function vkBotToken() { return trim((string)setting('vk_bot_token', '')); }
function vkBotConfigured() { return vkBotToken() !== ''; }

function vkApiCall($method, $params = []) {
    global $__lastVkBotError;
    $__lastVkBotError = null;
    if (!vkBotConfigured()) { $__lastVkBotError = 'Ключ сообщества VK не задан'; return null; }
    $params['access_token'] = vkBotToken();
    $params['v'] = setting('vk_api_version', '5.199');
    $url = 'https://api.vk.com/method/' . rawurlencode($method);
    $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_USERAGENT => 'Galbraith VK Bot/2.0',
    ]);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) { $__lastVkBotError = 'Сетевая ошибка VK API: ' . ($curlError ?: 'не удалось подключиться') ; return null; }
    $data = json_decode($raw, true);
    if (!is_array($data)) { $__lastVkBotError = 'VK API вернул некорректный JSON (HTTP ' . $http . ')'; return null; }
    if (!empty($data['error'])) {
        $__lastVkBotError = (string)($data['error']['error_msg'] ?? 'Ошибка VK API') . ' (код ' . (int)($data['error']['error_code'] ?? 0) . ')';
        return null;
    }
    return $data['response'] ?? null;
}

function vkBotCheck() {
    global $__lastVkBotError;
    $__lastVkBotError = null;
    if (!vkBotConfigured()) { $__lastVkBotError = 'Ключ сообщества не задан'; return null; }
    $response = vkApiCall('groups.getById', ['fields' => 'name']);
    if (is_array($response) && isset($response['groups'])) $groups = $response['groups']; else $groups = $response;
    if (!is_array($groups) || empty($groups[0]) || !is_array($groups[0])) {
        if (!$__lastVkBotError) $__lastVkBotError = 'VK не вернул данные сообщества';
        return null;
    }
    $group = $groups[0];
    $groupId = (string)($group['id'] ?? '');
    setSetting('vk_group_id', $groupId);
    setSetting('vk_group_name', (string)($group['name'] ?? ''));

    // Проверяем именно доступ к сообщениям, а не только валидность токена.
    $conv = vkApiCall('messages.getConversations', ['count' => 1, 'extended' => 0]);
    if ($conv === null) {
        setSetting('vk_bot_enabled', '0');
        return null;
    }
    // vk_bot_enabled означает: токен рабочий + messages API доступен.
    setSetting('vk_bot_enabled', '1');
    return $group;
}

function lastVkBotError() { return $GLOBALS['__lastVkBotError'] ?? ''; }

function vkBotSendMessage($peerId, $message) {
    global $__lastVkBotError;
    $__lastVkBotError = null;
    $peerId = (int)$peerId;
    $message = trim((string)$message);
    if ($peerId <= 0) { $__lastVkBotError = 'Некорректный peer_id'; return false; }
    if ($message === '') { $__lastVkBotError = 'Пустое сообщение'; return false; }
    $params = [
        'peer_id' => $peerId,
        'random_id' => random_int(-2147483648, 2147483647),
        'message' => $message,
    ];
    $groupId = (int)setting('vk_group_id', '0');
    if ($groupId > 0) $params['group_id'] = $groupId;
    $result = vkApiCall('messages.send', $params);
    if ($result === null) {
        error_log('VK messages.send failed: ' . lastVkBotError());
        return false;
    }
    return true;
}

function generateVkLinkCode($userId) {
    $code = 'VK-' . strtoupper(bin2hex(random_bytes(4)));
    getDB()->prepare('UPDATE users SET vk_bot_link_code = ? WHERE id = ?')->execute([$code, $userId]);
    return $code;
}
function vkBotDisconnect($userId) { getDB()->prepare('UPDATE users SET vk_bot_peer_id = NULL, vk_bot_link_code = NULL WHERE id = ?')->execute([$userId]); }

function vkRememberContact($peerId, $userId = null) {
    $peerId = (int)$peerId; if ($peerId <= 0) return;
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM vk_contacts WHERE peer_id = ? LIMIT 1');
        $stmt->execute([(string)$peerId]);
        if ($stmt->fetch()) {
            $db->prepare('UPDATE vk_contacts SET last_seen_at=CURRENT_TIMESTAMP, user_id=COALESCE(?,user_id) WHERE peer_id=?')->execute([$userId ? (int)$userId : null, (string)$peerId]);
        } else {
            $db->prepare('INSERT INTO vk_contacts(peer_id,user_id) VALUES(?,?)')->execute([(string)$peerId, $userId ? (int)$userId : null]);
        }
    } catch (Throwable $e) { error_log('VK contact save failed: ' . $e->getMessage()); }
}

function vkSyncConversationContacts($maxPages = 20) {
    $added=0; $seen=0; $offset=0; $maxPages=max(1,min(50,(int)$maxPages));
    for($page=0;$page<$maxPages;$page++){
        $response=vkApiCall('messages.getConversations',['offset'=>$offset,'count'=>200,'extended'=>0]);
        if(!is_array($response)||empty($response['items'])) break;
        foreach($response['items'] as $item){
            if((string)($item['conversation']['peer']['type']??'user')!=='user') continue;
            $peerId=(int)($item['conversation']['peer']['id']??0); if($peerId<=0) continue; $seen++;
            $db=getDB(); $q=$db->prepare('SELECT id FROM vk_contacts WHERE peer_id=? LIMIT 1'); $q->execute([(string)$peerId]);
            if(!$q->fetch()){$db->prepare('INSERT INTO vk_contacts(peer_id,user_id) VALUES(?,NULL)')->execute([(string)$peerId]);$added++;}
            else{$db->prepare('UPDATE vk_contacts SET last_seen_at=CURRENT_TIMESTAMP WHERE peer_id=?')->execute([(string)$peerId]);}
        }
        $offset+=count($response['items']); $count=(int)($response['count']??0); if($offset >= $count || count($response['items'])<200) break;
    }
    return ['ok'=>true,'seen'=>$seen,'added'=>$added,'error'=>lastVkBotError()];
}

function handleVkBotUpdate($update) {
    // Compatibility entry point. VK Callback API message_new uses
    // object.message.{peer_id,text}; keep support for the older flat layout too.
    if (!is_array($update) || ($update['type'] ?? '') !== 'message_new') return;
    $object = is_array($update['object'] ?? null) ? $update['object'] : [];
    $message = is_array($object['message'] ?? null) ? $object['message'] : $object;
    $peerId=(int)($message['peer_id']??$message['from_id']??0); $text=trim((string)($message['text']??''));
    if(!$peerId || $text==='') return;
    vkRememberContact($peerId);
    $normalized=strtoupper(preg_replace('/\s+/', '', $text));
    $stmt=getDB()->prepare("SELECT id FROM users WHERE UPPER(REPLACE(REPLACE(vk_bot_link_code,' ',''), CHAR(9), ''))=? LIMIT 1"); $stmt->execute([$normalized]);
    $user=$stmt->fetch();
    if($user){
        getDB()->prepare('UPDATE users SET vk_bot_peer_id=?, vk_bot_link_code=NULL WHERE id=?')->execute([(string)$peerId,(int)$user['id']]);
        vkRememberContact($peerId,(int)$user['id']);
        vkBotSendMessage($peerId,'Готово! Уведомления Galbraith теперь будут приходить вам сюда.');
    } else {
        vkBotSendMessage($peerId,'Чтобы подключить уведомления, откройте профиль на сайте Galbraith → «VK-уведомления» → «Подключить». Затем отправьте боту полученный код.');
    }
}

function vkNotificationMessage($type, $data = []) {
    $data = is_array($data) ? $data : [];
    $title = notificationSubject($type);
    $content = trim(preg_replace('/\\s+/', ' ', strip_tags(notificationContent($type, $data))));
    $orderId = (int)($data['order_id'] ?? 0);

    // VK не рендерит HTML из notificationContent(), поэтому для ЛС
    // собираем отдельный текстовый формат: заголовок + блоки + ссылка.
    switch ($type) {
        case 'login_code':
            $code = trim((string)($data['code'] ?? ''));
            return "🔐  {$title}\n\n"
                . "Ваш код: {$code}\n\n"
                . "⏱ Код действует 10 минут.\n"
                . "Если это были не вы — просто проигнорируйте сообщение.";
        case 'order_created':
            return "🧾  Новый заказ\n\n"
                . ($data['product'] ? "📦 Товар: " . trim((string)$data['product']) . "\n" : '')
                . ($orderId ? "🔢 Заказ: #{$orderId}\n" : '')
                . "Статус заказа можно посмотреть на сайте.";
        case 'order_status':
            return "📦  Статус заказа изменён\n\n" . $content . ($orderId ? "\n\n🔗 Заказ: " . rtrim(SITE_URL,'/') . "/orders/" . $orderId : '');
        case 'new_message':
            return "💬  Новое сообщение\n\n" . $content . ($orderId ? "\n\n🔗 Открыть чат: " . rtrim(SITE_URL,'/') . "/orders/" . $orderId : '');
        case 'seller_question':
            return "💬  Новое сообщение в чате\n\n" . $content . "\n\n🔗 " . rtrim(SITE_URL,'/') . "/seller/messages";
        case 'bonus_change':
        case 'trader_bonus_change':
            return "🎁  Изменение бонусов\n\n" . $content;
        case 'task_completed':
            return "🏆  Задание выполнено\n\n" . $content;
        case 'new_review':
            return "⭐  Новый отзыв\n\n" . $content;
        case 'withdrawal':
            return "💰  Заявка на вывод\n\n" . $content;
        case 'support_message':
        case 'support_reply':
            return "🆘  " . $title . "\n\n" . $content;
        case 'auto_delivery':
            return "⚡  Автовыдача\n\n" . $content . ($orderId ? "\n\n🔗 " . rtrim(SITE_URL,'/') . "/orders/" . $orderId : '');
        default:
            return "🔔  {$title}\n\n{$content}";
    }
}

function sendVkNotification($userId, $type, $data) {
    if (!vkBotConfigured() || setting('vk_bot_enabled', '0') !== '1') return;
    try {
        $stmt = getDB()->prepare('SELECT vk_bot_peer_id, login_code_via_vk FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['vk_bot_peer_id'])) return;

        if ($type === 'login_code' && (int)($row['login_code_via_vk'] ?? 1) === 0) return;

        vkBotSendMessage($row['vk_bot_peer_id'], vkNotificationMessage($type, $data));
    } catch (Throwable $e) {
        error_log('VK bot notify failed: ' . $e->getMessage());
    }
}

function vkBroadcastFormat($title, $message, $url = '') {
    $title = trim($title);
    $message = trim($message);
    $url = trim($url);
    $parts = [];
    if ($title !== '') $parts[] = "📢  " . $title;
    if ($message !== '') $parts[] = $message;
    if ($url !== '') $parts[] = "🔗 " . $url;
    $parts[] = "──────────────";
    $parts[] = "Galbraith • Сборки и моды";
    return implode("\n\n", $parts);
}

function vkBroadcastCreate($title, $message, $url = '', $createdBy = null) {
    if (!vkBotConfigured() || setting('vk_bot_enabled', '0') !== '1') {
        return ['ok'=>false, 'error'=>'VK-бот не подключён'];
    }
    $title = trim((string)$title);
    $message = trim((string)$message);
    $url = trim((string)$url);
    if ($title === '' || $message === '') return ['ok'=>false, 'error'=>'Заполните заголовок и текст рассылки'];
    if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) return ['ok'=>false, 'error'=>'Ссылка должна быть корректным URL'];

    // Один раз подтягиваем из VK доступные диалоги, чтобы в рассылку попали
    // и люди, которые писали сообществу до установки текущей версии сайта.
    vkSyncConversationContacts(50);
    $db = getDB();
    $contacts = $db->query("SELECT peer_id FROM vk_contacts WHERE CAST(peer_id AS INTEGER) > 0 ORDER BY id ASC")->fetchAll();
    $stmt = $db->prepare('INSERT INTO vk_broadcasts (title, message, url, status, total, created_by) VALUES (?, ?, ?, "queued", ?, ?)');
    $stmt->execute([$title, $message, $url, count($contacts), $createdBy ? (int)$createdBy : null]);
    $broadcastId = (int)$db->lastInsertId();
    $position = 0;
    $ins = $db->prepare('INSERT INTO vk_broadcast_recipients (broadcast_id, peer_id, position) VALUES (?, ?, ?)');
    foreach ($contacts as $contact) {
        $ins->execute([$broadcastId, (string)$contact['peer_id'], $position++]);
    }
    return ['ok'=>true, 'broadcast_id'=>$broadcastId, 'total'=>count($contacts)];
}

function vkBroadcastGet($id) {
    $stmt = getDB()->prepare('SELECT * FROM vk_broadcasts WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    return $stmt->fetch();
}

function vkBroadcastProcess($id, $batchSize = 15) {
    $db = getDB();
    $batchSize = max(1, min(50, (int)$batchSize));
    $broadcast = vkBroadcastGet($id);
    if (!$broadcast) return ['ok'=>false, 'error'=>'Рассылка не найдена'];
    if ($broadcast['status'] === 'paused') return ['ok'=>true, 'paused'=>true, 'broadcast'=>$broadcast];
    if ($broadcast['status'] === 'finished') return ['ok'=>true, 'finished'=>true, 'broadcast'=>$broadcast];

    if ($broadcast['status'] === 'queued') {
        $db->prepare('UPDATE vk_broadcasts SET status="running", started_at=COALESCE(started_at, CURRENT_TIMESTAMP), paused_at=NULL WHERE id=?')->execute([$id]);
    }

    $stmt = $db->prepare('SELECT * FROM vk_broadcast_recipients WHERE broadcast_id = ? AND status = "pending" ORDER BY position ASC LIMIT ' . $batchSize);
    $stmt->execute([(int)$id]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        $db->prepare('UPDATE vk_broadcasts SET status="finished", finished_at=CURRENT_TIMESTAMP, next_index=total WHERE id=?')->execute([$id]);
        return ['ok'=>true, 'finished'=>true, 'broadcast'=>vkBroadcastGet($id)];
    }

    $text = vkBroadcastFormat($broadcast['title'], $broadcast['message'], $broadcast['url']);
    foreach ($rows as $row) {
        // Проверяем статус перед каждой попыткой: админ может поставить паузу
        // между пакетами, не дожидаясь завершения всей рассылки.
        $fresh = vkBroadcastGet($id);
        if (!$fresh || $fresh['status'] === 'paused') break;

        $db->prepare('UPDATE vk_broadcast_recipients SET status="attempted", attempted_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$row['id']]);
        $db->prepare('UPDATE vk_broadcasts SET attempts=attempts+1 WHERE id=?')->execute([$id]);
        $ok = vkBotSendMessage((int)$row['peer_id'], $text);
        if ($ok) {
            $db->prepare('UPDATE vk_broadcast_recipients SET status="sent", sent_at=CURRENT_TIMESTAMP, error=NULL WHERE id=?')->execute([(int)$row['id']]);
            $db->prepare('UPDATE vk_broadcasts SET sent=sent+1 WHERE id=?')->execute([$id]);
        } else {
            $error = lastVkBotError() ?: 'VK API не принял сообщение';
            $db->prepare('UPDATE vk_broadcast_recipients SET status="failed", error=? WHERE id=?')->execute([$error, (int)$row['id']]);
            $db->prepare('UPDATE vk_broadcasts SET failed=failed+1 WHERE id=?')->execute([$id]);
        }
        usleep(70000);
    }

    $remainingStmt = $db->prepare('SELECT COUNT(*) FROM vk_broadcast_recipients WHERE broadcast_id=? AND status="pending"');
    $remainingStmt->execute([$id]);
    $remaining = (int)$remainingStmt->fetchColumn();
    if ($remaining === 0) {
        $db->prepare('UPDATE vk_broadcasts SET status="finished", finished_at=CURRENT_TIMESTAMP, next_index=total WHERE id=?')->execute([$id]);
    }
    return ['ok'=>true, 'finished'=>$remaining===0, 'paused'=>false, 'remaining'=>$remaining, 'broadcast'=>vkBroadcastGet($id)];
}

function vkBroadcastPause($id) {
    $db = getDB();
    $db->prepare('UPDATE vk_broadcasts SET status="paused", paused_at=CURRENT_TIMESTAMP WHERE id=? AND status IN ("queued","running")')->execute([(int)$id]);
    return vkBroadcastGet($id);
}

function vkBroadcastResume($id) {
    $db = getDB();
    $db->prepare('UPDATE vk_broadcasts SET status="running", paused_at=NULL, started_at=COALESCE(started_at,CURRENT_TIMESTAMP) WHERE id=? AND status="paused"')->execute([(int)$id]);
    return vkBroadcastGet($id);
}

function vkBroadcastList($limit = 20) {
    $stmt = getDB()->query('SELECT * FROM vk_broadcasts ORDER BY id DESC LIMIT ' . max(1, min(100, (int)$limit)));
    return $stmt->fetchAll();
}

