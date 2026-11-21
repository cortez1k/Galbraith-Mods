<?php
// =====================================================================
// Генератор ников (Имя_Фамилия для RP-серверов SA-MP / CRMP)
// Словари хранятся в БД (nick_words), стартовый набор — includes/nick_seed.json.
// Совместимо с PHP 7.3: без стрелочных функций и PHP 8-синтаксиса.
// =====================================================================

define('NICK_MAX_LEN', 24); // лимит SA-MP на длину ника

function nickOriginLabels() {
    return [
        'ru' => 'Русские', 'en' => 'Американские / британские', 'it' => 'Итальянские', 'es' => 'Латино',
        'kav' => 'Кавказские', 'ir' => 'Ирландские', 'de' => 'Немецкие', 'jp' => 'Японские',
    ];
}

function nickGroupDefs() {
    return [
        'any'    => ['label' => 'Любой',        'o' => ['ru' => 18, 'en' => 24, 'it' => 10, 'es' => 12, 'kav' => 6, 'ir' => 6, 'de' => 8, 'jp' => 4], 'tag' => []],
        'gangs'  => ['label' => 'Банды',        'o' => ['en' => 50, 'es' => 32, 'ru' => 8, 'ir' => 5, 'de' => 5], 'tag' => ['s' => 0.3]],
        'mafia'  => ['label' => 'Мафия',        'o' => ['it' => 45, 'ru' => 28, 'ir' => 10, 'es' => 9, 'jp' => 8], 'tag' => []],
        'gov'    => ['label' => 'Госструктуры', 'o' => ['en' => 42, 'ru' => 38, 'de' => 20], 'tag' => []],
        'elite'  => ['label' => 'Элита',        'o' => ['en' => 40, 'it' => 20, 'de' => 15, 'ru' => 15, 'es' => 10], 'tag' => ['e' => 0.65]],
        'street' => ['label' => 'Улица',        'o' => ['en' => 40, 'es' => 25, 'ru' => 20, 'ir' => 10, 'de' => 5], 'tag' => ['s' => 0.55]],
    ];
}

// ---------- Слова: очистка и транслитерация ----------
function nickTranslit($s) {
    static $map = null;
    if ($map === null) {
        $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    }
    return strtr(mb_strtolower((string)$s, 'UTF-8'), $map);
}

function nickCleanWord($w) {
    $w = nickTranslit(trim((string)$w));
    $w = preg_replace('/[^A-Za-z]/', '', $w);
    if (strlen($w) < 2 || strlen($w) > 16) return '';
    return ucfirst(strtolower($w));
}

function nickValidNick($n) {
    return is_string($n) && strlen($n) <= NICK_MAX_LEN && preg_match('/^[A-Z][a-z]{1,15}_[A-Z][a-z]{1,15}$/', $n) === 1;
}

