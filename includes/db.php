<?php
require_once __DIR__ . '/../config.php';

/**
 * Обёртка над SQLite3, имитирующая PDO API.
 * Это позволяет не переписывать весь остальной код сайта.
 */
class SQLite3Wrapper {
    private $db;
    public function __construct($path) {
        $this->db = new SQLite3($path);
        $this->db->busyTimeout(5000);
    }
    public function prepare($sql) {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->db->lastErrorMsg());
        }
        return new SQLite3StmtWrapper($stmt);
    }
    public function query($sql) {
        $res = $this->db->query($sql);
        if (!$res) {
            throw new Exception('Query failed: ' . $this->db->lastErrorMsg());
        }
        return new SQLite3ResultWrapper($res);
    }
    public function exec($sql) {
        $res = $this->db->exec($sql);
        if ($res === false) {
            throw new Exception('Exec failed: ' . $this->db->lastErrorMsg());
        }
        return $res;
    }
    public function lastInsertId() {
        return $this->db->lastInsertRowID();
    }
}

class SQLite3StmtWrapper {
    private $stmt;
    private $result;
    public function __construct($stmt) {
        $this->stmt = $stmt;
    }
    public function execute($params = []) {
        foreach ($params as $i => $val) {
            if (is_int($val)) {
                $type = SQLITE3_INTEGER;
            } elseif (is_float($val)) {
                $type = SQLITE3_FLOAT;
            } elseif (is_null($val)) {
                $type = SQLITE3_NULL;
            } else {
                $type = SQLITE3_TEXT;
            }
            $this->stmt->bindValue($i + 1, $val, $type);
        }
        $this->result = $this->stmt->execute();
        if (!$this->result) {
            throw new Exception('Execute failed');
        }
        return true;
    }
    public function fetch() {
        return $this->result->fetchArray(SQLITE3_ASSOC);
    }
    public function fetchAll() {
        $rows = [];
        while ($row = $this->result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }
    public function fetchColumn() {
        $row = $this->result->fetchArray(SQLITE3_NUM);
        return $row ? $row[0] : null;
    }
}

class SQLite3ResultWrapper {
    private $result;
    public function __construct($result) {
        $this->result = $result;
    }
    public function fetch() {
        return $this->result->fetchArray(SQLITE3_ASSOC);
    }
    public function fetchAll() {
        $rows = [];
        while ($row = $this->result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }
    public function fetchColumn() {
        $row = $this->result->fetchArray(SQLITE3_NUM);
        return $row ? $row[0] : null;
    }
}

function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $isNew = !file_exists(DB_PATH);
            $db = new SQLite3Wrapper(DB_PATH);
            if ($isNew) {
                initDB($db);
            }
            // ensureTables() должна выполняться ВСЕГДА, а не только для уже
            // существующей базы: initDB() создаёт лишь базовый набор таблиц,
            // а все дополнительные таблицы и колонки, добавленные в более
            // поздних версиях (trader_products, orders, product_stock,
            // notifications, seller_messages, email_log, VK-поля и т.д.),
            // живут только в ensureTables(). На свежей базе она раньше вообще
            // не вызывалась — сайт на новой установке был обречён на 500 при
            // первом же обращении к любой из этих таблиц.
            ensureTables($db);
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Ошибка базы данных</title>';
            echo '<style>body{font-family:sans-serif;max-width:700px;margin:60px auto;padding:0 20px;color:#222;line-height:1.6}
                  .box{background:#fff3f3;border:1px solid #ffcccc;border-radius:8px;padding:20px;margin-top:16px}
                  code{background:#f2f2f2;padding:2px 6px;border-radius:4px;word-break:break-all}</style></head><body>';
            echo '<h1>⚠️ Не удалось открыть базу данных</h1><div class="box"><p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p>Проверьте права доступа на файл <code>database.sqlite</code> и на папку сайта (нужна запись для пользователя веб-сервера), а также что расширение PHP <code>sqlite3</code> включено.</p></div></body></html>';
            exit;
        }
    }
    return $db;
}

function ensureTables($db) {
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
    $existing = array_column($tables, 'name');
    if (!in_array('news', $existing)) {
        $db->exec('
            CREATE TABLE news (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                image_url VARCHAR(2048),
                url VARCHAR(2048),
                is_pinned INTEGER DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }
    // Add totp_secret to users if missing
    $cols = $db->query("PRAGMA table_info(users)")->fetchAll();
    $colNames = array_column($cols, 'name');
    if (!in_array('totp_secret', $colNames)) {
        $db->exec('ALTER TABLE users ADD COLUMN totp_secret VARCHAR(32)');
    }

    // ===== v2.0 migration: marketplace / escrow / roles =====
    $isFirstV2Migration = !in_array('role', $colNames);

    $userColumns = [
        'role' => "VARCHAR(20) DEFAULT 'user'",
        'google_id' => 'VARCHAR(255)',
        'vk_id' => 'VARCHAR(255)',
        'avatar' => 'VARCHAR(255)',
        'banned_until' => 'TIMESTAMP',
        'ban_reason' => 'TEXT',
        'twofa_enabled' => 'INTEGER DEFAULT 0',
        'card_number' => 'VARCHAR(255)',
        'full_name' => 'VARCHAR(255)',
        'contact_info' => 'VARCHAR(255)',
        'commission_percent' => 'DECIMAL(5,2)',
        'personal_data_consent_at' => 'TIMESTAMP',
        'personal_data_consent_version' => 'VARCHAR(32)',
    ];
    foreach ($userColumns as $col => $def) {
        if (!in_array($col, $colNames)) {
            $db->exec("ALTER TABLE users ADD COLUMN $col $def");
        }
    }
    // Legal/payment metadata migrations
    if (in_array('orders', $existing)) {
        $orderCols = $db->query("PRAGMA table_info(orders)")->fetchAll();
        $orderColNames = array_column($orderCols, 'name');
        foreach (['payment_transaction_id' => 'VARCHAR(255)', 'payment_confirmed_at' => 'TIMESTAMP'] as $col => $def) {
            if (!in_array($col, $orderColNames)) $db->exec("ALTER TABLE orders ADD COLUMN $col $def");
        }
    }
    if (!in_array('legal_documents', $existing)) {
        $db->exec('CREATE TABLE legal_documents (id INTEGER PRIMARY KEY AUTOINCREMENT, slug VARCHAR(100) UNIQUE NOT NULL, title VARCHAR(255) NOT NULL, content TEXT NOT NULL, version VARCHAR(32) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    }
    $legalCount=(int)$db->query("SELECT COUNT(*) FROM legal_documents")->fetchColumn();
    if($legalCount===0){
        $docs=[
        ['privacy-policy','Политика конфиденциальности','<p><strong>Оператор:</strong> GALBRAITH MODS (не зарегистрировано), владелец — Пришляк Илья. Основной контакт: iprishlyak16@gmail.com. Для чеков и оплаты: galbraith-sup@mail.ru. Адрес: Россия, г. Санкт-Петербург.</p><h2>1. Данные</h2><p>Сайт использует только данные, необходимые для регистрации, авторизации, оформления и сопровождения заказов, отправки подтверждений и работы поддержки: логин, email и данные, которые пользователь добровольно предоставляет при использовании соответствующих функций.</p><h2>2. Цели и основания</h2><p>Данные обрабатываются для исполнения пользовательского соглашения, работы аккаунта и заказов, а также на основании согласия пользователя там, где оно требуется.</p><h2>3. Cookies и технологии</h2><p>Сайт может использовать cookies, localStorage, CAPTCHA, аналитику, сторонние API, платёжные SDK или шрифты только в той мере, в какой они фактически присутствуют в установленной версии сайта. Конкретный перечень подлежит проверке конфигурации перед публикацией.</p><h2>4. Хранение и передача</h2><p>Данные хранятся столько, сколько необходимо для указанных целей и выполнения обязанностей оператора. Передача третьим лицам допускается только когда это необходимо для работы сервиса, исполнения заказа, отправки сообщений или предусмотрено законом.</p><h2>5. Права</h2><p>Пользователь вправе запросить сведения об обработке, исправление или удаление данных в пределах применимых требований, а также отозвать согласие, обратившись на iprishlyak16@gmail.com.</p><h2>6. Контакты</h2><p>iprishlyak16@gmail.com · galbraith-sup@mail.ru</p>','1.0'],
        ['personal-data-consent','Согласие на обработку персональных данных','<p>Я, пользователь сайта GALBRAITH MODS, добровольно даю согласие на обработку предоставленных мной персональных данных, включая логин и email, в целях регистрации и ведения аккаунта, авторизации, оформления и сопровождения заказов, отправки необходимых уведомлений и работы поддержки.</p><p>Согласие действует до его отзыва либо до прекращения необходимости обработки. Отзыв можно направить на iprishlyak16@gmail.com. Отзыв не отменяет законность обработки, выполненной до его получения.</p><p>Версия документа: 1.0.</p>','1.0'],
        ['terms','Пользовательское соглашение','<p><strong>GALBRAITH MODS</strong> предоставляет пользователю доступ к сайту, аккаунту, каталогу и цифровым товарам.</p><h2>1. Использование</h2><p>Пользователь обязан предоставлять достоверные данные, не передавать доступ к аккаунту третьим лицам и не использовать сайт для нарушения закона или работы сервиса.</p><h2>2. Регистрация</h2><p>Регистрация требует согласия на обработку персональных данных. Пользователь отвечает за сохранность своих данных доступа.</p><h2>3. Покупки и цифровые товары</h2><p>Заказ создаётся через существующий функционал сайта. Передача цифрового товара выполняется после подтверждения оплаты и в соответствии со статусом заказа.</p><h2>4. Оплата и возвраты</h2><p>Порядок оплаты определяется фактически подключённой платёжной инфраструктурой. Возвраты рассматриваются с учётом статуса заказа, характера цифрового товара и применимых правил.</p><h2>5. Ответственность</h2><p>Стороны несут ответственность в пределах применимого законодательства и настоящих условий.</p><h2>6. Интеллектуальная собственность</h2><p>Материалы сайта и цифровые товары нельзя копировать, распространять или перепродавать с нарушением прав правообладателей.</p><h2>7. Поддержка и блокировка</h2><p>По вопросам заказа обращайтесь через поддержку сайта или на galbraith-sup@mail.ru. Аккаунт может быть ограничен или заблокирован при нарушении правил, злоупотреблении сервисом или попытках мошенничества.</p>','1.0']];
        foreach($docs as $d) $db->prepare('INSERT INTO legal_documents(slug,title,content,version) VALUES(?,?,?,?)')->execute($d);
    }

    if ($isFirstV2Migration) {
        // Prior to v2.0 the users table held staff accounts only (created via admin/login).
        // Mark all pre-existing accounts as admin so nobody is locked out after the upgrade.
        $db->exec("UPDATE users SET role = 'admin' WHERE role IS NULL OR role = 'user'");
        $db->exec("UPDATE users SET twofa_enabled = 1 WHERE totp_secret IS NOT NULL AND totp_secret != ''");
    }

    $newTables = [
        'trader_products' => '
            CREATE TABLE trader_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                description TEXT NOT NULL,
                category_id INTEGER,
                price DECIMAL(10,2) DEFAULT 0.00,
                file_url VARCHAR(2048) NOT NULL,
                youtube_url VARCHAR(255),
                cover_path VARCHAR(255),
                screenshots TEXT,
                game_version VARCHAR(50),
                file_size VARCHAR(50),
                downloads_count INTEGER DEFAULT 0,
                views_count INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'orders' => '
            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                buyer_id INTEGER NOT NULL,
                seller_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                discount_percent INTEGER DEFAULT 0,
                discount_amount DECIMAL(10,2) DEFAULT 0,
                final_amount DECIMAL(10,2) NOT NULL,
                commission_percent DECIMAL(5,2) NOT NULL,
                commission_amount DECIMAL(10,2) NOT NULL,
                net_amount DECIMAL(10,2) NOT NULL,
                status VARCHAR(30) DEFAULT "awaiting_confirmation",
                buyer_confirmed_at TIMESTAMP,
                auto_confirm_at TIMESTAMP,
                dispute_opened_at TIMESTAMP,
                dispute_resolved_at TIMESTAMP,
                payment_transaction_id VARCHAR(255),
                payment_confirmed_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'messages' => '
            CREATE TABLE messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                sender_id INTEGER,
                message TEXT NOT NULL,
                file_path VARCHAR(255),
                is_system INTEGER DEFAULT 0,
                is_read INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'withdrawal_requests' => '
            CREATE TABLE withdrawal_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                card_number VARCHAR(255),
                status VARCHAR(20) DEFAULT "pending",
                admin_comment TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP
            )
        ',
        'user_balances' => '
            CREATE TABLE user_balances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE NOT NULL,
                balance DECIMAL(10,2) DEFAULT 0.00
            )
        ',
        'transactions' => '
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                order_id INTEGER,
                type VARCHAR(20) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'reviews' => '
            CREATE TABLE reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER UNIQUE NOT NULL,
                seller_id INTEGER NOT NULL,
                buyer_id INTEGER NOT NULL,
                rating INTEGER NOT NULL,
                comment TEXT,
                response TEXT,
                is_hidden INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'notifications' => '
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type VARCHAR(50) NOT NULL,
                data TEXT,
                is_read INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'seller_messages' => '
            CREATE TABLE seller_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seller_id INTEGER NOT NULL,
                buyer_id INTEGER NOT NULL,
                product_id INTEGER,
                message TEXT NOT NULL,
                response TEXT,
                is_read_seller INTEGER DEFAULT 0,
                is_read_buyer INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'bonuses' => '
            CREATE TABLE bonuses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE NOT NULL,
                balance INTEGER DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'bonus_transactions' => '
            CREATE TABLE bonus_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                order_id INTEGER,
                amount INTEGER NOT NULL,
                type VARCHAR(20) NOT NULL,
                description VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'admin_logs' => '
            CREATE TABLE admin_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',

        // ===== v2.1: trader bonus system, tasks, referrals, discipline =====
        'trader_bonuses' => '
            CREATE TABLE trader_bonuses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE NOT NULL,
                balance DECIMAL(10,2) DEFAULT 0.00,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'trader_bonus_transactions' => '
            CREATE TABLE trader_bonus_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                order_id INTEGER,
                amount DECIMAL(10,2) NOT NULL,
                type VARCHAR(20) DEFAULT "auto_accrual",
                reason VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'trader_bonus_activations' => '
            CREATE TABLE trader_bonus_activations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                option_type VARCHAR(50) NOT NULL,
                value DECIMAL(10,2),
                status VARCHAR(20) DEFAULT "pending",
                expires_at TIMESTAMP,
                used_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'bonus_tasks' => '
            CREATE TABLE bonus_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                condition_type VARCHAR(50) NOT NULL,
                condition_value INTEGER NOT NULL,
                bonus_amount DECIMAL(10,2) NOT NULL,
                deadline_days INTEGER,
                is_active INTEGER DEFAULT 1,
                is_repeatable INTEGER DEFAULT 0,
                is_deleted INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'trader_task_progress' => '
            CREATE TABLE trader_task_progress (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                task_id INTEGER NOT NULL,
                progress INTEGER DEFAULT 0,
                baseline_value INTEGER DEFAULT 0,
                is_completed INTEGER DEFAULT 0,
                completed_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'referrals' => '
            CREATE TABLE referrals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                referrer_id INTEGER NOT NULL,
                referred_user_id INTEGER UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP,
                status VARCHAR(20) DEFAULT "active"
            )
        ',
        'referral_bonuses' => '
            CREATE TABLE referral_bonuses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                referrer_id INTEGER NOT NULL,
                referred_user_id INTEGER NOT NULL,
                order_id INTEGER,
                amount DECIMAL(10,2) NOT NULL,
                reason VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'trader_penalties' => '
            CREATE TABLE trader_penalties (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                reason VARCHAR(255) NOT NULL,
                issued_by INTEGER,
                issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP,
                is_active INTEGER DEFAULT 1,
                comment TEXT
            )
        ',
        'admin_material_downloads' => '
            CREATE TABLE admin_material_downloads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                material_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, material_id)
            )
        ',
        'admin_material_purchases' => '
            CREATE TABLE admin_material_purchases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                material_id INTEGER NOT NULL,
                status VARCHAR(20) DEFAULT "claimed",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, material_id)
            )
        ',
    ];
    foreach ($newTables as $name => $sql) {
        if (!in_array($name, $existing)) {
            $db->exec($sql);
        }
    }

    // v2.1: marketplace ordering columns on trader_products (additive, may already exist as part of a fresh v2.1 install)
    $tpCols = array_column($db->query('PRAGMA table_info(trader_products)')->fetchAll(), 'name');
    if (!in_array('sort_order', $tpCols)) { $db->exec('ALTER TABLE trader_products ADD COLUMN sort_order INTEGER DEFAULT 0'); }
    if (!in_array('is_pinned', $tpCols)) { $db->exec('ALTER TABLE trader_products ADD COLUMN is_pinned INTEGER DEFAULT 0'); }

    // v2.1: extra user columns (referral tracking, trader freeze, top-seller boost)
    $userCols2 = array_column($db->query('PRAGMA table_info(users)')->fetchAll(), 'name');
    foreach (['referred_by' => 'INTEGER', 'trader_frozen' => 'INTEGER DEFAULT 0', 'top_seller_until' => 'TIMESTAMP', 'extra_product_slots' => 'INTEGER DEFAULT 0'] as $col => $def) {
        if (!in_array($col, $userCols2)) { $db->exec("ALTER TABLE users ADD COLUMN $col $def"); }
    }

    // v2.1: withdrawal fee tracking columns
    $wrCols = array_column($db->query('PRAGMA table_info(withdrawal_requests)')->fetchAll(), 'name');
    foreach ([
        'fee_percent' => 'DECIMAL(5,2) DEFAULT 0',
        'fee_amount' => 'DECIMAL(10,2) DEFAULT 0',
        'commission_percent_snapshot' => 'DECIMAL(5,2)',
        'gross_sales_snapshot' => 'DECIMAL(10,2)',
        'commission_amount_snapshot' => 'DECIMAL(10,2)',
    ] as $col => $def) {
        if (!in_array($col, $wrCols)) { $db->exec("ALTER TABLE withdrawal_requests ADD COLUMN $col $def"); }
    }

    // Default v2.1 settings
    $isFirstV21Migration = !in_array('trader_bonuses', $existing);
    if ($isFirstV21Migration) {
        global $default_settings_v21;
        $stmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
        foreach (($default_settings_v21 ?? []) as $k => $v) {
            $stmt->execute([$k, $v]);
        }
    }

    // ===== v3.0: email auth, product moderation, favorites/subscriptions =====
    $userCols3 = array_column($db->query('PRAGMA table_info(users)')->fetchAll(), 'name');
    foreach (['login' => 'VARCHAR(20)', 'email_verified_at' => 'TIMESTAMP'] as $col => $def) {
        if (!in_array($col, $userCols3)) { $db->exec("ALTER TABLE users ADD COLUMN $col $def"); }
    }
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_login ON users(login)');
    // Pre-existing accounts (OAuth users, staff) are treated as already verified so nobody gets locked out.
    if (!in_array('email_verified_at', $userCols3)) {
        $db->exec("UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL");
    }

    $tpCols3 = array_column($db->query('PRAGMA table_info(trader_products)')->fetchAll(), 'name');
    foreach ([
        'status' => "VARCHAR(20) DEFAULT 'approved'", // existing rows predate moderation; treat as already approved
        'moderation_comment' => 'TEXT',
        'version' => 'INTEGER DEFAULT 1',
    ] as $col => $def) {
        if (!in_array($col, $tpCols3)) { $db->exec("ALTER TABLE trader_products ADD COLUMN $col $def"); }
    }

    $newTables3 = [
        'password_resets' => '
            CREATE TABLE password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                used INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'email_verifications' => '
            CREATE TABLE email_verifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                used INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'product_history' => '
            CREATE TABLE product_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                user_id INTEGER,
                changes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'favorites' => '
            CREATE TABLE favorites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, product_id)
            )
        ',
        'seller_subscriptions' => '
            CREATE TABLE seller_subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                seller_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, seller_id)
            )
        ',
        'product_tasks' => '
            CREATE TABLE product_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                task_id INTEGER NOT NULL,
                UNIQUE(product_id, task_id)
            )
        ',
    ];
    foreach ($newTables3 as $name => $sql) {
        if (!in_array($name, $existing)) { $db->exec($sql); }
    }

    $isFirstV3Migration = !in_array('password_resets', $existing);
    if ($isFirstV3Migration) {
        global $default_settings_v3;
        $stmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
        foreach (($default_settings_v3 ?? []) as $k => $v) {
            $stmt->execute([$k, $v]);
        }
    }

    // ===== v3.1: auto-delivery (FunPay-style instant stock delivery) =====
    $tpCols4 = array_column($db->query('PRAGMA table_info(trader_products)')->fetchAll(), 'name');
    if (!in_array('auto_delivery', $tpCols4)) {
        $db->exec('ALTER TABLE trader_products ADD COLUMN auto_delivery INTEGER DEFAULT 0');
    }
    if (!in_array('product_stock', $existing)) {
        $db->exec('
            CREATE TABLE product_stock (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                is_used INTEGER DEFAULT 0,
                order_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                used_at TIMESTAMP
            )
        ');
    }

    // ===== v4.0: marketplace/chat/security/promo refinements =====
    $userCols4 = array_column($db->query("PRAGMA table_info(users)")->fetchAll(), 'name');
    foreach ([
        'referral_code' => 'VARCHAR(32)',
    ] as $col => $def) {
        if (!in_array($col, $userCols4)) { $db->exec("ALTER TABLE users ADD COLUMN $col $def"); }
    }
    $tpCols5 = array_column($db->query("PRAGMA table_info(trader_products)")->fetchAll(), 'name');
    foreach ([
        'auto_delivery_content' => 'TEXT',
    ] as $col => $def) {
        if (!in_array($col, $tpCols5)) { $db->exec("ALTER TABLE trader_products ADD COLUMN $col $def"); }
    }
    $msgCols4 = array_column($db->query("PRAGMA table_info(messages)")->fetchAll(), 'name');
    foreach ([
        'is_admin' => 'INTEGER DEFAULT 0',
    ] as $col => $def) {
        if (!in_array($col, $msgCols4)) { $db->exec("ALTER TABLE messages ADD COLUMN $col $def"); }
    }
    $sellerMsgCols4 = array_column($db->query("PRAGMA table_info(seller_messages)")->fetchAll(), 'name');
    foreach ([
        'sender_id' => 'INTEGER',
        'attachment_path' => 'VARCHAR(255)',
        'is_admin' => 'INTEGER DEFAULT 0',
    ] as $col => $def) {
        if (!in_array($col, $sellerMsgCols4)) { $db->exec("ALTER TABLE seller_messages ADD COLUMN $col $def"); }
    }

    // ===== v3.5: VK contacts + persistent broadcasts =====
    $vkBroadcastTables = [
        'vk_contacts' => 'CREATE TABLE vk_contacts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            peer_id VARCHAR(64) UNIQUE NOT NULL,
            user_id INTEGER,
            first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )',
        'vk_broadcasts' => 'CREATE TABLE vk_broadcasts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            url VARCHAR(2048),
            status VARCHAR(20) DEFAULT "draft",
            total INTEGER DEFAULT 0,
            attempts INTEGER DEFAULT 0,
            sent INTEGER DEFAULT 0,
            failed INTEGER DEFAULT 0,
            next_index INTEGER DEFAULT 0,
            created_by INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP,
            finished_at TIMESTAMP,
            paused_at TIMESTAMP
        )',
        'vk_broadcast_recipients' => 'CREATE TABLE vk_broadcast_recipients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            broadcast_id INTEGER NOT NULL,
            peer_id VARCHAR(64) NOT NULL,
            position INTEGER NOT NULL,
            status VARCHAR(20) DEFAULT "pending",
            attempted_at TIMESTAMP,
            sent_at TIMESTAMP,
            error TEXT,
            UNIQUE(broadcast_id, peer_id)
        )',
    ];
    foreach ($vkBroadcastTables as $name => $sql) {
        if (!in_array($name, $existing)) { $db->exec($sql); }
    }
    // Индексы для быстрой выборки получателей и статистики рассылок.
    $db->exec('CREATE INDEX IF NOT EXISTS idx_vk_contacts_last_seen ON vk_contacts(last_seen_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_vk_broadcast_recipients_queue ON vk_broadcast_recipients(broadcast_id, status, position)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_vk_broadcasts_status ON vk_broadcasts(status, created_at)');

    // ===== v3.4: VK-уведомления + фиксированное сообщение автовыдачи =====
    $userColsVk = array_column($db->query("PRAGMA table_info(users)")->fetchAll(), 'name');
    foreach ([
        'vk_bot_peer_id' => 'VARCHAR(64)',
        'vk_bot_link_code' => 'VARCHAR(32)',
        'login_code_via_vk' => 'INTEGER DEFAULT 1',
    ] as $col => $def) {
        if (!in_array($col, $userColsVk)) { $db->exec("ALTER TABLE users ADD COLUMN $col $def"); }
    }
    $tpColsVk = array_column($db->query("PRAGMA table_info(trader_products)")->fetchAll(), 'name');
    foreach ([
        'auto_delivery_message' => 'TEXT',
    ] as $col => $def) {
        if (!in_array($col, $tpColsVk)) { $db->exec("ALTER TABLE trader_products ADD COLUMN $col $def"); }
    }
    $stmtVk = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ([
        'vk_bot_token' => '',
        'vk_group_id' => '',
        'vk_group_name' => '',
        'vk_confirmation_code' => '',
        'vk_callback_secret' => '',
        'vk_bot_enabled' => '0',
        'vk_api_version' => '5.199',
    ] as $k => $v) {
        $stmtVk->execute([$k, $v]);
    }

    $orderCols4 = array_column($db->query("PRAGMA table_info(orders)")->fetchAll(), 'name');
    foreach ([
        'promo_code' => 'VARCHAR(50)',
        'promo_discount_amount' => 'DECIMAL(10,2) DEFAULT 0',
    ] as $col => $def) {
        if (!in_array($col, $orderCols4)) { $db->exec("ALTER TABLE orders ADD COLUMN $col $def"); }
    }
    $newTables4 = [
        'promo_codes' => '
            CREATE TABLE promo_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code VARCHAR(50) UNIQUE NOT NULL,
                discount_type VARCHAR(10) DEFAULT "percent",
                discount_value DECIMAL(10,2) NOT NULL,
                max_uses INTEGER DEFAULT 0,
                used_count INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                expires_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
        'promo_code_usages' => '
            CREATE TABLE promo_code_usages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                promo_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                order_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(promo_id, user_id, order_id)
            )
        ',
        'login_codes' => '
            CREATE TABLE login_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                attempts INTEGER DEFAULT 0,
                used INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ',
    ];
    foreach ($newTables4 as $name => $sql) {
        if (!in_array($name, $existing)) { $db->exec($sql); }
    }
    $db->exec("UPDATE users SET referral_code = COALESCE(referral_code, 'u' || id) WHERE referral_code IS NULL OR referral_code = ''");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_referral_code ON users(referral_code)");
    foreach ([
        'login_email_code_enabled' => '1',
        'promo_enabled' => '1',
    ] as $k => $v) {
        $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('$k', '$v')");
    }

    // ===== v3.2: maintenance mode (always ensure these exist, independent of install version) =====
    $stmt = $db->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    foreach ([
        'maintenance_mode' => '0',
        'maintenance_message' => 'Мы проводим технические работы, чтобы сайт стал ещё лучше. Скоро вернёмся!',
        'maintenance_started_at' => '',
        'maintenance_bypass_code' => '',
    ] as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    // ===== v3.3: журнал отправки писем (для диагностики SMTP) =====
    if (!in_array('email_log', $existing)) {
        $db->exec('
            CREATE TABLE email_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255),
                status VARCHAR(20) NOT NULL,
                error TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    // ===== v4.1: промокоды — мин. сумма заказа + логи экономии; 2FA с подтверждением; TG-код входа =====
    // Важно: этот блок должен идти ПОСЛЕ создания promo_codes/promo_code_usages/users
    // выше по функции — PRAGMA table_info() на ещё не созданной таблице тихо
    // возвращает пустой список, а следующий за ним ALTER TABLE падает с
    // "no such table". Раньше блок стоял раньше — ловили ошибку на чистой БД.
    $promoCols41 = array_column($db->query("PRAGMA table_info(promo_codes)")->fetchAll(), 'name');
    if (!in_array('min_order_amount', $promoCols41)) { $db->exec('ALTER TABLE promo_codes ADD COLUMN min_order_amount DECIMAL(10,2) DEFAULT 0'); }
    $usageCols41 = array_column($db->query("PRAGMA table_info(promo_code_usages)")->fetchAll(), 'name');
    if (!in_array('discount_amount', $usageCols41)) { $db->exec('ALTER TABLE promo_code_usages ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0'); }
    $userCols41 = array_column($db->query("PRAGMA table_info(users)")->fetchAll(), 'name');
    foreach ([
        'totp_pending' => 'INTEGER DEFAULT 0',
        'login_code_via_vk' => 'INTEGER DEFAULT 1',
    ] as $col => $def) {
        if (!in_array($col, $userCols41)) { $db->exec("ALTER TABLE users ADD COLUMN $col $def"); }
    }

    // ===== v4.4: редизайн — принудительно обновляем ранее сохранённый акцент =====
    // Настройка accent_default хранится в БД (кастомайзер темы) и перекрывает
    // цвет из style.css через инлайн-<style> в шапке. Простая смена дефолта в
    // коде НЕ подхватывалась — старое значение уже лежало в базе с первой
    // установки сайта. Форсируем обновление один раз (флаг design_v2_applied),
    // дальше не трогаем — если админ перекрасит через кастомайзер, это его выбор.
    if (setting('design_v2_applied', '') !== '1') {
        setSetting('accent_default', '#E81C5A');
        setSetting('design_v2_applied', '1');
    }

    // ===== v4.5: постоянный вход («запомнить браузер») =====
    if (!in_array('remember_tokens', $existing)) {
        $db->exec('
            CREATE TABLE remember_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                selector VARCHAR(24) UNIQUE NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                user_agent VARCHAR(255),
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    // ===== v4.7: доп. роли, привязка промокодов к продавцу/товару =====
    // users.role остаётся «основной» ролью (её проверяют напрямую в SQL в
    // нескольких местах — не трогаем, чтобы не переписывать эти запросы).
    // Дополнительные роли живут отдельной таблицей — их проверяет hasRole().
    if (!in_array('user_roles', $existing)) {
        $db->exec('
            CREATE TABLE user_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                role VARCHAR(30) NOT NULL,
                UNIQUE(user_id, role)
            )
        ');
    }
    $promoCols47 = array_column($db->query("PRAGMA table_info(promo_codes)")->fetchAll(), 'name');
    foreach ([
        'seller_id' => 'INTEGER',
        'product_id' => 'INTEGER',
    ] as $col => $def) {
        if (!in_array($col, $promoCols47)) { $db->exec("ALTER TABLE promo_codes ADD COLUMN $col $def"); }
    }

    // ===== v4.8: система техподдержки (чат вместо тикетов) =====
    // Один постоянный чат на пользователя (не отдельные обращения) — проще
    // и для пользователя, и для агента: вся история в одном месте.
    if (!in_array('support_chats', $existing)) {
        $db->exec('
            CREATE TABLE support_chats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE NOT NULL,
                status VARCHAR(20) DEFAULT "open",
                assigned_agent_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }
    if (!in_array('support_messages', $existing)) {
        $db->exec('
            CREATE TABLE support_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER NOT NULL,
                sender_id INTEGER,
                message TEXT,
                attachment_path VARCHAR(255),
                is_agent INTEGER DEFAULT 0,
                is_internal_note INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    // ===== Шаблоны товаров для торговцев =====
    // Позволяют продавцу сохранить набор часто повторяющихся полей (описание,
    // категория, версия игры и т.д.) и быстро подставить их при создании нового
    // товара, не вводя всё заново каждый раз.
    if (!in_array('trader_product_templates', $existing)) {
        $db->exec('
            CREATE TABLE trader_product_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                title VARCHAR(255),
                description TEXT,
                category_id INTEGER,
                price DECIMAL(10,2) DEFAULT 0.00,
                file_url VARCHAR(2048),
                youtube_url VARCHAR(255),
                game_version VARCHAR(50),
                file_size VARCHAR(50),
                cover_path VARCHAR(255),
                screenshots TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    // ===== Отложенная публикация материалов («премьера») =====
    // publish_at = NULL — материал опубликован сразу (как раньше, обратная
    // совместимость). publish_at в будущем — материал скрыт из каталога до
    // наступления даты и показывается на детальной странице как «премьера»
    // со счётчиком обратного отсчёта.
    // ===== Комментарии: только для авторизованных + ответы =====
    $commentCols = array_column($db->query('PRAGMA table_info(comments)')->fetchAll(), 'name');
    if (!in_array('parent_id', $commentCols)) $db->exec('ALTER TABLE comments ADD COLUMN parent_id INTEGER');
    if (!in_array('user_id', $commentCols)) $db->exec('ALTER TABLE comments ADD COLUMN user_id INTEGER');

    // ===== Напоминания о премьере + рейтинги материалов =====
    $db->exec("CREATE TABLE IF NOT EXISTS premiere_reminders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        material_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        notified INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(material_id, user_id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS material_ratings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        material_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        rating INTEGER NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(material_id, user_id)
    )");

    $materialsColsPub = array_column($db->query('PRAGMA table_info(materials)')->fetchAll(), 'name');
    if (!in_array('publish_at', $materialsColsPub)) {
        $db->exec('ALTER TABLE materials ADD COLUMN publish_at TIMESTAMP');
    }
}

function initDB($db) {
    $tables = [
        'CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) UNIQUE NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            description TEXT NOT NULL,
            access_type VARCHAR(10) DEFAULT "free",
            price DECIMAL(10,2),
            discount_price DECIMAL(10,2),
            file_url VARCHAR(2048) NOT NULL,
            youtube_url VARCHAR(255),
            cover_path VARCHAR(255),
            screenshots TEXT,
            game_version VARCHAR(50),
            file_size VARCHAR(50),
            downloads_count INTEGER DEFAULT 0,
            views_count INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            url VARCHAR(2048) NOT NULL,
            is_active INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            material_id INTEGER NOT NULL,
            author_name VARCHAR(30) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject VARCHAR(255) NOT NULL,
            contact VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT "new",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key VARCHAR(255) UNIQUE NOT NULL,
            value TEXT
        )',
        'CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            show_in_menu INTEGER DEFAULT 0,
            menu_order INTEGER DEFAULT 0,
            meta_title VARCHAR(255),
            meta_description TEXT,
            meta_keywords VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100),
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            totp_secret VARCHAR(32),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )',
        'CREATE TABLE email_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            status VARCHAR(20) NOT NULL,
            error TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    ];

    foreach ($tables as $sql) {
        $db->exec($sql);
    }

    // Seed defaults
    global $default_settings;
    $stmt = $db->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
    foreach ($default_settings as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    $db->exec('INSERT INTO categories (name, slug) VALUES ("Без категории", "bez-kategorii")');
    $db->exec('INSERT INTO tasks (title, url, sort_order) VALUES ("Подписка на YouTube", "https://www.youtube.com/@galbraith1629", 1)');
    $db->exec('INSERT INTO tasks (title, url, sort_order) VALUES ("Подписка на VK", "https://vk.ru/galbraith_yt", 2)');

    // Default admin: admin@galbraith-yt.ru / ChangeMe123!
    $hash = password_hash('ChangeMe123!', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (name, email, password) VALUES ('Galbraith', 'admin@galbraith-yt.ru', '$hash')");
}
