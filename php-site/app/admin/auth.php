<?php
/* Admin sign-in: sessions, brute-force limiting, roles & permissions, access guard. */

const SESSION_IDLE = 8 * 3600;      // sign out after 8 hours without activity
const MAX_FAILS = 5;                // per IP per 15 minutes

/** Roles in the order they appear in the role picker. */
const ROLES = [
    'hr' => 'HR',
    'latepoint_agent' => 'LatePoint Agent',
    'subscriber' => 'Subscriber',
    'contributor' => 'Contributor',
    'author' => 'Author',
    'editor' => 'Editor',
    'administrator' => 'Administrator',
];
const DEFAULT_ROLE = 'subscriber';

/*
 * What each role may do. Every check in the admin goes through can(), so this table is the single source of truth.
 *   manage:<type>    list / create / edit / delete that content type (see app/admin/resources.php)
 *   posts:others     see and edit articles written by other people (without it: own articles only)
 *   posts:publish    publish articles (without it: drafts only, and published articles become read-only)
 *   enquiries        view enquiries and update their status / notes
 *   enquiries:manage delete and export enquiries
 *   applications     view, update and delete job applications, download CVs
 *   settings         site settings
 *   users            add, remove and change the role of users
 *   upload           upload images (needed by anyone who edits content)
 */
const ROLE_CAPS = [
    'administrator' => ['*'],
    'editor' => ['manage:posts', 'manage:projects', 'manage:team', 'manage:testimonials', 'manage:cities', 'manage:pages', 'manage:redirects', 'posts:others', 'posts:publish', 'upload'],
    'author' => ['manage:posts', 'posts:publish', 'upload'],
    'contributor' => ['manage:posts', 'upload'],
    'hr' => ['manage:jobs', 'applications', 'upload'],
    'latepoint_agent' => ['enquiries'],
    'subscriber' => [],
];

/** Plain-language summary of each role, shown on the Users page. */
const ROLE_HELP = [
    'administrator' => 'Everything, including settings and users.',
    'editor' => 'All website content: articles (anyone’s), projects, team, testimonials, cities, pages and redirects.',
    'author' => 'Writes, publishes and deletes their own articles.',
    'contributor' => 'Writes their own articles as drafts. Cannot publish, or edit an article once it is published.',
    'hr' => 'Job openings and job applications (including CVs).',
    'latepoint_agent' => 'Enquiries: view them and update status and notes. Cannot delete or export.',
    'subscriber' => 'Can sign in and change their own password. Sees no site data.',
];

/** Domain of the test accounts created from the Users page (one per role). */
const TEST_DOMAIN = 'morphora.test';

function admin_user(): ?array
{
    session_boot();
    if (empty($_SESSION['uid'])) return null;
    if (time() - ($_SESSION['seen'] ?? 0) > SESSION_IDLE) { admin_logout(); return null; }
    $_SESSION['seen'] = time();
    static $u;
    if ($u === null) {
        $u = row('SELECT id, name, email, role FROM users WHERE id = ?', [$_SESSION['uid']]);
        if (!$u) { admin_logout(); return null; }               // account was removed: end the session
        if (!isset(ROLES[$u['role']])) $u['role'] = DEFAULT_ROLE; // unknown role: least privilege
    }
    return $u;
}

function require_admin(): array
{
    $u = admin_user();
    if (!$u) redirect('/admin/?r=login&next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
    return $u;
}

/** Does the signed-in user (or $u) have this permission? */
function can(string $cap, ?array $u = null): bool
{
    $u ??= admin_user();
    if (!$u) return false;
    $caps = ROLE_CAPS[$u['role']] ?? [];
    return in_array('*', $caps, true) || in_array($cap, $caps, true);
}

function role_label(string $role): string { return ROLES[$role] ?? ROLES[DEFAULT_ROLE]; }

/** Articles: may the signed-in user open / change / delete this one? */
function can_edit_post(array $post): bool
{
    $u = admin_user();
    if (!$u || !can('manage:posts')) return false;
    if (!can('posts:others') && (int)($post['created_by'] ?? 0) !== (int)$u['id']) return false;
    if (($post['status'] ?? '') === 'published' && !can('posts:publish')) return false;
    return true;
}

/** Drafts on the public site (?preview=1): only for people who could edit that item. */
function can_preview_item(string $type, ?array $item = null): bool
{
    if (!admin_user() || !can('manage:' . $type)) return false;
    if ($type === 'posts' && $item && !can('posts:others')) return (int)($item['created_by'] ?? 0) === (int)admin_user()['id'];
    return true;
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

/* ------------------------------------------------------- test accounts */
function test_account_email(string $role): string { return 'test.' . str_replace('_', '-', $role) . '@' . TEST_DOMAIN; }

/**
 * Create (or reset) one sign-in per role so access can be checked role by role.
 * All share $pass. Returns [role => email].
 */
function test_accounts_create(string $pass): array
{
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $out = [];
    foreach (ROLES as $role => $label) {
        $email = test_account_email($role);
        q('INSERT INTO users (name, email, pass_hash, role) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE pass_hash = VALUES(pass_hash), role = VALUES(role), name = VALUES(name)',
            ['Test ' . $label, $email, $hash, $role]);
        $out[$role] = $email;
    }
    return $out;
}

function test_accounts_remove(): int
{
    return q('DELETE FROM users WHERE email LIKE ?', ['%@' . TEST_DOMAIN])->rowCount();
}