// ---------- БД ----------
function nickDb() {
    static $ready = false;
    $db = getDB();
    if ($ready) return $db;
    $db->exec("CREATE TABLE IF NOT EXISTS nick_words (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kind TEXT NOT NULL, origin TEXT NOT NULL, gender TEXT NOT NULL DEFAULT 'u',
        era TEXT NOT NULL DEFAULT 'any', tag TEXT NOT NULL DEFAULT '', word TEXT NOT NULL,
        enabled INTEGER NOT NULL DEFAULT 1, source TEXT DEFAULT 'seed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(kind, origin, gender, word)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS nick_suggestions (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, kind TEXT NOT NULL, word TEXT NOT NULL,
        origin TEXT NOT NULL, gender TEXT NOT NULL DEFAULT 'u', era TEXT NOT NULL DEFAULT 'any',
        status TEXT NOT NULL DEFAULT 'new', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS nick_favorites (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, nick TEXT NOT NULL, skin TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, nick)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS nick_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, nick TEXT NOT NULL, style TEXT DEFAULT '', action TEXT NOT NULL,
        user_id INTEGER, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS nick_user_stats (
        user_id INTEGER PRIMARY KEY, generated INTEGER DEFAULT 0, copied INTEGER DEFAULT 0,
        cards INTEGER DEFAULT 0, shares INTEGER DEFAULT 0, customs INTEGER DEFAULT 0
    )");
    $cnt = (int)$db->query('SELECT COUNT(*) FROM nick_words')->fetchColumn();
    if ($cnt === 0) nickSeed($db);
    $ready = true;
    return $db;
}

function nickSeed($db) {
    $file = __DIR__ . '/nick_seed.json';
    if (!is_file($file)) return;
    $seed = json_decode(file_get_contents($file), true);
    if (!is_array($seed)) return;
    $rows = [];
    foreach ((array)($seed['first'] ?? []) as $key => $str) {
        $p = explode('|', $key);
        if (count($p) !== 3) continue;
        foreach (preg_split('/\s+/', trim($str)) as $w) {
            if (preg_match('/^[A-Za-z]{2,16}$/', $w)) $rows[] = "('first','{$p[0]}','{$p[1]}','{$p[2]}','','{$w}','seed')";
        }
    }
    foreach ((array)($seed['last'] ?? []) as $key => $str) {
        $p = explode('|', $key);
        if (count($p) !== 2) continue;
        foreach (preg_split('/\s+/', trim($str)) as $w) {
            if (preg_match('/^[A-Za-z]{2,16}$/', $w)) $rows[] = "('last','{$p[0]}','u','any','{$p[1]}','{$w}','seed')";
        }
    }
    $db->exec('BEGIN');
    foreach (array_chunk($rows, 200) as $chunk) {
        $db->exec('INSERT OR IGNORE INTO nick_words (kind, origin, gender, era, tag, word, source) VALUES ' . implode(',', $chunk));
    }
    $db->exec('COMMIT');
}

function nickWordsGrouped() {
    static $cache = null;
    if ($cache !== null) return $cache;
    $rows = nickDb()->query('SELECT kind, origin, gender, tag, word FROM nick_words WHERE enabled = 1')->fetchAll();
    $first = []; $last = [];
    foreach ($rows as $r) {
        if ($r['kind'] === 'first') $first[$r['origin']][$r['gender']][] = $r['word'];
        else $last[$r['origin']][$r['tag'] === '' ? 'p' : $r['tag']][] = $r['word'];
    }
    $cache = ['first' => $first, 'last' => $last];
    return $cache;
}

// ---------- Генерация на сервере (для SEO-примеров и «Ника дня») ----------
function nickRng($seed) {
    $s = crc32((string)$seed) & 0x7fffffff;
    return function ($n) use (&$s) {
        $s = ($s * 1103515245 + 12345) & 0x7fffffff;
        return $n > 0 ? ($s >> 8) % $n : 0;
    };
}

function nickPickWeighted($weights, $rng) {
    $sum = array_sum($weights);
    $r = $rng($sum * 10) / 10;
    foreach ($weights as $k => $w) {
        if ($r < $w) return $k;
        $r -= $w;
    }
    $keys = array_keys($weights);
    return $keys[0];
}

function nickFemSurname($s) {
    if (preg_match('/(skiy|sky)$/', $s)) return preg_replace('/(skiy|sky)$/', 'skaya', $s);
    if (preg_match('/(ov|ev)$/', $s)) return $s . 'a';
    if (preg_match('/(in|yn)$/', $s)) return $s . 'a';
    if (preg_match('/(oy|iy)$/', $s)) return preg_replace('/(oy|iy)$/', 'aya', $s);
    return $s;
}

function nickSamples($group, $count, $seed) {
    $defs = nickGroupDefs();
    if (!isset($defs[$group])) $group = 'any';
    $def = $defs[$group];
    $W = nickWordsGrouped();
    $rng = nickRng($seed . '|' . $group);
    $out = []; $guard = 0;
    while (count($out) < $count && $guard++ < $count * 30) {
        $o = nickPickWeighted($def['o'], $rng);
        $g = $rng(100) < 58 ? 'm' : 'f';
        if (empty($W['first'][$o][$g]) || empty($W['last'][$o])) continue;
        $fp = $W['first'][$o][$g];
        $first = $fp[$rng(count($fp))];
        $L = $W['last'][$o];
        $pool = null;
        foreach ($def['tag'] as $t => $p) {
            if (!empty($L[$t]) && $rng(100) < $p * 100) { $pool = $L[$t]; break; }
        }
        if ($pool === null) $pool = !empty($L['p']) ? $L['p'] : null;
        if ($pool === null) continue;
        $last = $pool[$rng(count($pool))];
        if ($g === 'f' && $o === 'ru') $last = nickFemSurname($last);
        $nick = $first . '_' . $last;
        if (strlen($nick) > NICK_MAX_LEN) continue;
        $out[$nick] = ['nick' => $nick, 'first' => $first, 'last' => $last, 'origin' => $o, 'gender' => $g];
    }
    return array_values($out);
}

// ---------- Скины ----------
function nickSkins() {
    static $s = null;
    if ($s !== null) return $s;
    $f = __DIR__ . '/../assets/img/nick/skins.json';
    $s = is_file($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
    return $s;
}

function nickPickSkin($group, $gender, $rng) {
    $map = ['gangs' => ['a', 's'], 'mafia' => ['m'], 'gov' => ['g'], 'elite' => ['m'], 'street' => ['s', 'a', 'c']];
    $all = nickSkins();
    $pool = [];
    foreach ($all as $r) {
        if ($r[1] !== $gender) continue;
        if (!isset($map[$group]) || in_array($r[2], $map[$group], true)) $pool[] = $r[0];
    }
    if (!$pool) { foreach ($all as $r) { if ($r[1] === $gender) $pool[] = $r[0]; } }
    return $pool ? (string)$pool[$rng(count($pool))] : '';
}

// ---------- Ник дня ----------
function nickOfDay() {
    $today = date('Y-m-d');
    $cached = setting('nick_day');
    if ($cached) {
        $d = json_decode($cached, true);
        if (is_array($d) && ($d['date'] ?? '') === $today && nickValidNick($d['nick'] ?? '')) return $d;
    }
    $rng = nickRng('nickday-' . $today);
    $groups = ['gangs', 'mafia', 'gov', 'elite', 'street'];
    $group = $groups[$rng(count($groups))];
    $s = nickSamples($group, 1, 'day-' . $today);
    if (!$s) return null;
    $d = ['date' => $today, 'nick' => $s[0]['nick'], 'group' => $group, 'gender' => $s[0]['gender'],
          'skin' => nickPickSkin($group, $s[0]['gender'], $rng)];
    setSetting('nick_day', json_encode($d));
    return $d;
}

// ---------- События, статистика, избранное ----------
function nickUid() { return !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; }

function nickRateLimit($bucket, $max, $window) {
    $now = time();
    $a = isset($_SESSION['nick_rl'][$bucket]) ? $_SESSION['nick_rl'][$bucket] : [];
    $a = array_values(array_filter($a, function ($t) use ($now, $window) { return $t > $now - $window; }));
    if (count($a) >= $max) { $_SESSION['nick_rl'][$bucket] = $a; return false; }
    $a[] = $now;
    $_SESSION['nick_rl'][$bucket] = $a;
    return true;
}

function nickUserStats($uid) {
    $db = nickDb();
    $z = ['generated' => 0, 'copied' => 0, 'cards' => 0, 'shares' => 0, 'customs' => 0, 'favorites' => 0];
    if (!$uid) return $z;
    $st = $db->prepare('SELECT * FROM nick_user_stats WHERE user_id = ?');
    $st->execute([$uid]);
    $r = $st->fetch();
    if ($r) foreach (['generated', 'copied', 'cards', 'shares', 'customs'] as $k) $z[$k] = (int)$r[$k];
    $f = $db->prepare('SELECT COUNT(*) FROM nick_favorites WHERE user_id = ?');
    $f->execute([$uid]);
    $z['favorites'] = (int)$f->fetchColumn();
    return $z;
}

function nickBumpStat($uid, $col, $n) {
    $allowed = ['generated', 'copied', 'cards', 'shares', 'customs'];
    if (!$uid || !in_array($col, $allowed, true) || $n < 1) return;
    nickDb()->prepare("INSERT INTO nick_user_stats (user_id, $col) VALUES (?, ?) ON CONFLICT(user_id) DO UPDATE SET $col = $col + excluded.$col")
        ->execute([$uid, (int)$n]);
}

function nickTopWeek($limit) {
    $st = nickDb()->prepare("SELECT nick, COUNT(*) AS c FROM nick_events
        WHERE created_at >= datetime('now', '-7 days') AND action IN ('copy','fav','card','share')
        GROUP BY nick ORDER BY c DESC, MAX(id) DESC LIMIT ?");
    $st->execute([(int)$limit]);
    return $st->fetchAll();
}

function nickFavorites($uid) {
    if (!$uid) return [];
    $st = nickDb()->prepare('SELECT nick, skin FROM nick_favorites WHERE user_id = ? ORDER BY id DESC LIMIT 200');
    $st->execute([$uid]);
    return $st->fetchAll();
}

// ---------- API ----------
function nickJson($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function nickCsrfOk() {
    $t = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : ($_POST['csrf'] ?? '');
    return verifyCsrfToken($t);
}

function nickHandleApi($uri) {
    $db = nickDb();
    $uid = nickUid();

    if ($uri === 'api/nick-words') {
        $meta = $db->query('SELECT COUNT(*) AS c, MAX(id) AS m, SUM(enabled) AS e FROM nick_words')->fetch();
        $etag = '"nw' . md5(($meta['c'] ?? 0) . '-' . ($meta['m'] ?? 0) . '-' . ($meta['e'] ?? 0)) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=300');
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) { http_response_code(304); exit; }
        $f = []; $l = [];
        foreach ($db->query('SELECT kind, origin, gender, era, tag, word FROM nick_words WHERE enabled = 1')->fetchAll() as $r) {
            if ($r['kind'] === 'first') $f[] = [$r['word'], $r['origin'], $r['gender'], $r['era']];
            else $l[] = [$r['word'], $r['origin'], $r['tag']];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['v' => $etag, 'f' => $f, 'l' => $l], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($uri === 'api/nick-state') {
        nickJson([
            'ok' => true, 'logged' => $uid > 0, 'favorites' => nickFavorites($uid),
            'stats' => nickUserStats($uid), 'top' => nickTopWeek(10), 'day' => nickOfDay(),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') nickJson(['ok' => false, 'error' => 'method'], 405);
    if (!nickCsrfOk()) nickJson(['ok' => false, 'error' => 'csrf'], 403);

    if ($uri === 'api/nick-event') {
        if (!nickRateLimit('ev', 120, 3600)) nickJson(['ok' => false, 'error' => 'rate'], 429);
        $type = (string)($_POST['type'] ?? '');
        $nick = (string)($_POST['nick'] ?? '');
        $style = substr(preg_replace('/[^a-z]/', '', (string)($_POST['style'] ?? '')), 0, 12);
        $cols = ['gen' => 'generated', 'copy' => 'copied', 'card' => 'cards', 'share' => 'shares', 'custom' => 'customs'];
        if ($type === 'gen') {
            nickBumpStat($uid, 'generated', max(1, min(50, (int)($_POST['n'] ?? 1))));
        } elseif (isset($cols[$type])) {
            if ($type !== 'custom' && !nickValidNick($nick)) nickJson(['ok' => false, 'error' => 'nick'], 400);
            if ($type !== 'custom') $db->prepare('INSERT INTO nick_events (nick, style, action, user_id) VALUES (?, ?, ?, ?)')->execute([$nick, $style, $type, $uid ?: null]);
            nickBumpStat($uid, $cols[$type], 1);
        } else {
            nickJson(['ok' => false, 'error' => 'type'], 400);
        }
        nickJson(['ok' => true]);
    }

    if ($uri === 'api/nick-fav') {
        if (!$uid) nickJson(['ok' => false, 'error' => 'login'], 401);
        $nick = (string)($_POST['nick'] ?? '');
        if (!nickValidNick($nick)) nickJson(['ok' => false, 'error' => 'nick'], 400);
        $action = (string)($_POST['action'] ?? 'add');
        if ($action === 'remove') {
            $db->prepare('DELETE FROM nick_favorites WHERE user_id = ? AND nick = ?')->execute([$uid, $nick]);
        } else {
            if (!nickRateLimit('fav', 60, 3600)) nickJson(['ok' => false, 'error' => 'rate'], 429);
            $c = $db->prepare('SELECT COUNT(*) FROM nick_favorites WHERE user_id = ?'); $c->execute([$uid]);
            if ((int)$c->fetchColumn() >= 200) nickJson(['ok' => false, 'error' => 'limit'], 400);
            $skin = preg_match('/^[0-9]{1,5}(-[0-9])?$/', (string)($_POST['skin'] ?? '')) ? (string)$_POST['skin'] : '';
            $db->prepare('INSERT OR IGNORE INTO nick_favorites (user_id, nick, skin) VALUES (?, ?, ?)')->execute([$uid, $nick, $skin]);
            $db->prepare('INSERT INTO nick_events (nick, style, action, user_id) VALUES (?, ?, ?, ?)')->execute([$nick, '', 'fav', $uid]);
        }
        nickJson(['ok' => true, 'stats' => nickUserStats($uid)]);
    }

    if ($uri === 'api/nick-suggest') {
        if (!$uid) nickJson(['ok' => false, 'error' => 'login'], 401);
        if (!nickRateLimit('sg', 10, 86400)) nickJson(['ok' => false, 'error' => 'rate'], 429);
        $kind = ($_POST['kind'] ?? '') === 'last' ? 'last' : 'first';
        $word = nickCleanWord($_POST['word'] ?? '');
        $origin = (string)($_POST['origin'] ?? '');
        $gender = (string)($_POST['gender'] ?? 'u');
        $era = (string)($_POST['era'] ?? 'any');
        if ($word === '') nickJson(['ok' => false, 'error' => 'word'], 400);
        if (!isset(nickOriginLabels()[$origin])) nickJson(['ok' => false, 'error' => 'origin'], 400);
        if ($kind === 'last') { $gender = 'u'; $era = 'any'; }
        else { if (!in_array($gender, ['m', 'f'], true)) $gender = 'm'; if (!in_array($era, ['modern', 'retro', 'any'], true)) $era = 'any'; }
        $ex = $db->prepare('SELECT 1 FROM nick_words WHERE kind = ? AND origin = ? AND word = ?'); $ex->execute([$kind, $origin, $word]);
        if ($ex->fetch()) nickJson(['ok' => false, 'error' => 'exists'], 409);
        $db->prepare('INSERT INTO nick_suggestions (user_id, kind, word, origin, gender, era) VALUES (?, ?, ?, ?, ?, ?)')->execute([$uid, $kind, $word, $origin, $gender, $era]);
        nickJson(['ok' => true, 'word' => $word]);
    }

    nickJson(['ok' => false, 'error' => 'unknown'], 404);
}

// ---------- SEO: проекты, тексты, разметка ----------
function nickProjects() {
    return [
        '' => [
            'name' => 'SAMP', 'path' => '/generator-nikov',
            'title' => 'Генератор ников SAMP RP — Имя_Фамилия онлайн',
            'h1' => 'Генератор ников для SAMP RP',
            'desc' => 'Бесплатный генератор ников для SAMP RP: ролевые ники в формате Имя_Фамилия для банд, мафий и госструктур. Свой вариант имени или фамилии, карточка со скином.',
            'lead' => 'Подбери ролевой ник для SA-MP за пару секунд: Имя_Фамилия, до 24 символов, стили для банд, мафий и госструктур. Введи своё имя или фамилию — остальное подберём в тон.',
        ],
        'arizona' => [
            'name' => 'Arizona RP', 'path' => '/generator-nikov/arizona',
            'title' => 'Генератор ников Arizona RP — Имя_Фамилия онлайн',
            'h1' => 'Генератор ников для Arizona RP',
            'desc' => 'Генератор ников Arizona RP: ролевые ники Имя_Фамилия под вашу роль — гангстер, мафиози, сотрудник госструктуры. Бесплатно, со своим именем или фамилией.',
            'lead' => 'Ролевые ники в формате Имя_Фамилия для Arizona RP. Выбери стиль персонажа, при желании введи своё имя или фамилию — мы подберём подходящую пару.',
        ],
        'amazing' => [
            'name' => 'Amazing RP', 'path' => '/generator-nikov/amazing',
            'title' => 'Генератор ников Amazing RP — Имя_Фамилия онлайн',
            'h1' => 'Генератор ников для Amazing RP',
            'desc' => 'Генератор ников Amazing RP (Amazing Online): ролевые ники Имя_Фамилия и красивая карточка персонажа со скином. Бесплатно, без регистрации.',
            'lead' => 'Придумай ролевой ник для Amazing RP и получи готовую карточку персонажа со скином. Стили: банды, мафия, госс, элита и улица.',
        ],
        'radmir' => [
            'name' => 'Radmir RP', 'path' => '/generator-nikov/radmir',
            'title' => 'Генератор ников Radmir RP — Имя_Фамилия онлайн',
            'h1' => 'Генератор ников для Radmir RP',
            'desc' => 'Генератор ников Radmir RP: подберите ролевой ник Имя_Фамилия за секунды. Современные и раритетные имена, короткие и длинные варианты.',
            'lead' => 'Подбери ролевой ник в формате Имя_Фамилия для Radmir RP: современные и раритетные имена, короткие и длинные варианты, свой вариант имени или фамилии.',
        ],
        'black-russia' => [
            'name' => 'Black Russia', 'path' => '/generator-nikov/black-russia',
            'title' => 'Генератор ников Black Russia — Имя_Фамилия онлайн',
            'h1' => 'Генератор ников для Black Russia',
            'desc' => 'Генератор ников Black Russia (CRMP): ролевые ники Имя_Фамилия под нужный стиль. Бесплатно, быстро, с возможностью подобрать пару к своему имени.',
            'lead' => 'Ролевые ники Имя_Фамилия для Black Russia. Выбери стиль, длину и происхождение — или введи своё имя, а фамилию мы подберём сами.',
        ],
        'advance' => [
            'name' => 'Advance RP', 'path' => '/generator-nikov/advance',
            'title' => 'Генератор ников Advance RP — Имя_Фамилия онлайн',
            'h1' => 'Генератор ников для Advance RP',
            'desc' => 'Генератор ников Advance RP: красивые ролевые ники Имя_Фамилия для банд, мафий и госструктур. Бесплатно, с рулеткой и карточкой персонажа.',
            'lead' => 'Крути рулетку и находи ролевой ник для Advance RP: Имя_Фамилия, любой стиль, фиксация понравившейся части и готовая карточка.',
        ],
        'evolve' => [
            'name' => 'Evolve RP', 'path' => '/generator-nikov/evolve',
            'title' => 'Генератор ников Evolve RP — Имя_Фамилия онлайн',
            'h1' => 'Генератор ников для Evolve RP',
            'desc' => 'Генератор ников Evolve RP: подберите ролевой ник Имя_Фамилия. Тысячи имён и фамилий, стили по организациям, свой вариант имени.',
            'lead' => 'Генератор ролевых ников для Evolve RP: тысячи имён и фамилий, стили по организациям и возможность подобрать пару к своему варианту.',
        ],
    ];
}

function nickFaq($project) {
    $name = $project['name'];
    return [
        ['Как придумать ник для SAMP RP?', 'Нажмите «Крутить»: генератор соберёт ролевой ник в формате Имя_Фамилия. Выберите стиль (банды, мафия, госструктуры, элита, улица), происхождение и длину. Понравившуюся часть можно закрепить замком и перекрутить остальное.'],
        ['Какой формат ника принят на RP-серверах?', 'На ролевых проектах SA-MP используется формат Имя_Фамилия латиницей, каждая часть с заглавной буквы. Общий лимит SA-MP — 24 символа; генератор никогда его не превышает. На отдельных серверах правила могут быть строже, поэтому проверяйте их перед регистрацией.'],
        ['Можно ли подставить своё имя или фамилию?', 'Да. Введите своё имя или фамилию (можно кириллицей — переведём в латиницу), и генератор подберёт вторую часть того же происхождения и подходящего рода. Например, к русской фамилии — русское имя, к итальянскому имени — итальянская фамилия.'],
        ['Подойдут ли ники для ' . $name . '?', 'Формат Имя_Фамилия принят на большинстве RP-проектов SA-MP и CRMP, включая Arizona RP, Amazing RP, Radmir RP, Black Russia, Advance RP и Evolve RP. Конкретные правила именования у каждого проекта свои — сверяйтесь с ними.'],
        ['Генератор проверяет, свободен ли ник?', 'Нет: у игровых проектов нет открытого способа проверить занятость ника. Свободен ли вариант, станет понятно при регистрации персонажа в игре. Поэтому мы даём много вариантов сразу и сохраняем избранное.'],
        ['Что такое «красивое оформление» ника?', 'Это Unicode-варианты (готика, прописные, рамки и символы) для соцсетей — ВК, Telegram, Discord. В игру такой ник установить не получится: там разрешена только латиница.'],
        ['Можно ли скачать картинку с ником?', 'Да. После генерации автоматически создаётся карточка со скином персонажа, названием и логотипом Galbraith. Её можно скачать в PNG и отправить друзьям.'],
    ];
}

function nickJsonLd($data) {
    return '<script type="application/ld+json">' . str_replace('</', '<\/', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</script>';
}

function nickSeoHead($slug, $project) {
    $canonical = SITE_URL . $project['path'];
    $img = SITE_URL . '/assets/img/nick/logo.png';
    $h = '<link rel="canonical" href="' . e($canonical) . '">' . "\n";
    $h .= '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
    $h .= '<meta property="og:type" content="website"><meta property="og:site_name" content="Galbraith">' . "\n";
    $h .= '<meta property="og:title" content="' . e($project['title']) . '"><meta property="og:description" content="' . e($project['desc']) . '">' . "\n";
    $h .= '<meta property="og:url" content="' . e($canonical) . '"><meta property="og:image" content="' . e($img) . '"><meta property="og:locale" content="ru_RU">' . "\n";
    $h .= '<meta name="twitter:card" content="summary"><meta name="twitter:title" content="' . e($project['title']) . '">' . "\n";
    $crumbs = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Генератор ников', 'item' => SITE_URL . '/generator-nikov'],
    ];
    if ($slug !== '') $crumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $project['name'], 'item' => $canonical];
    $h .= nickJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs]) . "\n";
    $h .= nickJsonLd([
        '@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => $project['h1'], 'url' => $canonical,
        'description' => $project['desc'], 'applicationCategory' => 'GameApplication', 'operatingSystem' => 'Web',
        'inLanguage' => 'ru', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'RUB'],
        'publisher' => ['@type' => 'Organization', 'name' => 'Galbraith', 'url' => SITE_URL . '/', 'logo' => $img],
    ]) . "\n";
    $qa = [];
    foreach (nickFaq($project) as $f) $qa[] = ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
    $h .= nickJsonLd(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $qa]) . "\n";
    return $h;
}

// ---------- robots.txt и sitemap.xml ----------
function galbraithRobotsTxt() {
    return "User-agent: *\nDisallow: /admin\nDisallow: /moderator\nDisallow: /support\nDisallow: /seller\nDisallow: /api/\nDisallow: /login\nDisallow: /register\nAllow: /\n\nSitemap: " . SITE_URL . "/sitemap.xml\n";
}

function galbraithSitemapXml() {
    $urls = [['/', '1.0', 'daily'], ['/catalog', '0.9', 'daily'], ['/marketplace', '0.7', 'daily']];
    foreach (nickProjects() as $slug => $p) $urls[] = [$p['path'], $slug === '' ? '0.9' : '0.8', 'weekly'];
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $out .= '<url><loc>' . e(SITE_URL . $u[0]) . '</loc><changefreq>' . $u[2] . '</changefreq><priority>' . $u[1] . '</priority></url>' . "\n";
    }
    foreach (getMaterials() as $m) {
        if (!empty($m['is_premiere'])) continue;
        $lm = !empty($m['updated_at']) ? date('Y-m-d', strtotime($m['updated_at'])) : date('Y-m-d', strtotime($m['created_at']));
        $out .= '<url><loc>' . e(SITE_URL . '/mod/' . $m['slug']) . '</loc><lastmod>' . $lm . '</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>' . "\n";
    }
    return $out . '</urlset>' . "\n";
}
