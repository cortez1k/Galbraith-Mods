<?php
/**
 * Система техподдержки: один постоянный чат на пользователя (не отдельные
 * обращения — проще и для пользователя, и для агента, вся история в одном
 * месте). Агенты забирают чат в работу, чтобы двое не отвечали одновременно.
 */

/** Возвращает существующий чат пользователя или создаёт новый. */
function getOrCreateSupportChat($userId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM support_chats WHERE user_id = ?');
    $stmt->execute([$userId]);
    $chat = $stmt->fetch();
    if ($chat) return $chat;
    $db->prepare('INSERT INTO support_chats (user_id) VALUES (?)')->execute([$userId]);
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getSupportChatById($chatId) {
    $stmt = getDB()->prepare('SELECT * FROM support_chats WHERE id = ?');
    $stmt->execute([$chatId]);
    return $stmt->fetch();
}

/** @param bool $includeInternal Заметки агентов видны только персоналу — на пользовательской странице всегда false. */
function getSupportChatMessages($chatId, $includeInternal = true) {
    $sql = 'SELECT sm.*, u.name as sender_name FROM support_messages sm LEFT JOIN users u ON sm.sender_id = u.id WHERE sm.chat_id = ?';
    if (!$includeInternal) $sql .= ' AND sm.is_internal_note = 0';
    $sql .= ' ORDER BY sm.created_at ASC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([$chatId]);
    return $stmt->fetchAll();
}

/**
 * Добавляет сообщение в чат поддержки. Обычное сообщение от пользователя
 * автоматически переоткрывает закрытый чат (не нужно создавать новый).
 */
function addSupportMessage($chatId, $senderId, $message, $attachmentPath = null, $isAgent = false, $isInternalNote = false) {
    $db = getDB();
    $db->prepare('INSERT INTO support_messages (chat_id, sender_id, message, attachment_path, is_agent, is_internal_note) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$chatId, $senderId, $message, $attachmentPath, $isAgent ? 1 : 0, $isInternalNote ? 1 : 0]);
    if (!$isInternalNote) {
        $newStatus = $isAgent ? 'answered' : 'open';
        $db->prepare('UPDATE support_chats SET last_message_at = CURRENT_TIMESTAMP, status = ? WHERE id = ?')->execute([$newStatus, $chatId]);
    }

    $chat = getSupportChatById($chatId);
    if (!$chat) return;
    if ($isAgent) {
        // Агент ответил — уведомляем пользователя.
        if (!$isInternalNote) notify($chat['user_id'], 'support_reply', ['chat_id' => $chatId]);
    } else {
        // Пользователь написал — уведомляем агентов поддержки (назначенного, либо всех, если чат ещё ничей).
        $agentIds = $chat['assigned_agent_id']
            ? [$chat['assigned_agent_id']]
            : array_column($db->query("SELECT id FROM users WHERE role = 'support' OR id IN (SELECT user_id FROM user_roles WHERE role = 'support')")->fetchAll(), 'id');
        foreach ($agentIds as $agentId) {
            notify($agentId, 'support_message', ['chat_id' => $chatId]);
        }
    }
}

function claimSupportChat($chatId, $agentId) {
    getDB()->prepare('UPDATE support_chats SET assigned_agent_id = ? WHERE id = ?')->execute([$agentId, $chatId]);
}

function closeSupportChat($chatId) {
    getDB()->prepare('UPDATE support_chats SET status = "closed" WHERE id = ?')->execute([$chatId]);
}

/** Список чатов для панели агента, с фильтром по вкладке. */
function getSupportChatsForAgent($agentId, $tab = 'new') {
    $db = getDB();
    $sql = 'SELECT sc.*, u.name as user_name, u.email as user_email,
            a.name as agent_name,
            (SELECT message FROM support_messages WHERE chat_id = sc.id AND is_internal_note = 0 ORDER BY id DESC LIMIT 1) as last_message
            FROM support_chats sc
            LEFT JOIN users u ON sc.user_id = u.id
            LEFT JOIN users a ON sc.assigned_agent_id = a.id
            WHERE ';
    if ($tab === 'mine') {
        $sql .= 'sc.assigned_agent_id = ? AND sc.status != "closed" ORDER BY sc.last_message_at DESC';
        $params = [$agentId];
    } elseif ($tab === 'closed') {
        $sql .= 'sc.status = "closed" ORDER BY sc.last_message_at DESC';
        $params = [];
    } else { // new — открытые и никем не взятые в работу
        $sql .= 'sc.status != "closed" AND sc.assigned_agent_id IS NULL ORDER BY sc.last_message_at DESC';
        $params = [];
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function countSupportChatsByTab($agentId) {
    $db = getDB();
    $mineStmt = $db->prepare('SELECT COUNT(*) FROM support_chats WHERE assigned_agent_id = ? AND status != "closed"');
    $mineStmt->execute([$agentId]);
    return [
        'new' => (int)$db->query('SELECT COUNT(*) FROM support_chats WHERE status != "closed" AND assigned_agent_id IS NULL')->fetchColumn(),
        'mine' => (int)$mineStmt->fetchColumn(),
    ];
}

/**
 * Возвращает список активных быстрых ответов, отсортированных по sort_order.
 * @return array
 */
function getQuickReplies() {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM support_quick_replies WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Безопасный рендерер сообщений поддержки (как от агента, так и от пользователя).
 * Экранирует весь текст, затем восстанавливает только разрешённую разметку:
 * **жирный**, *курсив*, `код`, переносы строк -> <br>.
 *
 * @param string $message
 * @return string HTML
 */
function renderSupportMessage($message) {
    if ($message === '') {
        return '';
    }
    $html = e($message);
    $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $html);
    $html = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $html);
    return nl2br($html);
}
