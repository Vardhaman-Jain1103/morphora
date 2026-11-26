<?php
/*
 * Create (or reset) one test sign-in per role, from the command line:
 *
 *   php app/database/test-users.php                 # random shared password, printed once
 *   php app/database/test-users.php "MyPassword123"  # use your own (10+ characters)
 *   php app/database/test-users.php --remove        # delete every test account
 *
 * Same as Admin → Users → Test accounts. The app/ folder is not reachable from the web.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
require APP . '/admin/auth.php';
migrate();

$arg = $argv[1] ?? '';
if ($arg === '--remove') { echo test_accounts_remove() . " test accounts removed.\n"; exit(0); }
$pass = $arg !== '' ? $arg : 'Test-' . bin2hex(random_bytes(5));
if (strlen($pass) < 10) { fwrite(STDERR, "Use a password of at least 10 characters.\n"); exit(1); }

$made = test_accounts_create($pass);
printf("%-17s %s\n", 'ROLE', 'EMAIL');
foreach ($made as $role => $email) printf("%-17s %s\n", role_label($role), $email);
echo "\nPassword (all accounts): {$pass}\nSign in at " . rtrim((string)cfg('base_url'), '/') . "/admin/\n";
