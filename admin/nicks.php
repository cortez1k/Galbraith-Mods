<?php
// Админка генератора ников: словарь, предложения пользователей, статистика.
$db = nickDb();
$origins = nickOriginLabels();
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['words', 'sug', 'stats'], true) ? $_GET['tab'] : 'words';
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Сессия формы истекла, повторите действие.';
        redirect('/admin/nicks?tab=' . $tab);
    }
    $act = (string)($_POST['action'] ?? '');
    $back = (string)($_POST['back'] ?? '/admin/nicks');
    if (strpos($back, '/admin/nicks') !== 0) $back = '/admin/nicks';
    $msg = '';

    if ($act === 'add_word' || $act === 'bulk_add') {
        $kind = ($_POST['kind'] ?? '') === 'last' ? 'last' : 'first';
        $origin = (string)($_POST['origin'] ?? '');
        $gender = in_array($_POST['gender'] ?? '', ['m', 'f'], true) ? $_POST['gender'] : 'm';
        $era = in_array($_POST['era'] ?? '', ['modern', 'retro', 'any'], true) ? $_POST['era'] : 'any';
        $tag = in_array($_POST['tag'] ?? '', ['s', 'e'], true) ? $_POST['tag'] : '';
        if (!isset($origins[$origin])) {
            $msg = 'Выберите происхождение.';
        } else {
            if ($kind === 'last') { $gender = 'u'; $era = 'any'; } else { $tag = ''; }
            $raw = $act === 'add_word' ? (string)($_POST['word'] ?? '') : (string)($_POST['words'] ?? '');
            $words = preg_split('/[\s,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
            $added = 0; $skipped = 0;
            $db->exec('BEGIN');
            foreach (array_slice($words, 0, 3000) as $w) {
                $c = nickCleanWord($w);
                if ($c === '') { $skipped++; continue; }
                $ex = $db->prepare('SELECT 1 FROM nick_words WHERE kind = ? AND origin = ? AND gender = ? AND word = ?');
                $ex->execute([$kind, $origin, $gender, $c]);
                if ($ex->fetch()) { $skipped++; continue; }
                $db->prepare('INSERT INTO nick_words (kind, origin, gender, era, tag, word, source) VALUES (?, ?, ?, ?, ?, ?, ?)')
                   ->execute([$kind, $origin, $gender, $era, $tag, $c, 'admin']);
                $added++;
            }
            $db->exec('COMMIT');
            $msg = "Добавлено: $added, пропущено (дубликаты/некорректные): $skipped.";
        }
    } elseif ($act === 'toggle') {
        $db->prepare('UPDATE nick_words SET enabled = 1 - enabled WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
    } elseif ($act === 'delete') {
        $db->prepare('DELETE FROM nick_words WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        $msg = 'Слово удалено.';
    } elseif ($act === 'approve_sug' || $act === 'reject_sug') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $db->prepare("SELECT * FROM nick_suggestions WHERE id = ? AND status = 'new'");
        $st->execute([$id]);
        $sug = $st->fetch();
        if ($sug) {
            if ($act === 'approve_sug') {
                $db->prepare('INSERT OR IGNORE INTO nick_words (kind, origin, gender, era, tag, word, source) VALUES (?, ?, ?, ?, ?, ?, ?)')
                   ->execute([$sug['kind'], $sug['origin'], $sug['gender'], $sug['era'], '', $sug['word'], 'user']);
                $db->prepare("UPDATE nick_suggestions SET status = 'approved' WHERE id = ?")->execute([$id]);
                $msg = 'Слово «' . $sug['word'] . '» добавлено в словарь.';
            } else {
                $db->prepare("UPDATE nick_suggestions SET status = 'rejected' WHERE id = ?")->execute([$id]);
                $msg = 'Предложение отклонено.';
            }
        }
    }
    if ($msg !== '') $_SESSION['flash'] = $msg;
    redirect($back);
}

$pageTitle = 'Генератор ников';
include __DIR__ . '/../templates/admin_header.php';

$pendingSug = (int)$db->query("SELECT COUNT(*) FROM nick_suggestions WHERE status = 'new'")->fetchColumn();
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
?>
<h1 style="font-size:22px;font-weight:600;margin-bottom:6px;">🎭 Генератор ников</h1>
<p style="color:var(--g-text-secondary);font-size:13.5px;margin-bottom:16px;">Публичная страница: <a href="/generator-nikov" target="_blank" style="color:var(--g-accent-light);">/generator-nikov</a>. Изменения словаря подхватываются генератором в течение ~5 минут (кэш браузера).</p>
<?php if ($flash): ?><div class="alert alert-success" style="margin-bottom:14px;"><?= e($flash) ?></div><?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
    <a href="/admin/nicks?tab=words" class="btn <?= $tab === 'words' ? 'btn-primary' : 'btn-secondary' ?>">Словарь</a>
    <a href="/admin/nicks?tab=sug" class="btn <?= $tab === 'sug' ? 'btn-primary' : 'btn-secondary' ?>">Предложения<?= $pendingSug ? ' (' . $pendingSug . ')' : '' ?></a>
    <a href="/admin/nicks?tab=stats" class="btn <?= $tab === 'stats' ? 'btn-primary' : 'btn-secondary' ?>">Статистика</a>
</div>

<?php if ($tab === 'words'):
    $fKind = isset($_GET['kind']) && in_array($_GET['kind'], ['first', 'last'], true) ? $_GET['kind'] : '';
    $fOrigin = isset($_GET['origin']) && isset($origins[$_GET['origin']]) ? $_GET['origin'] : '';
    $fQ = trim((string)($_GET['q'] ?? ''));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 100;
    $where = '1=1'; $params = [];
    if ($fKind) { $where .= ' AND kind = ?'; $params[] = $fKind; }
    if ($fOrigin) { $where .= ' AND origin = ?'; $params[] = $fOrigin; }
    if ($fQ !== '') { $where .= ' AND word LIKE ?'; $params[] = '%' . preg_replace('/[^A-Za-z]/', '', $fQ) . '%'; }
    $cs = $db->prepare("SELECT COUNT(*) FROM nick_words WHERE $where"); $cs->execute($params);
    $total = (int)$cs->fetchColumn();
    $pages = max(1, (int)ceil($total / $per)); if ($page > $pages) $page = $pages;
    $ls = $db->prepare("SELECT * FROM nick_words WHERE $where ORDER BY kind, origin, word LIMIT $per OFFSET " . (($page - 1) * $per));
    $ls->execute($params);
    $rows = $ls->fetchAll();
    $sum = $db->query("SELECT kind, origin, COUNT(*) AS c, SUM(enabled) AS e FROM nick_words GROUP BY kind, origin ORDER BY kind, origin")->fetchAll();
    $totalAll = (int)$db->query('SELECT COUNT(*) FROM nick_words')->fetchColumn();
    $qs = http_build_query(['tab' => 'words', 'kind' => $fKind, 'origin' => $fOrigin, 'q' => $fQ]);
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:14px;margin-bottom:20px;">
    <form method="POST" class="card" style="padding:16px;gap:10px;">
        <?= csrfField() ?><input type="hidden" name="action" value="bulk_add"><input type="hidden" name="back" value="/admin/nicks?tab=words">
        <h3 style="font-size:16px;">Добавить слова (списком)</h3>
        <textarea name="words" class="input" rows="4" placeholder="Слова через пробел, запятую или с новой строки. Кириллица переводится в латиницу." required></textarea>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <select name="kind" class="input"><option value="first">Имя</option><option value="last">Фамилия</option></select>
            <select name="origin" class="input"><?php foreach ($origins as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
            <select name="gender" class="input"><option value="m">Мужское (для имён)</option><option value="f">Женское (для имён)</option></select>
            <select name="era" class="input"><option value="any">Любая эпоха (для имён)</option><option value="modern">Современное</option><option value="retro">Раритетное</option></select>
            <select name="tag" class="input" style="grid-column:1/-1;"><option value="">Обычная фамилия</option><option value="s">Уличная фамилия (стиль «Улица/Банды»)</option><option value="e">Элитная фамилия (стиль «Элита»)</option></select>
        </div>
        <button type="submit" class="btn btn-primary">Добавить</button>
    </form>
    <div class="card" style="padding:16px;gap:8px;">
        <h3 style="font-size:16px;">В словаре: <?= number_format($totalAll, 0, ',', ' ') ?> слов</h3>
        <table class="admin-table" style="font-size:12.5px;"><thead><tr><th>Тип</th><th>Происхождение</th><th>Всего</th><th>Активных</th></tr></thead><tbody>
        <?php foreach ($sum as $r): ?><tr><td><?= $r['kind'] === 'first' ? 'Имена' : 'Фамилии' ?></td><td><?= e($origins[$r['origin']] ?? $r['origin']) ?></td><td><?= (int)$r['c'] ?></td><td><?= (int)$r['e'] ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
    <input type="hidden" name="tab" value="words">
    <select name="kind" class="input" style="max-width:160px;"><option value="">Все типы</option><option value="first"<?= $fKind === 'first' ? ' selected' : '' ?>>Имена</option><option value="last"<?= $fKind === 'last' ? ' selected' : '' ?>>Фамилии</option></select>
    <select name="origin" class="input" style="max-width:220px;"><option value="">Любое происхождение</option><?php foreach ($origins as $k => $l): ?><option value="<?= e($k) ?>"<?= $fOrigin === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <input type="text" name="q" class="input" style="max-width:220px;" placeholder="Поиск по слову" value="<?= e($fQ) ?>">
    <button class="btn btn-secondary" type="submit">Найти</button>
    <span style="align-self:center;color:var(--g-text-secondary);font-size:13px;">Найдено: <?= number_format($total, 0, ',', ' ') ?></span>
</form>

<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Слово</th><th>Тип</th><th>Происхождение</th><th>Пол</th><th>Эпоха</th><th>Тег</th><th>Источник</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $w): ?>
        <tr<?= $w['enabled'] ? '' : ' style="opacity:.5;"' ?>>
            <td><strong><?= e($w['word']) ?></strong></td>
            <td><?= $w['kind'] === 'first' ? 'Имя' : 'Фамилия' ?></td>
            <td><?= e($origins[$w['origin']] ?? $w['origin']) ?></td>
            <td><?= $w['gender'] === 'm' ? 'М' : ($w['gender'] === 'f' ? 'Ж' : '—') ?></td>
            <td><?= $w['era'] === 'modern' ? 'совр.' : ($w['era'] === 'retro' ? 'раритет.' : '—') ?></td>
            <td><?= $w['tag'] === 's' ? 'улица' : ($w['tag'] === 'e' ? 'элита' : '—') ?></td>
            <td><?= e($w['source']) ?></td>
            <td><?= $w['enabled'] ? '<span style="color:var(--g-success);">вкл</span>' : 'выкл' ?></td>
            <td style="white-space:nowrap;">
                <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input type="hidden" name="back" value="/admin/nicks?<?= e($qs . '&page=' . $page) ?>"><button class="btn btn-secondary btn-sm" type="submit"><?= $w['enabled'] ? 'Выкл' : 'Вкл' ?></button></form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить слово?');"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input type="hidden" name="back" value="/admin/nicks?<?= e($qs . '&page=' . $page) ?>"><button class="btn btn-secondary btn-sm" type="submit">✕</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" style="text-align:center;color:var(--g-text-secondary);padding:24px;">Ничего не найдено</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:14px;align-items:center;">
    <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="/admin/nicks?<?= e($qs . '&page=' . ($page - 1)) ?>">←</a><?php endif; ?>
    <span style="font-size:13px;color:var(--g-text-secondary);">Страница <?= $page ?> из <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="/admin/nicks?<?= e($qs . '&page=' . ($page + 1)) ?>">→</a><?php endif; ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'sug'):
    $sugs = $db->query("SELECT s.*, u.name AS user_name FROM nick_suggestions s LEFT JOIN users u ON u.id = s.user_id ORDER BY (s.status = 'new') DESC, s.id DESC LIMIT 200")->fetchAll();
