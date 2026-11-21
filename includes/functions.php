<?php
require_once __DIR__ . '/db.php';

function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCsrfToken($token) {
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function csrfField() { return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">'; }

function galbraithSellerName() { return 'GALBRAITH MODS'; }
function galbraithSellerContact() { return 'galbraith-sup@mail.ru'; }
function receiptStatusAllowed($status) { return in_array($status, ['paid','in_progress','completed'], true); }
function receiptData($order) {
    return [
        'seller' => galbraithSellerName(), 'owner' => 'Пришляк Илья',
        'contact' => galbraithSellerContact(), 'city' => 'Россия, г. Санкт-Петербург',
        'order_id' => (int)$order['id'], 'date' => date('d.m.Y H:i', strtotime($order['created_at'])),
        'product' => $order['product_title'] ?? 'Товар', 'quantity' => 1,
        'amount' => (float)$order['final_amount'], 'payment' => 'Оплата подтверждена системой сайта',
        'transaction' => !empty($order['payment_transaction_id']) ? $order['payment_transaction_id'] : null,
        'email' => $order['buyer_email'] ?? null,
    ];
}
function receiptHtml($order) {
    $r=receiptData($order); $title=receiptStatusAllowed($order['status'])?'Квитанция об оплате':'Подтверждение заказа';
    $rows=[['Продавец',$r['seller']],['Владелец',$r['owner']],['Контакт',$r['contact']],['Адрес',$r['city']],['Заказ','#'.$r['order_id']],['Дата',$r['date']],['Товар',$r['product']],['Количество',(string)$r['quantity']],['Стоимость',number_format($r['amount'],2,',',' ').' ₽'],['Способ оплаты',$r['payment']]];
    if($r['transaction']) $rows[]=['Идентификатор платежа',$r['transaction']]; if($r['email']) $rows[]=['Email покупателя',$r['email']];
    $html='<div style="background:var(--g-card);border:1px solid var(--g-border);border-radius:var(--g-radius);padding:20px;text-align:left"><div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:16px"><strong style="font-size:18px">'.$title.'</strong><span style="font-size:12px;color:var(--g-text-secondary)">Не является кассовым чеком</span></div>';
    foreach($rows as $row) $html.='<div style="display:flex;justify-content:space-between;gap:16px;padding:9px 0;border-top:1px solid var(--g-border);font-size:14px"><span style="color:var(--g-text-secondary)">'.e($row[0]).'</span><strong style="text-align:right">'.e($row[1]).'</strong></div>';
    return $html.'</div>';
}

/**
 * Единая точка рендера аватара. Раньше в разных местах сайта аватар собирался
 * по-разному: где-то как UPLOAD_URL.$avatar (ломало внешние OAuth-аватары
 * Google/VK, которые уже являются полным URL), где-то как голый $avatar без
 * префикса (ломало локально загруженные файлы). Теперь один источник правды:
 * - пусто → одна из 6 дефолтных аватарок, стабильно выбранная по ID пользователя;
 * - начинается с http(s):// → внешний URL (Google/VK), используем как есть;
 * - иначе → локальный файл, добавляем UPLOAD_URL.
 */
function avatarUrl($avatar, $userId = 0) {
    if (!empty($avatar)) {
        if (preg_match('#^https?://#i', $avatar)) return $avatar;
        return UPLOAD_URL . $avatar;
    }
    $n = ((int)$userId % 6) + 1;
    return '/assets/img/default-avatars/avatar-' . $n . '.svg';
}

/** Цвет бейджа роли — чтобы роли визуально отличались друг от друга, а не все выглядели одинаково. */
function roleColor($role) {
    $colors = [
        'admin' => '#FF375F',
        'moderator' => '#FF9F0A',
        'support' => '#2FE0B0',
        'trader' => '#5B8DEF',
        'premium' => '#FFD60A',
        'user' => '#8E8E93',
        'guest' => '#8E8E93',
    ];
    return $colors[$role] ?? '#8E8E93';
}

/** Число без лишних нулей после запятой: 15.0 -> "15", 15.5 -> "15.5". */
function formatPercent($value) {
    $value = (float)$value;
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

/** Достаёт ID видео из любой распространённой формы youtube-ссылки (watch?v=,
 *  youtu.be/, /shorts/, /embed/) и возвращает готовый embed-URL, либо null,
 *  если ссылка не похожа на YouTube. */
function youtubeEmbedUrl($url) {
    $url = trim((string)$url);
    if ($url === '') return null;
    if (preg_match('#(?:youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#i', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    return null;
}

function setting($key, $default = null) {
    $db = getDB();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function setSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([$key, $value]);
}

function slugify($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace([' ','_'], '-', $text);
    $text = preg_replace('/[^a-z0-9\-а-яё]/u', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function uploadFile($file, $subdir) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
    $dir = UPLOAD_DIR . $subdir;
    // move_uploaded_file() молча проваливается (возвращает false), если папки
    // назначения не существует — PHP её сам не создаёт. Ровно это было причиной
    // того, что изображения в чате не отправлялись: физической папки
    // assets/uploads/chat просто не было в поставке. Теперь создаём папку
    // назначения на лету при необходимости — это тот же класс бага, что мог бы
    // повториться с любой другой новой подпапкой в будущем.
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = uniqid() . '.' . $ext;
    $path = $dir . '/' . $name;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $subdir . '/' . $name;
    }
    return null;
}

function getCategories() {
    return getDB()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
}

function getCategoryBySlug($slug) {
    $stmt = getDB()->prepare('SELECT * FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function getMaterials($limit = null, $categorySlug = null, $search = null, $sort = 'new') {
    $db = getDB();
    $sql = "SELECT m.*, c.name as category_name, c.slug as category_slug,
                   (CASE WHEN m.publish_at IS NOT NULL AND m.publish_at > CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as is_premiere,
                   (CASE
                        WHEN m.publish_at IS NOT NULL AND m.publish_at <= CURRENT_TIMESTAMP AND datetime(m.publish_at, '+48 hours') >= CURRENT_TIMESTAMP THEN 1
                        WHEN m.publish_at IS NULL AND datetime(m.created_at, '+48 hours') >= CURRENT_TIMESTAMP THEN 1
                        ELSE 0
                   END) as is_new,
                   (SELECT AVG(rating) FROM material_ratings WHERE material_id = m.id) as rating_avg,
                   (SELECT COUNT(*) FROM material_ratings WHERE material_id = m.id) as rating_count
            FROM materials m LEFT JOIN categories c ON m.category_id = c.id WHERE 1=1";
    $params = [];
    if ($categorySlug) { $sql .= ' AND c.slug = ?'; $params[] = $categorySlug; }
    if ($search) { $sql .= ' AND m.title LIKE ?'; $params[] = '%' . $search . '%'; }
    // Материалы на премьере всегда закреплены наверху, независимо от сортировки.
    $sql .= $sort === 'popular' ? ' ORDER BY is_premiere DESC, m.downloads_count DESC' : ' ORDER BY is_premiere DESC, m.created_at DESC';
    if ($limit) $sql .= ' LIMIT ' . (int)$limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getMaterialBySlug($slug) {
    $stmt = getDB()->prepare('SELECT m.*, c.name as category_name FROM materials m LEFT JOIN categories c ON m.category_id = c.id WHERE m.slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

/** Похожие материалы — та же категория, кроме текущего. */
function getSimilarMaterials($materialId, $categoryId, $limit = 4) {
    if (!$categoryId) return [];
    $db = getDB();
    $stmt = $db->prepare("SELECT m.*, c.name as category_name,
            (CASE WHEN m.publish_at IS NOT NULL AND m.publish_at > CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as is_premiere
        FROM materials m LEFT JOIN categories c ON m.category_id = c.id
        WHERE m.category_id = ? AND m.id != ? AND (m.publish_at IS NULL OR m.publish_at <= CURRENT_TIMESTAMP)
        ORDER BY m.downloads_count DESC LIMIT ?");
    $stmt->execute([$categoryId, $materialId, $limit]);
    return $stmt->fetchAll();
}

/** Рейтинг материала: средняя оценка + количество. */
function getMaterialRating($materialId) {
    $stmt = getDB()->prepare('SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM material_ratings WHERE material_id = ?');
    $stmt->execute([$materialId]);
    $row = $stmt->fetch();
    return ['avg' => $row ? (float)$row['avg_rating'] : 0, 'cnt' => $row ? (int)$row['cnt'] : 0];
}

/** Оценка пользователя для материала (или null). */
function getUserMaterialRating($materialId, $userId) {
    if (!$userId) return null;
    $stmt = getDB()->prepare('SELECT rating FROM material_ratings WHERE material_id = ? AND user_id = ?');
    $stmt->execute([$materialId, $userId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['rating'] : null;
}

/** Поставить/обновить оценку 1..5. */
function rateMaterial($materialId, $userId, $rating) {
    $rating = max(1, min(5, (int)$rating));
    $stmt = getDB()->prepare('INSERT INTO material_ratings (material_id, user_id, rating) VALUES (?, ?, ?) ON CONFLICT(material_id, user_id) DO UPDATE SET rating = excluded.rating');
    $stmt->execute([$materialId, $userId, $rating]);
}

/** Подписка/отписка на напоминание о выходе премьеры. Возвращает true, если теперь подписан. */
function togglePremiereReminder($materialId, $userId) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM premiere_reminders WHERE material_id = ? AND user_id = ?');
    $stmt->execute([$materialId, $userId]);
    if ($stmt->fetch()) {
        $db->prepare('DELETE FROM premiere_reminders WHERE material_id = ? AND user_id = ?')->execute([$materialId, $userId]);
        return false;
    }
    $db->prepare('INSERT INTO premiere_reminders (material_id, user_id) VALUES (?, ?)')->execute([$materialId, $userId]);
    return true;
}

function hasPremiereReminder($materialId, $userId) {
    if (!$userId) return false;
    $stmt = getDB()->prepare('SELECT 1 FROM premiere_reminders WHERE material_id = ? AND user_id = ?');
    $stmt->execute([$materialId, $userId]);
    return (bool)$stmt->fetch();
}

/**
 * Разослать уведомления всем, кто подписался на напоминание, если материал
 * уже вышел. Вызывается на каждый запрос (как и остальные лёгкие миграции в
 * этом проекте) — отдельного cron на хостинге нет, а индексируемый запрос по
 * notified=0 дешёвый даже при заметной посещаемости.
 */
function processDuePremiereReminders() {
    $db = getDB();
    $rows = $db->query("SELECT r.id, r.material_id, r.user_id, m.title, m.slug
        FROM premiere_reminders r
        JOIN materials m ON m.id = r.material_id
        WHERE r.notified = 0 AND m.publish_at IS NOT NULL AND m.publish_at <= CURRENT_TIMESTAMP
        LIMIT 50")->fetchAll();
    foreach ($rows as $row) {
        notify($row['user_id'], 'premiere_release', ['title' => $row['title'], 'slug' => $row['slug']]);
        $db->prepare('UPDATE premiere_reminders SET notified = 1 WHERE id = ?')->execute([$row['id']]);
    }
}

/** «На сайте с 14.03.2025 (больше года)» — полная дата + человекочитаемая длительность. */
function formatMembershipDuration($createdAt) {
    if (!$createdAt) return '';
    $start = strtotime($createdAt);
    $months = max(0, (int)floor((time() - $start) / (86400 * 30.44)));
    if ($months < 1) {
        $human = 'меньше месяца';
    } elseif ($months < 12) {
        $human = $months . ' ' . pluralRu($months, 'месяц', 'месяца', 'месяцев');
    } else {
        $years = (int)floor($months / 12);
        $remMonths = $months % 12;
        $human = $years . ' ' . pluralRu($years, 'год', 'года', 'лет');
        if ($remMonths > 0) $human .= ' ' . $remMonths . ' ' . pluralRu($remMonths, 'месяц', 'месяца', 'месяцев');
    }
    return 'на сайте с ' . date('d.m.Y', $start) . ' (' . $human . ')';
}

function pluralRu($n, $one, $few, $many) {
    $n = abs((int)$n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $many;
    if ($n1 > 1 && $n1 < 5) return $few;
    if ($n1 == 1) return $one;
    return $many;
}

function getMaterialById($id) {
    $stmt = getDB()->prepare('SELECT * FROM materials WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getTasks() {
    return getDB()->query('SELECT * FROM tasks WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
}

/** Возвращает комментарии материала деревом: корневые (новые сверху) + массив 'replies' (старые сверху). */
function getComments($materialId) {
    $stmt = getDB()->prepare('SELECT * FROM comments WHERE material_id = ? ORDER BY created_at ASC, id ASC');
    $stmt->execute([$materialId]);
    $all = $stmt->fetchAll();
    $byParent = [];
    foreach ($all as $c) { $byParent[(int)($c['parent_id'] ?? 0)][] = $c; }
    $roots = array_reverse($byParent[0] ?? []);
    $attach = function ($c) use (&$attach, $byParent) {
        $c['replies'] = [];
        foreach ($byParent[(int)$c['id']] ?? [] as $r) $c['replies'][] = $attach($r);
        return $c;
    };
    return array_map($attach, $roots);
}

function countCommentsTotal($tree) {
    $n = 0;
    foreach ($tree as $c) $n += 1 + countCommentsTotal($c['replies'] ?? []);
    return $n;
}

function addComment($materialId, $name, $content, $userId = null, $parentId = null) {
    $stmt = getDB()->prepare('INSERT INTO comments (material_id, author_name, content, user_id, parent_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$materialId, $name, $content, $userId, $parentId]);
}

function getPagesMenu() {
    return getDB()->query('SELECT * FROM pages WHERE is_active = 1 AND show_in_menu = 1 ORDER BY menu_order')->fetchAll();
}

function getPageBySlug($slug) {
    $stmt = getDB()->prepare('SELECT * FROM pages WHERE slug = ? AND is_active = 1');
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function getTickets($status = null) {
    $db = getDB();
    $sql = 'SELECT * FROM tickets';
    if ($status) { $sql .= ' WHERE status = ?'; $stmt = $db->prepare($sql); $stmt->execute([$status]); }
    else { $stmt = $db->query($sql); }
    return $stmt->fetchAll();
}

function getTicketById($id) {
    $stmt = getDB()->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function isAdmin() {
    return !empty($_SESSION['admin_id']);
}

function requireAdmin() {
    if (!checkAdminSession()) {
        session_destroy();
        header('Location: /admin/login');
        exit;
    }
    // Раньше здесь проверялось только "не модератор ли это" — то есть ЛЮБАЯ
    // не-модераторская роль (в том числе новая 'support') проходила бы во
    // весь админ-раздел целиком. Теперь явно требуется role === 'admin'.
    if (staffRole() === 'moderator') {
        redirect('/moderator');
    }
    if (staffRole() === 'support') {
        redirect('/support/chats/new');
    }
    if (staffRole() !== 'admin') {
        http_response_code(403);
        die('Доступ запрещён.');
    }
}

// ===== v2.0: site-user session (separate from staff/admin session) =====

/**
 * Returns the logged-in site user (role: user/premium/trader), or the
 * logged-in staff account if they're browsing the public site too.
 * Staff accounts (admin/moderator) authenticate separately via /admin/login
 * and are tracked in $_SESSION['admin_id']; regular users authenticate via
 * OAuth (Google/VK) and are tracked in $_SESSION['user_id'].
 */
function currentUser() {
    static $user = false;
    if ($user !== false) return $user;
    $id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
    if (!$id) { $user = null; return null; }
    $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function isLoggedIn() {
    return currentUser() !== null;
}

function currentRole() {
    $u = currentUser();
    return $u ? $u['role'] : 'guest';
}

function roleLabel($role) {
    $labels = [
        'admin' => 'Администратор',
        'moderator' => 'Модератор',
        'support' => 'Агент поддержки',
        'trader' => 'Торговец',
        'premium' => 'Премиум',
        'user' => 'Пользователь',
        'guest' => 'Гость',
    ];
    return $labels[$role] ?? $role;
}

/** Роли пользователя сверх основной (users.role) — храним отдельно, чтобы не
 *  трогать места в коде, которые проверяют users.role напрямую в SQL. */
function extraRoles($userId) {
    if (!$userId) return [];
    $stmt = getDB()->prepare('SELECT role FROM user_roles WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'role');
}

function allRolesOf($user) {
    if (!$user) return ['guest'];
    return array_unique(array_merge([$user['role']], extraRoles($user['id'])));
}

function addExtraRole($userId, $role) {
    if (!in_array($role, ROLES, true)) return false;
    try {
        getDB()->prepare('INSERT OR IGNORE INTO user_roles (user_id, role) VALUES (?, ?)')->execute([$userId, $role]);
        return true;
    } catch (Throwable $e) { return false; }
}

function removeExtraRole($userId, $role) {
    getDB()->prepare('DELETE FROM user_roles WHERE user_id = ? AND role = ?')->execute([$userId, $role]);
}

function hasRole($roles) {
    $roles = (array)$roles;
    $user = currentUser();
    if (!$user) return in_array('guest', $roles, true);
    return (bool)array_intersect($roles, allRolesOf($user));
}

function isBanned($user = null) {
    $user = $user ?? currentUser();
    if (!$user || empty($user['banned_until'])) return false;
    return strtotime($user['banned_until']) > time();
}

/** Redirect to /login if not authenticated as a site user. */
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        redirect('/login');
    }
}

/** Redirect to / if the current user doesn't have one of the given roles. */
function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        http_response_code(403);
        die('Доступ запрещён: недостаточно прав.');
    }
}

/** Единая обёртка над setcookie(): на PHP 7.3+ использует нормальный
 *  array-синтаксис с samesite, на более старых версиях — позиционный
 *  синтаксис без samesite (SameSite там просто не выставляется, это не
 *  ломает функциональность, только чуть мягче политика cookie). Нужна
 *  отдельная обёртка, потому что старый трюк "путь + '; SameSite=Lax'"
 *  на PHP 8.1+ теперь падает с ValueError — там path валидируется строго. */
function setCookieCompat($name, $value, $expire, $httpOnly = true) {
    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $value, [
            'expires' => $expire,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);
    } else {
        setcookie($name, $value, $expire, '/', '', !empty($_SERVER['HTTPS']), $httpOnly);
    }
}

/**
 * Постоянный вход («запомнить браузер»): токен selector+validator в отдельной
 * долгоживущей куке, независимый от серверной сессии PHP. Обычная сессия на
 * многих хостингах истекает сама по себе через session.gc_maxlifetime (часто
 * 20-30 минут по умолчанию на shared-хостинге) или пропадает при простом
 * закрытии вкладки на телефоне — из-за этого людей выкидывало из аккаунта.
 * Этот токен переживает и то, и другое, пока не разлогинишься сам.
 */
function issueRememberToken($userId) {
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $hash = hash('sha256', $validator);
    $ttlDays = 45;
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * $ttlDays);
    getDB()->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $selector, $hash, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $expires]);
    setCookieCompat('galbraith_remember', $selector . ':' . $validator, time() + 60 * 60 * 24 * $ttlDays);
}

function clearRememberToken() {
    if (!empty($_COOKIE['galbraith_remember'])) {
        $parts = explode(':', $_COOKIE['galbraith_remember'], 2);
        if (!empty($parts[0])) {
            getDB()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$parts[0]]);
        }
    }
    setCookieCompat('galbraith_remember', '', time() - 3600);
}

/** Вызывается один раз в самом начале обработки запроса. Если обычной сессии
 *  нет, но есть валидная кука «запомнить браузер» — тихо логинит и выдаёт
 *  новый токен взамен использованного (ротация против повторного использования). */
function attemptRememberLogin() {
    if (!empty($_SESSION['user_id']) || empty($_COOKIE['galbraith_remember'])) return;
    $parts = explode(':', $_COOKIE['galbraith_remember'], 2);
    if (count($parts) !== 2) return;
    [$selector, $validator] = $parts;
    $stmt = getDB()->prepare('SELECT * FROM remember_tokens WHERE selector = ?');
    $stmt->execute([$selector]);
    $row = $stmt->fetch();
    if (!$row) return;
    getDB()->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$row['id']]);
    if (strtotime($row['expires_at']) < time()) return;
    if (!hash_equals($row['token_hash'], hash('sha256', $validator))) return;
    session_regenerate_id(true);
    $_SESSION['user_id'] = $row['user_id'];
    issueRememberToken($row['user_id']);
}

function loginSiteUser($userId) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    issueRememberToken($userId);
}

function logoutSiteUser() {
    unset($_SESSION['user_id']);
    clearRememberToken();
}

/**
 * Permanently disable an account and scrub its personal data on request of an admin.
 * The row itself (and its id) is kept rather than SQL-DELETEd, because orders,
 * transactions, reviews and trader products all reference users.id — a hard delete
 * would silently orphan that history throughout the admin panel, receipts and
 * financial reports. Instead we ban the account forever and anonymize the PII,
 * mirroring how deleteTrader() already deactivates a trader account safely.
 */
function deleteUserAccount($userId) {
    $db = getDB();
    $userId = (int)$userId;
    $anon = 'deleted_user_' . $userId;
    $db->prepare("UPDATE users SET
            name = ?, email = ?, login = ?, password = ?, full_name = NULL, contact_info = NULL,
            card_number = NULL, avatar = NULL, google_id = NULL, vk_id = NULL,
            telegram_chat_id = NULL, telegram_link_code = NULL, vk_bot_peer_id = NULL, vk_bot_link_code = NULL,
            totp_secret = NULL, twofa_enabled = 0,
            banned_until = '9999-12-31 23:59:59', ban_reason = 'Аккаунт удалён администратором'
        WHERE id = ?")
        ->execute(['Удалённый пользователь', $anon . '@deleted.local', $anon, bin2hex(random_bytes(32)), $userId]);
    $db->prepare('UPDATE trader_products SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
    $stmt = $db->prepare("SELECT id FROM orders WHERE (seller_id = ? OR buyer_id = ?) AND status IN ('awaiting_confirmation','paid','in_progress')");
    $stmt->execute([$userId, $userId]);
    foreach ($stmt->fetchAll() as $o) {
        $db->prepare('UPDATE orders SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$o['id']]);
        addOrderMessage($o['id'], null, 'Заказ отменён администратором: аккаунт удалён.', true);
    }
}

function saveUserProfile($userId, $name, $avatarFile = null) {
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 100) return ['error'=>'Никнейм должен содержать от 1 до 100 символов'];
    $db=getDB(); $avatar=null;
    if ($avatarFile && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $avatar=uploadFile($avatarFile,'avatars');
        if(!$avatar) return ['error'=>'Не удалось загрузить аватар'];
    }
    $sql='UPDATE users SET name=?'; $params=[$name];
    if($avatar){$sql.=', avatar=?';$params[]=$avatar;}
    $sql.=' WHERE id=?';$params[]=$userId;$db->prepare($sql)->execute($params); return ['ok'=>true];
}
/** Начинает подключение 2FA: генерирует секрет, но НЕ включает 2FA сразу —
 *  раньше 2FA становилась обязательной в тот же момент, когда показывался QR,
 *  ещё до того как пользователь успел его отсканировать и убедиться, что
 *  приложение генерирует верные коды. Из-за этого при неудачном сканировании
 *  можно было случайно заблокировать себе вход. Теперь 2FA включается только
 *  после подтверждения кодом из приложения — см. confirmUser2FA(). */
function startUser2FA($userId) {
    $secret = generateTOTPSecret();
    getDB()->prepare('UPDATE users SET totp_secret=?, twofa_enabled=0, totp_pending=1 WHERE id=?')->execute([$secret, $userId]);
    return $secret;
}

/** Проверяет код из приложения и, если верный, окончательно включает 2FA. */
function confirmUser2FA($userId, $code) {
    $stmt = getDB()->prepare('SELECT totp_secret FROM users WHERE id=?');
    $stmt->execute([$userId]);
    $secret = $stmt->fetchColumn();
    if (!$secret || !verifyTOTPCode($secret, $code)) return false;
    getDB()->prepare('UPDATE users SET twofa_enabled=1, totp_pending=0 WHERE id=?')->execute([$userId]);
    return true;
}

/** Отменяет незавершённое подключение 2FA (секрет сгенерирован, но код ещё не подтверждён). */
function cancelUser2FA($userId) {
    getDB()->prepare('UPDATE users SET totp_secret=NULL, twofa_enabled=0, totp_pending=0 WHERE id=?')->execute([$userId]);
}

function setUser2FA($userId,$enabled) {
    $db=getDB();
    if($enabled){$secret=generateTOTPSecret();$db->prepare('UPDATE users SET totp_secret=?, twofa_enabled=1, totp_pending=0 WHERE id=?')->execute([$secret,$userId]);return $secret;}
    $db->prepare('UPDATE users SET totp_secret=NULL, twofa_enabled=0, totp_pending=0 WHERE id=?')->execute([$userId]); return null;
}

// ===== v2.0: encryption for sensitive data (trader payout details) =====

function encryptSecret($plain) {
    if ($plain === null || $plain === '') return null;
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function decryptSecret($encoded) {
    if (!$encoded) return null;
    $raw = base64_decode($encoded);
    if ($raw === false || strlen($raw) < 17) return null;
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}

// ===== v4.0: notifications =====

function notify($userId, $type, $data = []) {
    $data = is_array($data) ? $data : [];
    getDB()->prepare('INSERT INTO notifications (user_id, type, data) VALUES (?, ?, ?)')
        ->execute([$userId, $type, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    try {
        $stmt = getDB()->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if ($u && !empty($u['email']) && function_exists('sendSystemEmail')) {
            sendSystemEmail($u['email'], notificationSubject($type), notificationBody($type, $data));
        }
    } catch (Throwable $e) {
        error_log('Notification email failed: ' . $e->getMessage());
    }
    if (function_exists('sendVkNotification')) {
        sendVkNotification($userId, $type, $data);
    }
}

function notificationSubject($type) {
    $subjects = [
        'order_created'=>'Новый заказ на Galbraith','order_status'=>'Изменение статуса заказа','new_message'=>'Новое сообщение в чате заказа',
        'dispute_opened'=>'Открыт спор по заказу','dispute_resolved'=>'Спор по заказу решён','bonus_change'=>'Изменение бонусного баланса',
        'seller_question'=>'Новое сообщение в чате с покупателем','trader_bonus_change'=>'Изменение бонусов торговца','task_completed'=>'Бонусное задание выполнено',
        'penalty_issued'=>'Вынесено дисциплинарное взыскание','trader_penalty_threshold'=>'Достигнут лимит взысканий','trader_removed'=>'Статус торговца отозван',
        'trader_restored'=>'Статус торговца восстановлен','new_review'=>'Новый отзыв','withdrawal'=>'Изменение заявки на вывод',
        'email_verify'=>'Подтвердите email — Galbraith','password_reset'=>'Восстановление пароля — Galbraith','product_moderation'=>'Изменение статуса товара',
        'new_product_subscription'=>'Новый товар у торговца, на которого вы подписаны','login_code'=>'Код подтверждения входа — Galbraith','auto_delivery'=>'⚡ Автовыдача товара','support_message'=>'Новое сообщение в поддержку','support_reply'=>'Ответ от поддержки','premiere_release'=>'🚀 Премьера вышла — можно скачивать',
    ];
    return $subjects[$type] ?? 'Уведомление Galbraith';
}

/**
 * Возвращает только "содержательную" часть уведомления (то самое предложение
 * про конкретное событие), БЕЗ обёртки письма. Раньше notificationBody() сразу
 * оборачивала текст в полный HTML-шаблон письма (шапка "GALBRAITH", тэглайн,
 * кнопка, футер, ссылка) — и именно эта обёрнутая версия использовалась и для
 * письма, и для короткого превью в колокольчике на сайте. В превью с неё просто
 * срезали теги и схлопывали пробелы, поэтому шапка письма, заголовок, текст,
 * подпись кнопки и футер склеивались в одну нечитаемую строку. Теперь контент
 * и обёртка письма — разные функции, превью использует только контент.
 */
function notificationContent($type, $data = []) {
    $data = is_array($data) ? $data : [];
    $product = trim((string)($data['product'] ?? $data['title'] ?? ''));
    $orderId = (int)($data['order_id'] ?? 0);
    $reason = trim((string)($data['reason'] ?? $data['description'] ?? ''));
    $amount = $data['amount'] ?? null;
    switch ($type) {
        case 'order_created': return $product ? 'Товар: <strong>'.e($product).'</strong><br>Номер заказа: <strong>#'.$orderId.'</strong>.' : 'Создан новый заказ <strong>#'.$orderId.'</strong>.';
        case 'order_status': return 'Заказ <strong>#'.$orderId.'</strong> получил новый статус: <strong>'.e(orderStatusLabel($data['status'] ?? '')).'</strong>.';
        case 'new_message': return 'В чате заказа <strong>#'.$orderId.'</strong> появилось новое сообщение.';
        case 'seller_question': return ($product ? 'Товар: <strong>'.e($product).'</strong><br>' : '').(!empty($data['reply']) ? 'Продавец ответил в чате.' : 'Покупатель отправил новое сообщение.');
        case 'dispute_opened': return 'По заказу <strong>#'.$orderId.'</strong> открыт спор. Администратор рассмотрит переписку и детали заказа.';
        case 'dispute_resolved': return 'Спор по заказу <strong>#'.$orderId.'</strong> рассмотрен администрацией. Решение: <strong>'.(($data['decision'] ?? '') === 'refund' ? 'возврат покупателю' : 'в пользу продавца').'</strong>.';
        case 'bonus_change': return 'Ваш баланс изменён на <strong>'.(($amount >= 0 ? '+' : '').$amount).' бонусов</strong>.'.($reason ? '<br><br>Причина: '.e($reason) : '');
        case 'trader_bonus_change': return 'Ваш баланс бонусов торговца изменён на <strong>'.(($amount >= 0 ? '+' : '').$amount).' бонусов</strong>.'.($reason ? '<br><br>Причина: '.e($reason) : '');
        case 'task_completed': return 'Задание '.(!empty($data['task']) ? '<strong>«'.e($data['task']).'»</strong> ' : '').'выполнено. Награда: <strong>'.e($data['bonus'] ?? 0).' бонусов</strong>.';
        case 'new_review': return 'Покупатель оставил новый отзыв по вашему заказу'.($orderId ? ' <strong>#'.$orderId.'</strong>' : '').'.';
        case 'withdrawal': return 'Статус заявки на вывод изменён: <strong>'.e(($data['status'] ?? '') === 'completed' ? 'выполнено' : 'отклонено').'</strong>.'.($amount !== null ? '<br>Сумма: <strong>'.e($amount).' ₽</strong>.' : '');
        case 'penalty_issued': return 'В отношении аккаунта вынесено взыскание.'.($reason ? '<br><br>Причина: '.e($reason) : '');
        case 'trader_removed': return 'Ваш статус торговца был отозван администрацией.';
        case 'trader_restored': return 'Ваш статус торговца восстановлен.';
        case 'product_moderation': return 'Товар '.($product ? '<strong>«'.e($product).'»</strong> ' : '').'получил статус: <strong>'.e($data['decision'] ?? 'изменён').'</strong>.'.(!empty($data['comment']) ? '<br><br>Причина: '.e($data['comment']) : '');
        case 'auto_delivery': return ($data['status']??'')==='delivered' ? 'Товар по заказу <strong>#'.$orderId.'</strong> выдан автоматически. Заказ завершён.' : 'Автовыдача по заказу <strong>#'.$orderId.'</strong> не сработала: закончился товар.';
        case 'login_code': return 'Ваш код подтверждения входа: <strong style="font-size:24px;letter-spacing:5px;">'.e($data['code'] ?? '').'</strong><br><br>Код действует 10 минут. Если это были не вы, просто проигнорируйте письмо.';
        case 'support_message': return 'Пользователь написал в чат поддержки. Загляните в панель, чтобы ответить.';
        case 'support_reply': return 'Поддержка ответила в вашем чате. Загляните на сайт, чтобы прочитать.';
        case 'premiere_release': return 'Материал <strong>«'.e($data['title'] ?? '').'»</strong>, на премьеру которого вы подписались, уже вышел — можно скачивать.';
        default: return 'В вашем аккаунте Galbraith произошло новое событие.';
    }
}

/** URL и подпись кнопки для письма — тоже отдельно от контента, чтобы не тащить их в превью. */
function notificationEmailAction($type, $data = []) {
    $orderId = (int)($data['order_id'] ?? 0);
    $map = [
        'order_created' => ['/orders/'.$orderId, 'Открыть заказ'], 'order_status' => ['/orders/'.$orderId, 'Открыть заказ'],
        'new_message' => ['/orders/'.$orderId, 'Открыть чат'], 'seller_question' => ['/seller/messages', 'Открыть чат'],
        'dispute_opened' => ['/orders/'.$orderId, 'Открыть заказ'], 'dispute_resolved' => ['/orders/'.$orderId, 'Открыть заказ'],
        'bonus_change' => ['/profile/bonuses', 'Открыть бонусы'], 'trader_bonus_change' => ['/seller/bonuses', 'Открыть бонусы'],
        'task_completed' => ['/seller/bonuses', 'Открыть бонусы'], 'new_review' => ['/seller/reviews', 'Посмотреть отзывы'],
        'withdrawal' => ['/seller/finances', 'Открыть финансы'], 'penalty_issued' => ['/seller/discipline', 'Открыть дисциплину'],
        'trader_removed' => ['/seller', 'Открыть кабинет'], 'trader_restored' => ['/seller', 'Открыть кабинет'],
        'product_moderation' => ['/seller/products', 'Открыть товары'], 'auto_delivery' => ['/orders/'.$orderId, 'Открыть заказ'],
        'login_code' => ['/login', 'Открыть Galbraith'],
        'support_message' => ['/support/chats', 'Открыть поддержку'],
        'support_reply' => ['/support', 'Открыть чат'],
        'premiere_release' => ['/mod/' . ($data['slug'] ?? ''), 'Смотреть материал'],
    ];
    return $map[$type] ?? ['/', 'Открыть Galbraith'];
}

function notificationBody($type, $data = []) {
    $data = is_array($data) ? $data : [];
    [$url, $button] = notificationEmailAction($type, $data);
    return emailTemplate(notificationSubject($type), notificationContent($type, $data), $url, $button);
}

function emailTemplate($title, $content, $url='/', $button='Открыть Galbraith') {
    $site = e(rtrim(SITE_URL, '/')); $safeUrl = e(rtrim(SITE_URL, '/') . '/' . ltrim($url, '/'));
    return '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title></head><body style="margin:0;background:#0d0d0f;color:#f5f5f7;font-family:Arial,sans-serif"><div style="max-width:600px;margin:auto;padding:28px 16px"><div style="background:#16161a;border:1px solid #29292f;border-radius:16px;overflow:hidden"><div style="padding:22px;border-bottom:1px solid #29292f"><b style="font-size:20px">GALBRAITH</b><div style="color:#888892;font-size:11px;margin-top:4px">Сборки и моды для AMAZING RP</div></div><div style="padding:28px 22px"><h1 style="font-size:21px;margin:0 0 16px">'.e($title).'</h1><div style="font-size:14px;line-height:1.65;color:#c7c7ce">'.$content.'</div><p style="margin-top:26px"><a href="'.$safeUrl.'" style="display:inline-block;padding:12px 18px;border-radius:9px;background:#E81C5A;color:#fff;text-decoration:none;font-weight:700">'.e($button).'</a></p></div><div style="padding:18px 22px;border-top:1px solid #29292f;color:#777780;font-size:11px">Это автоматическое письмо от Galbraith.<br><br><a href="'.$site.'" style="color:#FF5C8A">'.$site.'</a></div></div></div></body></html>';
}

function notificationIcon($type) {
    $map=['order_created'=>'🧾','order_status'=>'📦','new_message'=>'💬','seller_question'=>'💬','dispute_opened'=>'⚠️','dispute_resolved'=>'✅','bonus_change'=>'🎁','trader_bonus_change'=>'🎁','task_completed'=>'🏆','penalty_issued'=>'⚠️','trader_removed'=>'🚫','trader_restored'=>'✅','new_review'=>'⭐','withdrawal'=>'💰','login_code'=>'🔐','auto_delivery'=>'⚡','support_message'=>'🆘','support_reply'=>'🆘','premiere_release'=>'🚀'];
    return $map[$type] ?? '🔔';
}
function notificationPreview($type,$data=[]) {
    $text=trim(preg_replace('/\s+/',' ',strip_tags(notificationContent($type,$data))));
    return mb_strlen($text)>110 ? mb_substr($text,0,107).'...' : $text;
}

/**
 * SQLite CURRENT_TIMESTAMP хранит created_at в UTC.
 * Нельзя отдавать строку "YYYY-MM-DD HH:MM:SS" напрямую в strtotime():
 * PHP может трактовать её как локальное серверное время, из-за чего
 * браузер получает смещение (на сервере Galbraith это давало -3 часа).
 */
function notificationTimestampUtc($date) {
    if (!$date) return null;
    try {
        $dt = new DateTimeImmutable((string)$date, new DateTimeZone('UTC'));
        return $dt->getTimestamp();
    } catch (Throwable $e) {
        return null;
    }
}

function notificationUtcIso($date) {
    $ts = notificationTimestampUtc($date);
    return $ts === null ? '' : gmdate('Y-m-d\\TH:i:s\\Z', $ts);
}

function formatNotificationTime($date) {
    $ts = notificationTimestampUtc($date);
    if ($ts === null) return '';
    $d = time() - $ts;
    if ($d < 60) return 'только что';
    if ($d < 3600) return floor($d / 60) . ' мин. назад';
    if ($d < 86400) return floor($d / 3600) . ' ч. назад';
    if ($d < 604800) return floor($d / 86400) . ' дн. назад';
    // Историческая дата остаётся в UTC на сервере; UI переводит её в
    // локальный часовой пояс браузера через JS.
    return gmdate('d.m.Y H:i', $ts);
}

function generateLoginCode($userId) {
    $code = (string)random_int(100000, 999999);
    getDB()->prepare('INSERT INTO login_codes (user_id, code_hash, expires_at) VALUES (?, ?, ?)')
        ->execute([$userId, password_hash($code, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time()+600)]);
    notify($userId, 'login_code', ['code'=>$code]);
    return true;
}
function verifyLoginCode($userId, $code) {
    $stmt=getDB()->prepare('SELECT * FROM login_codes WHERE user_id=? AND used=0 ORDER BY id DESC LIMIT 1'); $stmt->execute([$userId]); $row=$stmt->fetch();
    if(!$row || strtotime($row['expires_at'])<time() || (int)$row['attempts']>=5) return false;
    getDB()->prepare('UPDATE login_codes SET attempts=attempts+1 WHERE id=?')->execute([$row['id']]);
    if(!password_verify(trim($code),$row['code_hash'])) return false;
    getDB()->prepare('UPDATE login_codes SET used=1 WHERE id=?')->execute([$row['id']]); return true;
}
function startLoginVerification($userId) { $_SESSION['pending_login_user_id']=$userId; $_SESSION['pending_login_started']=time(); generateLoginCode($userId); }
function clearLoginVerification() { unset($_SESSION['pending_login_user_id'],$_SESSION['pending_login_started']); }

function getUnreadNotificationsCount($userId) {
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getNotifications($userId, $limit = 20) {
    $stmt = getDB()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Staff (admin or moderator) role, from the /admin/login session. */
function staffRole() {
    return $_SESSION['admin_role'] ?? null;
}

function requireModerator() {
    if (!checkAdminSession()) {
        session_destroy();
        header('Location: /admin/login');
        exit;
    }
    if (!in_array(staffRole(), ['admin', 'moderator'], true)) {
        http_response_code(403);
        die('Доступ запрещён.');
    }
}

function requireSupportStaff() {
    if (!checkAdminSession()) {
        session_destroy();
        header('Location: /admin/login');
        exit;
    }
    if (!in_array(staffRole(), ['admin', 'support'], true)) {
        http_response_code(403);
        die('Доступ запрещён.');
    }
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Безопасно выводит текст сообщения чата и превращает http/https-ссылки
 * в кликабельные ссылки. HTML пользователя при этом никогда не исполняется.
 */
function chatMessageHtml($text) {
    $escaped = e((string)$text);
    $escaped = preg_replace(
        '~(?<!["=])(https?://[^\s<]+)~iu',
        '<a href="$1" target="_blank" rel="noopener noreferrer" class="chat-message-link">$1</a>',
        $escaped
    );
    return nl2br($escaped, false);
}

function isAutoDeliveryNotice($text) {
    return strpos((string)$text, '[[AUTO_DELIVERY_NOTICE]]') === 0;
}

function isAutoDeliveryItem($text) {
    return strpos((string)$text, '[[AUTO_DELIVERY_ITEM]]') === 0;
}

function cleanAutoDeliveryMarker($text) {
    return preg_replace('/^\[\[(?:AUTO_DELIVERY_NOTICE|AUTO_DELIVERY_ITEM|AUTO_DELIVERY_MESSAGE)\]\]\s*/', '', (string)$text);
}


/**
 * Единый рендерер чата заказа. Автовыдача собирается в один полноценный
 * визуальный блок, поэтому это одинаково работает для новых и старых заказов.
 * Старые сообщения без служебных маркеров определяются по историческим
 * текстам: «🤖 Автовыдача...» + последняя системная позиция перед сообщением
 * «Товар выдан автоматически. Заказ завершён.».
 */
function isAutoDeliveryLegacyNotice($text) {
    $text = trim((string)$text);
    return strpos($text, '🤖 Автовыдача:') === 0;
}

function isAutoDeliveryCompletion($text) {
    $text = trim((string)$text);
    return $text === 'Товар выдан автоматически. Заказ завершён.';
}

function renderAutoDeliveryCard($messages, $uid) {
    if (!$messages) return '';
    $first = $messages[0];
    $time = !empty($first['created_at']) ? date('H:i', strtotime($first['created_at'])) : '';
    $instruction = [];
    $item = null;

    foreach ($messages as $m) {
        $text = (string)($m['message'] ?? '');
        if (isAutoDeliveryNotice($text) || isAutoDeliveryLegacyNotice($text)) continue;
        if (strpos($text, '[[AUTO_DELIVERY_MESSAGE]]') === 0) { $instruction[] = cleanAutoDeliveryMarker($text); continue; }
        if (isAutoDeliveryCompletion($text)) continue;
        if (isAutoDeliveryItem($text)) {
            $item = cleanAutoDeliveryMarker($text);
            continue;
        }
        // В старом формате маркера позиции не было: последняя системная
        // реплика внутри блока до сообщения о завершении — это выданная позиция.
        $instruction[] = $text;
    }

    if ($item === null && $instruction) {
        $item = array_pop($instruction);
    }

    $html = '<div class="seller-chat-row auto-delivery-row" style="justify-content:center">';
    $html .= '<div class="auto-delivery-card">';
    $html .= '<div class="auto-delivery-card-head">';
    $html .= '<div class="auto-delivery-icon">⚡</div>';
    $html .= '<div><div class="auto-delivery-title">Автовыдача</div><div class="auto-delivery-subtitle">Товар выдан автоматически</div></div>';
    $html .= '<span class="auto-delivery-status">ГОТОВО</span>';
    $html .= '</div>';

    if ($instruction) {
        $html .= '<div class="auto-delivery-section auto-delivery-instruction">';
        $html .= '<div class="auto-delivery-section-label">ИНСТРУКЦИЯ ОТ ПРОДАВЦА</div>';
        $html .= '<div class="auto-delivery-section-text">';
        $html .= chatMessageHtml(implode("\n\n", $instruction));
        $html .= '</div></div>';
    }

    if ($item !== null && trim($item) !== '') {
        $html .= '<div class="auto-delivery-section auto-delivery-item-section">';
        $html .= '<div class="auto-delivery-section-label">ПОЛУЧЕННАЯ ПОЗИЦИЯ</div>';
        $html .= '<div class="auto-delivery-item-content">' . chatMessageHtml($item) . '</div>';
        $html .= '</div>';
    }

    $html .= '<div class="auto-delivery-card-foot"><span>✓</span> Заказ завершён автоматически</div>';
    $html .= '<div class="auto-delivery-card-time">' . e($time) . '</div>';
    $html .= '</div></div>';
    return $html;
}

function renderOrderChatMessages($messages, $uid) {
    $html = '';
    $count = count($messages);
    for ($i = 0; $i < $count; $i++) {
        $msg = $messages[$i];
        $text = (string)($msg['message'] ?? '');
        $startsAuto = isAutoDeliveryNotice($text) || isAutoDeliveryLegacyNotice($text);

        // Собираем как новые, так и старые записи автовыдачи в единый блок.
        if ($startsAuto) {
            $group = [$msg];
            $j = $i + 1;
            while ($j < $count && !empty($messages[$j]['is_system'])) {
                $group[] = $messages[$j];
                $done = isAutoDeliveryCompletion($messages[$j]['message'] ?? '');
                $j++;
                if ($done) break;
            }
            $html .= renderAutoDeliveryCard($group, $uid);
            $i = $j - 1;
            continue;
        }

        // Даже если в старом чате осталась только запись позиции с маркером,
        // показываем её полноценной карточкой.
        if (isAutoDeliveryItem($text)) {
            $html .= renderAutoDeliveryCard([$msg], $uid);
            continue;
        }

        if (!empty($msg['is_system'])) {
            $html .= '<div class="seller-chat-row" style="justify-content:center"><div class="seller-chat-bubble seller-chat-system" style="max-width:90%;text-align:center;white-space:pre-wrap;"><div class="seller-chat-meta"><strong>Система</strong><span>' . e(date('H:i', strtotime($msg['created_at']))) . '</span></div><div class="seller-chat-text">' . chatMessageHtml($text) . '</div></div></div>';
            continue;
        }

        $mine = (int)$msg['sender_id'] === (int)$uid;
        $admin = !empty($msg['is_admin']);
        $html .= '<div class="seller-chat-row ' . ($mine ? 'mine' : '') . '"><div class="seller-chat-bubble ' . ($admin ? 'admin-message' : '') . '"><div class="seller-chat-meta"><strong>' . e($msg['sender_name'] ?? 'Пользователь') . '</strong>' . ($admin ? '<span class="admin-tag">АДМИНИСТРАТОР</span>' : '') . '<span>' . e(date('H:i', strtotime($msg['created_at']))) . '</span></div><div class="seller-chat-text">' . chatMessageHtml($text) . '</div>';
        if (!empty($msg['file_path'])) {
            $url = e(UPLOAD_URL . $msg['file_path']);
            $html .= '<a class="chat-image-link" href="' . $url . '" target="_blank" rel="noopener"><img src="' . $url . '" alt="Изображение"></a>';
        }
        $html .= '</div></div>';
    }
    return $html;
}

function formatPrice($material) {
    if ($material['access_type'] === 'free') return 'FREE';
    $price = $material['discount_price'] ?? $material['price'];
    return $price ? number_format($price, 0, ',', ' ') . ' ₽' : '';
}

function finalPrice($material) {
    return $material['discount_price'] ?? $material['price'];
}

function getNews($limit = null) {
    try {
        $db = getDB();
        $sql = 'SELECT * FROM news ORDER BY is_pinned DESC, sort_order ASC, created_at DESC';
        if ($limit) $sql .= ' LIMIT ' . (int)$limit;
        return $db->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getNewsById($id) {
    try {
        $stmt = getDB()->prepare('SELECT * FROM news WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function getYouTubeVideoInfo($videoId) {
    if (!$videoId) return null;
    $cacheKey = 'yt_cache_' . $videoId;
    $cached = setting($cacheKey);
    if ($cached) {
        $data = json_decode($cached, true);
        if ($data && (time() - ($data['ts'] ?? 0)) < 3600) {
            return $data;
        }
    }
    $url = 'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=' . urlencode($videoId) . '&format=json';
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp) {
        $data = json_decode($resp, true);
        if ($data) {
            $data['video_id'] = $videoId;
            $data['ts'] = time();
            setSetting($cacheKey, json_encode($data));
            return $data;
        }
    }
    return null;
}

// --- TOTP / 2FA helpers ---
function generateTOTPSecret($length = 16) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

function base32Decode($input) {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(str_replace('=', '', $input));
    $output = '';
    $buffer = 0;
    $bitsLeft = 0;
    foreach (str_split($input) as $char) {
        $val = strpos($map, $char);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}

function getTOTPCode($secret, $timeStep = 30, $atTime = null) {
    $secret = base32Decode($secret);
    $atTime = $atTime ?? time();
    $time = pack('N*', 0) . pack('N*', intval($atTime / $timeStep));
    $hm = hash_hmac('sha1', $time, $secret, true);
    $offset = ord($hm[19]) & 0x0F;
    $code = ((ord($hm[$offset]) & 0x7F) << 24 |
             (ord($hm[$offset + 1]) & 0xFF) << 16 |
             (ord($hm[$offset + 2]) & 0xFF) << 8 |
             (ord($hm[$offset + 3]) & 0xFF)) % 1000000;
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

function verifyTOTPCode($secret, $code, $window = 1) {
    if (!$secret || !$code) return false;
    for ($i = -$window; $i <= $window; $i++) {
        $expected = getTOTPCode($secret, 30, time() + ($i * 30));
        if (hash_equals($expected, str_pad($code, 6, '0', STR_PAD_LEFT))) {
            return true;
        }
    }
    return false;
}

function getTOTPQRUrl($secret, $label = 'Galbraith Admin') {
    $issuer = urlencode('Galbraith');
    $label = urlencode($label);
    $secret = urlencode($secret);
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";
}

