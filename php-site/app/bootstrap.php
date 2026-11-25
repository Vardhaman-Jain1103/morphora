<?php
/* Morphora Spaces — loads config, database and helpers. Included by index.php, admin/ and install/. */
declare(strict_types=1);

define('APP', __DIR__);
define('ROOT', dirname(__DIR__));

if (!is_file(APP . '/config.php')) {
    if (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) !== 'install') {
        http_response_code(503);
        exit('Site not configured yet. Copy app/config.sample.php to app/config.php, then open /install/.');
    }
}
$GLOBALS['CFG'] = is_file(APP . '/config.php') ? require APP . '/config.php' : require APP . '/config.sample.php';

date_default_timezone_set(cfg('timezone', 'Asia/Kolkata'));
if (cfg('debug')) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else { ini_set('display_errors', '0'); }

require APP . '/lib/helpers.php';
require APP . '/lib/seo.php';
require APP . '/lib/sections.php';
require APP . '/lib/mail.php';
require APP . '/lib/upload.php';
require APP . '/database/migrate.php';

function cfg(string $key, $default = null)
{
    $v = $GLOBALS['CFG'];
    foreach (explode('.', $key) as $k) { if (!is_array($v) || !array_key_exists($k, $v)) return $default; $v = $v[$k]; }
    return $v;
}

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $c = cfg('db');
        $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=" . ($c['charset'] ?? 'utf8mb4'), $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

/** Run a query with bound params and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function val(string $sql, array $params = []) { return q($sql, $params)->fetchColumn(); }

/** Site settings (editable in admin → Settings), cached per request. */
function setting(string $k, string $default = ''): string
{
    static $all;
    if ($all === null) {
        try { $all = array_column(rows('SELECT k, v FROM settings'), 'v', 'k'); } catch (Throwable $e) { $all = []; }
    }
    return isset($all[$k]) && $all[$k] !== '' ? (string)$all[$k] : $default;
}