?>
<div style="overflow-x:auto;">
<table class="admin-table">
    <thead><tr><th>Дата</th><th>Пользователь</th><th>Слово</th><th>Тип</th><th>Происхождение</th><th>Пол</th><th>Эпоха</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sugs as $s): ?>
        <tr>
            <td><?= date('d.m.Y H:i', strtotime($s['created_at'])) ?></td>
            <td><?= e($s['user_name'] ?? '—') ?></td>
            <td><strong><?= e($s['word']) ?></strong></td>
            <td><?= $s['kind'] === 'first' ? 'Имя' : 'Фамилия' ?></td>
            <td><?= e($origins[$s['origin']] ?? $s['origin']) ?></td>
            <td><?= $s['gender'] === 'm' ? 'М' : ($s['gender'] === 'f' ? 'Ж' : '—') ?></td>
            <td><?= $s['era'] === 'modern' ? 'совр.' : ($s['era'] === 'retro' ? 'раритет.' : '—') ?></td>
            <td><?= $s['status'] === 'new' ? '<span style="color:var(--g-warning);">на проверке</span>' : ($s['status'] === 'approved' ? '<span style="color:var(--g-success);">принято</span>' : 'отклонено') ?></td>
            <td style="white-space:nowrap;">
                <?php if ($s['status'] === 'new'): ?>
                <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="approve_sug"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="back" value="/admin/nicks?tab=sug"><button class="btn btn-primary btn-sm" type="submit">Принять</button></form>
                <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="reject_sug"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="back" value="/admin/nicks?tab=sug"><button class="btn btn-secondary btn-sm" type="submit">Отклонить</button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$sugs): ?><tr><td colspan="9" style="text-align:center;color:var(--g-text-secondary);padding:24px;">Предложений пока нет</td></tr><?php endif; ?>
    </tbody>
