<?php
/*
 * One-time installer: creates the tables, loads the current site content (app/database/seed.json)
 * and creates the first admin account. It locks itself once an admin exists - delete the /install
 * folder from the server afterwards anyway.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$step = 'form'; $errors = []; $done = [];

if (!is_file(APP . '/config.php')) {
    $step = 'config';
} else {
    try { db(); } catch (Throwable $e) { $step = 'dberror'; $dbError = $e->getMessage(); }
}
if ($step === 'form') {
    $installed = false;
    try { $installed = (int)val('SELECT COUNT(*) FROM users') > 0; } catch (Throwable $e) {}
    if ($installed) $step = 'locked';
}

if ($step === 'form' && is_post()) {
    csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if ($name === '') $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 10) $errors[] = 'Use a password of at least 10 characters.';
    if (!$errors) {
        try {
            // 1. tables
            schema_apply();
            $done[] = 'Database tables created.';

            // 2. content
            $seed = seed_data();
            $pdo = db(); $pdo->beginTransaction();
            $ins = function (string $table, array $r) {
                $cols = array_keys($r);
                q('INSERT IGNORE INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($r));
            };
            foreach ($seed['settings'] as $k => $v) q('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
            q('UPDATE settings SET v = ? WHERE k = "site_url"', [rtrim((string)cfg('base_url'), '/')]);
            foreach ($seed['pages'] as $p) $ins('pages', $p);
            foreach ($seed['posts'] as $p) $ins('posts', $p + ['status' => 'published']);
            foreach ($seed['projects'] as $p) { $p['gallery'] = json_encode($p['gallery'], JSON_UNESCAPED_SLASHES); $ins('projects', $p); }
            foreach ($seed['cities'] as $c) $ins('cities', $c);
            foreach ($seed['jobs'] as $j) $ins('jobs', $j);
            foreach ($seed['redirects'] as $r) $ins('redirects', $r);
            about_seed($seed);
            q('INSERT INTO settings (k, v) VALUES ("db_version", ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [(string)DB_VERSION]);
            $pdo->commit();
            $done[] = sprintf('Content loaded: %d pages, %d articles, %d projects, %d cities, %d jobs, %d redirects from the old site.',
                count($seed['pages']), count($seed['posts']), count($seed['projects']), count($seed['cities']), count($seed['jobs']), count($seed['redirects']));

            // 3. admin
            q('INSERT INTO users (name, email, pass_hash, role) VALUES (?, ?, ?, "administrator")', [$name, strtolower($email), password_hash($pass, PASSWORD_DEFAULT)]);
            $done[] = 'Admin account created for ' . $email . '.';
            $step = 'done';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'Install failed: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Install - Morphora Spaces</title><link rel="stylesheet" href="/admin/admin.css"></head>
<body class="auth"><main class="auth-box">
<h1>Morphora Spaces <small>Install</small></h1>
<?php if ($step === 'config'): ?>
  <p>Create <code>app/config.php</code> first: copy <code>app/config.sample.php</code>, fill in your MySQL database details (hPanel → Databases), then reload this page.</p>
<?php elseif ($step === 'dberror'): ?>
  <p class="msg err">Could not connect to the database. Check the details in <code>app/config.php</code>.</p>
  <?php if (cfg('debug')): ?><pre><?= e($dbError) ?></pre><?php endif ?>
<?php elseif ($step === 'locked'): ?>
  <p>The site is already installed. <a href="/admin/">Go to the admin</a>.</p>
  <p class="msg warn">For security, delete the <code>/install</code> folder from the server.</p>
<?php elseif ($step === 'done'): ?>
  <?php foreach ($done as $d): ?><p class="msg ok"><?= e($d) ?></p><?php endforeach ?>
  <p class="msg warn">Now delete the <code>/install</code> folder from the server.</p>
  <p><a class="btn" href="/admin/">Open the admin →</a></p>
<?php else: ?>
  <p>This creates the database tables, loads all current website content, and sets up your admin login.</p>
  <?php foreach ($errors as $er): ?><p class="msg err"><?= e($er) ?></p><?php endforeach ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Your name<input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
    <label>Admin email<input name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Password (10+ characters)<input name="password" type="password" minlength="10" required autocomplete="new-password"></label>
    <button class="btn" type="submit">Install</button>
  </form>
<?php endif ?>
</main></body></html>
