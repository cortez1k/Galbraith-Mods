<?php
// ЗАГРУЗИТЕ этот файл в корень сайта (рядом с index.php) и откройте в браузере:
// https://ваш-домен/diagnose.php
// Он ничего не меняет на сайте, только показывает диагностику. После использования — удалите файл.

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

function row($label, $ok, $detail = '') {
    $color = $ok ? '#0a0' : '#c00';
    $icon = $ok ? '✅' : '❌';
    echo "<tr><td style='padding:6px 10px;'>$icon $label</td><td style='padding:6px 10px;color:$color;'>$detail</td></tr>";
}

echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Диагностика Galbraith</title>';
echo '<style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:0 20px;} table{border-collapse:collapse;width:100%;} tr{border-bottom:1px solid #eee;}</style></head><body>';
echo '<h1>Диагностика сервера</h1><table>';

row('PHP версия', version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION . (version_compare(PHP_VERSION, '7.4.0', '>=') ? ' (ок)' : ' — нужно 7.4 или новее'));

foreach (['sqlite3', 'mbstring', 'curl', 'openssl', 'json'] as $ext) {
    row("Расширение PHP: $ext", extension_loaded($ext), extension_loaded($ext) ? 'включено' : 'НЕ включено — включите в панели хостинга');
}

$dbFile = __DIR__ . '/database.sqlite';
row('Файл database.sqlite существует', file_exists($dbFile), file_exists($dbFile) ? 'найден' : 'не найден (будет создан автоматически при первом запуске)');
if (file_exists($dbFile)) {
    row('database.sqlite доступен для записи', is_writable($dbFile), is_writable($dbFile) ? 'да' : 'НЕТ — выставьте права 664/666');
    row('Размер database.sqlite', filesize($dbFile) > 0, filesize($dbFile) . ' байт');
}
row('Папка сайта доступна для записи', is_writable(__DIR__), is_writable(__DIR__) ? 'да' : 'нет (нужно, если базы ещё нет)');

if (extension_loaded('sqlite3')) {
    try {
        $test = new SQLite3($dbFile);
        $res = $test->query("SELECT name FROM sqlite_master WHERE type='table' LIMIT 1");
        row('Пробное подключение к SQLite и запрос', $res !== false, $res !== false ? 'успешно' : $test->lastErrorMsg());
        $test->close();
    } catch (Throwable $e) {
        row('Пробное подключение к SQLite', false, 'Исключение: ' . $e->getMessage());
    }
}

$configPath = __DIR__ . '/config.php';
row('config.php найден', file_exists($configPath));
if (file_exists($configPath)) {
    row('config.php читается', is_readable($configPath));
}

echo '</table>';
echo '<h2>Что делать дальше</h2><p>Если выше есть ❌ — это причина ошибки 500. Всё остальное (маршруты, бизнес-логика) не запустится, пока не решены эти базовые проблемы окружения.</p>';
echo '<p><strong>После диагностики удалите этот файл (diagnose.php) с сервера.</strong></p>';
echo '</body></html>';
