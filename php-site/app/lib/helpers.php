<?php
/* General helpers: escaping, URLs, responses, sessions, CSRF, formatting. */

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Absolute URL for a site path ("/about/" → "https://morphoraspaces.com/about/"). */
function abs_url(string $path = '/'): string
{
    if (preg_match('~^https?://~i', $path)) return $path;
    return rtrim((string)cfg('base_url', ''), '/') . '/' . ltrim($path, '/');
}

/** Clean site path for a page slug ('' → '/', 'about' → '/about/'). */
function path_for(string $slug): string { return $slug === '' ? '/' : '/' . trim($slug, '/') . '/'; }

function redirect(string $to, int $code = 302): void
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    // Hostinger runs behind LiteSpeed/Cloudflare: REMOTE_ADDR is the real client unless a proxy header is set by them
    foreach (['HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h]) && filter_var($_SERVER[$h], FILTER_VALIDATE_IP)) return $_SERVER[$h];
    }
    return '0.0.0.0';
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function str_limit(string $s, int $n): string { return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s; }

function plain(string $html): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<[^>]*>/', ' $0 ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }

function slugify(string $s): string
{
    $s = str_replace('&', ' and ', $s);
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = $t !== false ? str_replace(["'", '"', '`', '^', '~'], '', $t) : $s;   // Windows iconv turns é into 'e
    $s = strtolower(trim(preg_replace('~[^\pL\pN]+~u', '-', $s), '-'));
    return preg_replace('~[^a-z0-9-]+~', '', $s) ?: 'item';
}

function fmt_date(?string $d): string { return $d ? date('j F Y', strtotime($d)) : ''; }

function read_minutes(string $html): int { return max(2, (int)round(str_word_count(plain($html)) / 220)); }

/* ------------------------------------------------------------- session */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('ms_sess');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    session_boot();
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function flash(?string $msg = null, string $type = 'ok')
{
    session_boot();
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}

/* ------------------------------------------------------- page caching */
/** Let LiteSpeed (Hostinger) cache a public page; any admin save purges the cache. */
function cache_public(int $seconds = 86400): void
{
    header('X-LiteSpeed-Cache-Control: public,max-age=' . $seconds);
    header('Cache-Control: public, max-age=0, must-revalidate');
}
function cache_purge_all(): void { header('X-LiteSpeed-Purge: *'); }

/* ------------------------------------------------------------ views */
/** Render a view file with variables; returns the HTML. */
function view(string $name, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include APP . '/views/' . $name . '.php';
    return (string)ob_get_clean();
}

/** Render a public page inside the site layout and send it. */
function page_out(string $name, array $vars, array $meta): void
{
    $body = view($name, $vars);           // body first: sections can add JSON-LD while rendering
    echo view('layout', ['body' => $body, 'meta' => $meta]);
}
