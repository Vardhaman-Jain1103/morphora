<?php
/* Admin sign-in: sessions, brute-force limiting, access guard. */

const SESSION_IDLE = 8 * 3600;      // sign out after 8 hours without activity
const MAX_FAILS = 5;                // per IP per 15 minutes

function admin_user(): ?array
{
    session_boot();
    if (empty($_SESSION['uid'])) return null;
    if (time() - ($_SESSION['seen'] ?? 0) > SESSION_IDLE) { admin_logout(); return null; }
    $_SESSION['seen'] = time();
    static $u;
    return $u ??= row('SELECT id, name, email FROM users WHERE id = ?', [$_SESSION['uid']]);
}

function require_admin(): array
{
    $u = admin_user();
    if (!$u) redirect('/admin/?r=login&next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
    return $u;
}

function login_blocked(): bool
{
    return (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ok = 0 AND created_at > NOW() - INTERVAL 15 MINUTE', [client_ip()]) >= MAX_FAILS;
}

/** Returns null on success or an error message. */
function admin_login(string $email, string $pass): ?string
{
    if (login_blocked()) return 'Too many failed attempts. Please wait 15 minutes and try again.';
    $u = row('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    $ok = $u && password_verify($pass, $u['pass_hash']);
    q('INSERT INTO login_attempts (ip, email, ok) VALUES (?, ?, ?)', [client_ip(), mb_substr($email, 0, 190), $ok ? 1 : 0]);
    if (!$ok) return 'That email and password do not match.';
    if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['seen'] = time();
    q('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
    q('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 30 DAY');
    return null;
}

function admin_logout(): void
{
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); }
    session_destroy();
}