</table>
</div>

<?php else:
    $ev = $db->query("SELECT action, COUNT(*) AS c FROM nick_events WHERE created_at >= datetime('now', '-30 days') GROUP BY action")->fetchAll();
    $evMap = []; foreach ($ev as $r) $evMap[$r['action']] = (int)$r['c'];
    $topNicks = $db->query("SELECT nick, COUNT(*) AS c FROM nick_events WHERE created_at >= datetime('now', '-30 days') GROUP BY nick ORDER BY c DESC, MAX(id) DESC LIMIT 20")->fetchAll();
    $topStyles = $db->query("SELECT style, COUNT(*) AS c FROM nick_events WHERE created_at >= datetime('now', '-30 days') AND style != '' GROUP BY style ORDER BY c DESC")->fetchAll();
    $gens = (int)$db->query('SELECT COALESCE(SUM(generated), 0) FROM nick_user_stats')->fetchColumn();
    $favs = (int)$db->query('SELECT COUNT(*) FROM nick_favorites')->fetchColumn();
    $users = (int)$db->query('SELECT COUNT(*) FROM nick_user_stats')->fetchColumn();
    $gl = nickGroupDefs();
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:22px;">
    <?php
    $cards = [['Копирований (30 дн.)', $evMap['copy'] ?? 0], ['Карточек скачано (30 дн.)', $evMap['card'] ?? 0], ['Поделились (30 дн.)', $evMap['share'] ?? 0], ['В избранное (30 дн.)', $evMap['fav'] ?? 0], ['Генераций (зарег. польз.)', $gens], ['Ников в избранном', $favs], ['Пользователей с активностью', $users], ['Предложений на проверке', $pendingSug]];
    foreach ($cards as $c): ?>
    <div class="card" style="padding:14px;"><div style="font-size:12px;color:var(--g-text-secondary);"><?= e($c[0]) ?></div><div style="font-family:var(--g-font-display);font-size:30px;font-weight:700;"><?= number_format($c[1], 0, ',', ' ') ?></div></div>
    <?php endforeach; ?>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;">
    <div><h3 style="font-size:16px;margin-bottom:8px;">Топ ников за 30 дней</h3>
        <table class="admin-table"><thead><tr><th>Ник</th><th>Действий</th></tr></thead><tbody>
        <?php foreach ($topNicks as $t): ?><tr><td><?= e($t['nick']) ?></td><td><?= (int)$t['c'] ?></td></tr><?php endforeach; ?>
        <?php if (!$topNicks): ?><tr><td colspan="2" style="color:var(--g-text-secondary);">Данных пока нет</td></tr><?php endif; ?>
        </tbody></table></div>
    <div><h3 style="font-size:16px;margin-bottom:8px;">Популярность стилей (30 дней)</h3>
        <table class="admin-table"><thead><tr><th>Стиль</th><th>Действий</th></tr></thead><tbody>
        <?php foreach ($topStyles as $t): ?><tr><td><?= e($gl[$t['style']]['label'] ?? $t['style']) ?></td><td><?= (int)$t['c'] ?></td></tr><?php endforeach; ?>
        <?php if (!$topStyles): ?><tr><td colspan="2" style="color:var(--g-text-secondary);">Данных пока нет</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/admin_footer.php'; ?>
