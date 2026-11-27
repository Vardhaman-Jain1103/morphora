<?php
/*
 * Morphora Spaces - admin panel (/admin/).
 * Routes (?r=): login, logout, dashboard, enquiries, applications, cv, list, edit, settings, users, account, upload
 * Access is role based: every route checks can() (permissions per role live in app/admin/auth.php).
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/admin/auth.php';
require APP . '/admin/resources.php';
require APP . '/admin/ui.php';
migrate();

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
header('X-LiteSpeed-Cache-Control: no-cache');
session_boot();

$r = preg_replace('/[^a-z]/', '', (string)($_GET['r'] ?? 'dashboard'));

if ($r === 'login') admin_login_page();
if ($r === 'logout') { if (is_post()) { csrf_check(); admin_logout(); } redirect('/admin/?r=login'); }

$me = require_admin();
if (is_post()) csrf_check();

// Permission each route needs. list / edit check the content type inside res_or_404().
$routeCap = ['enquiries' => 'enquiries', 'applications' => 'applications', 'cv' => 'applications', 'settings' => 'settings', 'users' => 'users', 'upload' => 'upload'];
if (isset($routeCap[$r])) require_cap($routeCap[$r]);

switch ($r) {
    case 'enquiries': admin_enquiries(); break;
    case 'applications': admin_applications(); break;
    case 'cv': admin_cv(); break;
    case 'list': admin_list(); break;
    case 'edit': admin_edit(); break;
    case 'settings': admin_settings(); break;
    case 'users': admin_users($me); break;
    case 'account': admin_account($me); break;
    case 'upload': admin_upload(); break;
    case 'upload-diag': admin_upload_diag(); break;
    default: admin_dashboard($me);
}

/* ================================================================ login */
function admin_login_page(): void
{
    if (admin_user()) redirect('/admin/');
    $err = null;
    if (is_post()) {
        csrf_check();
        $err = admin_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
        if (!$err) {
            $next = (string)($_GET['next'] ?? '/admin/');
            redirect(strpos($next, '/admin/') === 0 ? $next : '/admin/');
        }
    }
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Sign in - <?= e(setting('site_name', 'Morphora Spaces')) ?></title><link rel="stylesheet" href="/admin/admin.css"><link rel="icon" href="/images/favicon.ico" sizes="any"><link rel="icon" href="/images/favicon-32.png" type="image/png" sizes="32x32"></head>
<body class="auth"><main class="auth-box">
  <img src="/images/morphora-spaces-logo.png" alt="Morphora Spaces" class="auth-logo">
  <h1>Sign in</h1>
  <?php if ($err): ?><p class="msg err"><?= e($err) ?></p><?php endif ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email<input name="email" type="email" required autofocus autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn" type="submit">Sign in</button>
  </form>
  <p class="muted"><a href="/">← Back to the website</a></p>
</main></body></html><?php
    exit;
}

/* ============================================================ dashboard */
function admin_dashboard(array $me): void
{
    $c = fn($sql, $args = []) => (int)val($sql, $args);
    $own = can('posts:others') ? '' : ' AND created_by = ' . (int)$me['id'];   // authors / contributors: own articles only
    $stats = [];
    if (can('enquiries')) {
        $stats[] = ['New enquiries', $c('SELECT COUNT(*) FROM enquiries WHERE status="new"'), '/admin/?r=enquiries&status=new', 'hot'];
        $stats[] = ['Enquiries this month', $c('SELECT COUNT(*) FROM enquiries WHERE status<>"spam" AND created_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'), '/admin/?r=enquiries', ''];
    }
    if (can('applications')) $stats[] = ['New applications', $c('SELECT COUNT(*) FROM applications WHERE status="new"'), '/admin/?r=applications', 'hot'];
    if (can('manage:jobs')) $stats[] = ['Open jobs', $c('SELECT COUNT(*) FROM jobs WHERE status="published"'), '/admin/?r=list&t=jobs', ''];
    if (can('manage:posts')) {
        $stats[] = [$own ? 'My published articles' : 'Published articles', $c('SELECT COUNT(*) FROM posts WHERE status="published"' . $own), '/admin/?r=list&t=posts', ''];
        $stats[] = [$own ? 'My drafts' : 'Draft articles', $c('SELECT COUNT(*) FROM posts WHERE status="draft"' . $own), '/admin/?r=list&t=posts', ''];
    }
    if (can('manage:projects')) $stats[] = ['Projects', $c('SELECT COUNT(*) FROM projects WHERE status="published"'), '/admin/?r=list&t=projects', ''];

    // SEO checklist: each item only for people who can fix it
    $checks = [];
    if (can('settings')) {
        $checks[] = [setting('gsc_verification') !== '', 'Google Search Console connected', 'Add the verification code in Settings, then submit ' . abs_url('/sitemap.xml') . ' in Search Console.', '/admin/?r=settings'];
        $checks[] = [setting('ga4_id') !== '', 'Google Analytics (GA4) connected', 'Add your GA4 measurement ID (G-…) in Settings.', '/admin/?r=settings'];
    }
    if (can('posts:others')) $checks[] = [($n = $c('SELECT COUNT(*) FROM posts WHERE status="published" AND seo_desc="" AND (excerpt IS NULL OR excerpt="")')) === 0, 'Every article has a description', "{$n} published articles have no summary or SEO description.", '/admin/?r=list&t=posts'];
    if (can('manage:cities')) $checks[] = [($n = $c('SELECT COUNT(*) FROM cities WHERE status="published" AND top=1 AND (body IS NULL OR body="")')) === 0, 'Top cities have local content', "{$n} top cities only use the standard text. Unique local content helps them rank.", '/admin/?r=list&t=cities'];
    if (can('manage:team')) $checks[] = [$c('SELECT COUNT(*) FROM team WHERE status="published"') > 0 && $c('SELECT COUNT(*) FROM testimonials WHERE status="published"') > 0, 'About page has team and testimonials', 'Add real team photos and client quotes, then publish them. The samples are hidden drafts.', '/admin/?r=list&t=team'];
    if (can('settings')) $checks[] = [setting('social_links') !== '', 'Social profiles linked', 'Add your LinkedIn / Instagram links in Settings so Google can connect them to the business.', '/admin/?r=settings'];

    $sections = array_values(array_filter(admin_nav(), fn($n) => $n[0] !== 'dashboard' && $n[3] !== '' && can($n[3])));
    ob_start(); ?>
<section class="card welcome">
  <h2>Welcome, <?= e($me['name']) ?></h2>
  <p>You are signed in as <b><?= e($me['email']) ?></b> with the <span class="pill role"><?= e(role_label($me['role'])) ?></span> role. <?= e(ROLE_HELP[$me['role']] ?? '') ?></p>
<?php if ($sections): ?>
  <p class="small">You can open: <?= implode(' · ', array_map(fn($n) => '<a href="' . e($n[1]) . '">' . e($n[2]) . '</a>', $sections)) ?></p>
<?php else: ?>
  <p class="small muted">Your role does not include any admin sections. You can <a href="/admin/?r=account">change your password</a>.</p>
<?php endif ?>
</section>
<?php if ($stats): ?>
<div class="stats">
<?php foreach ($stats as [$l, $n, $u, $cls]): ?>
  <a class="stat <?= $cls && $n ? $cls : '' ?>" href="<?= e($u) ?>"><b><?= $n ?></b><span><?= e($l) ?></span></a>
<?php endforeach ?>
</div>
<?php endif ?>
<?php if (can('enquiries') || $checks): ?>
<div class="cols">
<?php if (can('enquiries')):
    $recent = rows('SELECT * FROM enquiries WHERE status<>"spam" ORDER BY id DESC LIMIT 8');
    $bySource = rows('SELECT source, COUNT(*) n FROM enquiries WHERE status<>"spam" AND created_at > NOW() - INTERVAL 30 DAY GROUP BY source'); ?>
  <section class="card">
    <div class="card-h"><h2>Latest enquiries</h2><a href="/admin/?r=enquiries">All enquiries →</a></div>
    <?= enquiry_table($recent) ?>
    <?php if ($bySource): ?><p class="muted small">Last 30 days: <?= e(implode(' · ', array_map(fn($s) => ($s['source'] === 'chat' ? 'chat assistant' : 'contact form') . ' ' . $s['n'], $bySource))) ?></p><?php endif ?>
  </section>
<?php endif ?>
<?php if ($checks): ?>
  <section class="card">
    <div class="card-h"><h2>SEO checklist</h2></div>
    <ul class="checks">
<?php foreach ($checks as [$ok, $t, $help, $u]): ?>
      <li class="<?= $ok ? 'ok' : 'todo' ?>"><b><?= $ok ? '✓' : '!' ?></b><div><strong><?= e($t) ?></strong><?php if (!$ok): ?><span><?= e($help) ?> <a href="<?= e($u) ?>">Fix →</a></span><?php endif ?></div></li>
<?php endforeach ?>
    </ul>
    <p class="muted small">Sitemap: <a href="/sitemap.xml" target="_blank"><?= e(abs_url('/sitemap.xml')) ?></a> - updates itself whenever you publish.</p>
  </section>
<?php endif ?>
</div>
<?php endif ?>
<?php
    admin_page('Dashboard', ob_get_clean(), 'dashboard');
}

/* ============================================================ enquiries */
function enquiry_table(array $list): string
{
    if (!$list) return '<p class="empty">No enquiries yet.</p>';
    $h = '<table class="tbl"><thead><tr><th>Name</th><th>Phone</th><th>City</th><th>Service</th><th>Source</th><th>Status</th><th>Received</th></tr></thead><tbody>';
    foreach ($list as $q) {
        $h .= '<tr class="' . ($q['status'] === 'new' ? 'unread' : '') . '"><td><a href="/admin/?r=enquiries&id=' . $q['id'] . '">' . e($q['name']) . '</a>' . ($q['company'] ? '<small>' . e($q['company']) . '</small>' : '') . '</td><td><a href="tel:' . e(preg_replace('/[^\d+]/', '', $q['phone'])) . '">' . e($q['phone']) . '</a></td><td>' . e($q['city']) . '</td><td>' . e($q['service']) . '</td><td>' . ($q['source'] === 'chat' ? 'Chat' : 'Form') . '</td><td>' . status_pill($q['status'], ENQ_STATUS) . '</td><td title="' . e($q['created_at']) . '">' . e(ago($q['created_at'])) . '</td></tr>';
    }
    return $h . '</tbody></table>';
}

function admin_enquiries(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if (is_post()) {
        if (isset($_POST['delete'])) { require_cap('enquiries:manage'); q('DELETE FROM enquiries WHERE id = ?', [$id]); flash('Enquiry deleted.'); redirect('/admin/?r=enquiries'); }
        $st = array_key_exists($_POST['status'] ?? '', ENQ_STATUS) ? $_POST['status'] : 'new';
        q('UPDATE enquiries SET status = ?, notes = ? WHERE id = ?', [$st, mb_substr((string)($_POST['notes'] ?? ''), 0, 5000), $id]);
        flash('Saved.'); redirect('/admin/?r=enquiries&id=' . $id);
    }
    if ($id) {
        $q = row('SELECT * FROM enquiries WHERE id = ?', [$id]) ?: redirect('/admin/?r=enquiries');
        $wa = preg_replace('/\D/', '', $q['phone']); if (strlen($wa) === 10) $wa = '91' . $wa;
        ob_start(); ?>
<p><a href="/admin/?r=enquiries">← All enquiries</a></p>
<div class="cols">
  <section class="card">
    <dl class="kv">
<?php foreach (['Name' => 'name', 'Company' => 'company', 'Phone' => 'phone', 'Email' => 'email', 'City' => 'city', 'Office size' => 'size', 'Service' => 'service', 'Message' => 'message', 'Source' => 'source', 'From page' => 'page', 'Came from' => 'referrer', 'Campaign' => 'utm', 'Received' => 'created_at'] as $l => $k): if ((string)$q[$k] === '') continue; ?>
      <dt><?= e($l) ?></dt><dd><?= nl2br(e($k === 'source' ? ($q[$k] === 'chat' ? 'Chat assistant' : 'Contact form') : $q[$k])) ?></dd>
<?php endforeach ?>
    </dl>
    <div class="row-btns">
      <a class="btn" href="tel:<?= e(preg_replace('/[^\d+]/', '', $q['phone'])) ?>">Call</a>
      <a class="btn ghost" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
      <?php if ($q['email']): ?><a class="btn ghost" href="mailto:<?= e($q['email']) ?>">Email</a><?php endif ?>
    </div>
  </section>
  <section class="card">
    <form method="post">
      <?= csrf_field() ?>
      <label>Status<select name="status"><?php foreach (ENQ_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $q['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></label>
      <label>Notes<textarea name="notes" rows="8" placeholder="Call notes, follow-up date, quote sent…"><?= e($q['notes']) ?></textarea></label>
      <div class="row-btns"><button class="btn" type="submit">Save</button><?php if (can('enquiries:manage')): ?><button class="btn danger" name="delete" value="1" type="submit" data-confirm="Delete this enquiry permanently?">Delete</button><?php endif ?></div>
    </form>
  </section>
</div>
<?php
        admin_page('Enquiry from ' . $q['name'], ob_get_clean(), 'enquiries');
        return;
    }
    // list + filters
    $where = []; $args = [];
    $status = (string)($_GET['status'] ?? '');
    if (isset(ENQ_STATUS[$status])) { $where[] = 'status = ?'; $args[] = $status; } else $where[] = 'status <> "spam"';
    if (in_array($_GET['source'] ?? '', ['form', 'chat'], true)) { $where[] = 'source = ?'; $args[] = $_GET['source']; }
    if (($s = trim((string)($_GET['q'] ?? ''))) !== '') { $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR company LIKE ? OR city LIKE ?)'; array_push($args, ...array_fill(0, 5, '%' . $s . '%')); }
    $w = ' WHERE ' . implode(' AND ', $where);
    if (!empty($_GET['export'])) {
        require_cap('enquiries:manage');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="enquiries-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Received', 'Status', 'Source', 'Name', 'Company', 'Phone', 'Email', 'City', 'Office size', 'Service', 'Message', 'Page', 'Campaign', 'Notes'], ',', '"', '');
        foreach (q('SELECT * FROM enquiries' . $w . ' ORDER BY id DESC', $args) as $x) fputcsv($out, [$x['id'], $x['created_at'], $x['status'], $x['source'], $x['name'], $x['company'], $x['phone'], $x['email'], $x['city'], $x['size'], $x['service'], $x['message'], $x['page'], $x['utm'], $x['notes']], ',', '"', '');
        exit;
    }
    [$list, $pager] = paginate('SELECT * FROM enquiries' . $w . ' ORDER BY id DESC', 'SELECT COUNT(*) FROM enquiries' . $w, $args);
    $qs = http_build_query(array_filter(['r' => 'enquiries', 'status' => $status, 'source' => $_GET['source'] ?? '', 'q' => $s]));
    ob_start(); ?>
<form class="filters" method="get">
  <input type="hidden" name="r" value="enquiries">
  <input type="search" name="q" value="<?= e($s) ?>" placeholder="Search name, phone, email, city">
  <select name="status"><option value="">All (except spam)</option><?php foreach (ENQ_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select>
  <select name="source"><option value="">Form + chat</option><option value="form"<?= ($_GET['source'] ?? '') === 'form' ? ' selected' : '' ?>>Contact form</option><option value="chat"<?= ($_GET['source'] ?? '') === 'chat' ? ' selected' : '' ?>>Chat assistant</option></select>
  <button class="btn ghost" type="submit">Filter</button>
  <?php if (can('enquiries:manage')): ?><a class="btn ghost" href="/admin/?<?= e($qs) ?>&amp;export=1">Export CSV</a><?php endif ?>
</form>
<section class="card"><?= enquiry_table($list) ?><?= $pager ?></section>
<?php
    admin_page('Enquiries', ob_get_clean(), 'enquiries');
}

/* ========================================================= applications */
function admin_applications(): void
{
    $id = (int)($_GET['id'] ?? 0);
    if (is_post()) {
        if (isset($_POST['delete'])) {
            $a = row('SELECT cv_file FROM applications WHERE id = ?', [$id]);
            if ($a && $a['cv_file'] && is_file(APP . '/storage/cv/' . basename($a['cv_file']))) @unlink(APP . '/storage/cv/' . basename($a['cv_file']));
            q('DELETE FROM applications WHERE id = ?', [$id]); flash('Application and CV deleted.'); redirect('/admin/?r=applications');
        }
        $st = array_key_exists($_POST['status'] ?? '', APP_STATUS) ? $_POST['status'] : 'new';
        q('UPDATE applications SET status = ?, notes = ? WHERE id = ?', [$st, mb_substr((string)($_POST['notes'] ?? ''), 0, 5000), $id]);
        flash('Saved.'); redirect('/admin/?r=applications&id=' . $id);
    }
    if ($id) {
        $a = row('SELECT * FROM applications WHERE id = ?', [$id]) ?: redirect('/admin/?r=applications');
        ob_start(); ?>
<p><a href="/admin/?r=applications">← All applications</a></p>
<div class="cols">
  <section class="card">
    <dl class="kv">
<?php foreach (['Role' => 'job_title', 'Name' => 'name', 'Email' => 'email', 'Phone' => 'phone', 'Portfolio' => 'portfolio', 'About' => 'message', 'Received' => 'created_at'] as $l => $k): if ((string)$a[$k] === '') continue; ?>
      <dt><?= e($l) ?></dt><dd><?= $k === 'portfolio' && preg_match('~^https?://~', $a[$k]) ? '<a href="' . e($a[$k]) . '" target="_blank" rel="noopener noreferrer">' . e($a[$k]) . '</a>' : nl2br(e($a[$k])) ?></dd>
<?php endforeach ?>
    </dl>
    <div class="row-btns">
      <?php if ($a['cv_file']): ?><a class="btn" href="/admin/?r=cv&amp;id=<?= $a['id'] ?>">Download CV</a><?php endif ?>
      <a class="btn ghost" href="mailto:<?= e($a['email']) ?>">Email</a>
    </div>
  </section>
  <section class="card">
    <form method="post">
      <?= csrf_field() ?>
      <label>Status<select name="status"><?php foreach (APP_STATUS as $k => $l): ?><option value="<?= $k ?>"<?= $a['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></label>
      <label>Notes<textarea name="notes" rows="8"><?= e($a['notes']) ?></textarea></label>
      <div class="row-btns"><button class="btn" type="submit">Save</button><button class="btn danger" name="delete" value="1" type="submit" data-confirm="Delete this application and its CV permanently?">Delete</button></div>
    </form>
  </section>
</div>
<?php
        admin_page('Application: ' . $a['name'], ob_get_clean(), 'applications');
        return;
    }
    [$list, $pager] = paginate('SELECT * FROM applications ORDER BY id DESC', 'SELECT COUNT(*) FROM applications');
    ob_start(); ?>
<section class="card">
<?php if (!$list): ?><p class="empty">No applications yet.</p><?php else: ?>
<table class="tbl"><thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Phone</th><th>Status</th><th>Received</th></tr></thead><tbody>
<?php foreach ($list as $a): ?>
<tr class="<?= $a['status'] === 'new' ? 'unread' : '' ?>"><td><a href="/admin/?r=applications&amp;id=<?= $a['id'] ?>"><?= e($a['name']) ?></a></td><td><?= e($a['job_title']) ?></td><td><?= e($a['email']) ?></td><td><?= e($a['phone']) ?></td><td><?= status_pill($a['status'], APP_STATUS) ?></td><td><?= e(ago($a['created_at'])) ?></td></tr>
<?php endforeach ?>
</tbody></table><?= $pager ?>
<?php endif ?>
</section>
<?php
    admin_page('Applications', ob_get_clean(), 'applications');
}

function admin_cv(): void
{
    $a = row('SELECT cv_file, cv_name, name FROM applications WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
    $f = $a ? APP . '/storage/cv/' . basename($a['cv_file']) : '';
    if (!$a || !$a['cv_file'] || !is_file($f)) { http_response_code(404); exit('CV not found.'); }
    $ext = pathinfo($f, PATHINFO_EXTENSION);
    $types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="CV - ' . preg_replace('/[^\w .-]/u', '', $a['name']) . '.' . $ext . '"');
    header('Content-Length: ' . filesize($f));
    header('X-Content-Type-Options: nosniff');
    readfile($f);
    exit;
}

/* ===================================================== generic content */
function res_or_404(): array
{
    $t = (string)($_GET['t'] ?? '');
    $all = resources();
    if (!isset($all[$t])) { http_response_code(404); exit('Unknown section.'); }
    require_cap('manage:' . $t);
    $R = $all[$t];
    if ($t === 'posts' && !can('posts:publish')) {
        // contributors: drafts only
        $R['fields']['status']['options'] = ['draft' => 'Draft (an editor publishes it)'];
        $R['fields']['status']['help'] = 'Your role can save drafts. An editor or administrator reviews and publishes them.';
    }
    return [$t, $R];
}

function seo_report(array $record, string $type): array
{
    $keyword = trim((string)($record['focus_keyword'] ?? ''));
    $title = trim((string)($record['seo_title'] ?? '')) ?: (string)($record['title'] ?? $record['name'] ?? ucwords(str_replace('-', ' ', (string)($record['slug'] ?? ''))));
    $source = (string)($record['body'] ?? $record['summary'] ?? $record['description'] ?? '');
    $text = plain($source);
    $description = trim((string)($record['seo_desc'] ?? '')) ?: trim((string)($record['excerpt'] ?? '')) ?: str_limit($text, 160);
    $normalize = static fn(string $value): string => trim((string)preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($value)));
    $contains = static function (string $haystack, string $needle) use ($normalize): bool {
        $phrase = $normalize($needle);
        return $phrase !== '' && strpos(' ' . $normalize($haystack) . ' ', ' ' . $phrase . ' ') !== false;
    };
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $firstWords = implode(' ', array_slice($words, 0, max(1, (int)ceil(count($words) * 0.1))));
    preg_match_all('/<h[1-6]\b[^>]*>(.*?)<\/h[1-6]>/is', $source, $headingMatches);
    $headings = plain(implode(' ', $headingMatches[1] ?? []));
    preg_match_all('/<img\b[^>]*>/i', $source, $imageMatches);
    $imagesHaveAlt = !array_filter($imageMatches[0] ?? [], fn($image) => !preg_match('/\balt\s*=\s*(["\'])\s*\S.*?\1/i', $image));
    preg_match_all('/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1/i', $source, $matches);
    $internal = $external = 0;
    $host = strtolower((string)parse_url(abs_url('/'), PHP_URL_HOST));
    foreach ($matches[2] ?? [] as $href) {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $scheme = strtolower((string)parse_url($href, PHP_URL_SCHEME));
        if (in_array($scheme, ['mailto', 'tel', 'javascript'], true)) continue;
        $linkHost = strtolower((string)parse_url($href, PHP_URL_HOST));
        if ($linkHost === '' || $linkHost === $host) $internal++;
        else $external++;
    }
    $checks = [
        $keyword !== '',
        $contains($title, $keyword),
        $contains($description, $keyword),
        $contains((string)($record['slug'] ?? ''), $keyword),
        $contains($firstWords, $keyword),
        $contains($headings, $keyword),
        count($words) >= 300,
        mb_strlen($title) >= 30 && mb_strlen($title) <= 60,
        mb_strlen($description) >= 120 && mb_strlen($description) <= 160,
        $imagesHaveAlt,
        $internal > 0,
    ];
    $score = $keyword !== '' ? (int)round(count(array_filter($checks)) / count($checks) * 100) : null;
    $schema = (string)($record['schema_type'] ?? 'auto');
    if ($schema === '' || $schema === 'auto') {
        $schema = ['posts' => 'BlogPosting', 'projects' => 'CreativeWork', 'cities' => 'Service', 'jobs' => 'JobPosting'][$type] ?? 'WebPage';
        if ($type === 'pages') {
            $schema = ['about' => 'AboutPage', 'contact' => 'ContactPage', 'design-and-build' => 'Service', 'real-estate' => 'Service', 'pmc' => 'Service', 'resources' => 'Blog'][(string)($record['slug'] ?? '')] ?? 'WebPage';
        }
    }
    return ['keyword' => $keyword, 'score' => $score, 'schema' => $schema, 'internal' => $internal, 'external' => $external];
}

function admin_list(): void
{
    [$t, $R] = res_or_404();
    if (is_post() && isset($_POST['delete']) && empty($R['nodelete'])) {
        $del = row("SELECT * FROM {$R['table']} WHERE id = ?", [(int)$_POST['delete']]);
        if ($del && $t === 'posts' && !can_edit_post($del)) admin_forbidden('You can only delete your own articles' . (can('posts:publish') ? '.' : ' while they are drafts.'));
        if ($del) q("DELETE FROM {$R['table']} WHERE id = ?", [(int)$del['id']]);
        cache_purge_all(); flash(ucfirst($R['one']) . ' deleted.'); redirect('/admin/?r=list&t=' . $t);
    }
    $where = ''; $args = [];
    if (($s = trim((string)($_GET['q'] ?? ''))) !== '') {
        $where = ' WHERE (' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $R['search'])) . ')';
        $args = array_fill(0, count($R['search']), '%' . $s . '%');
    }
    if ($t === 'posts' && !can('posts:others')) {   // authors / contributors: own articles only
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . 'created_by = ?';
        $args[] = (int)admin_user()['id'];
    }
    $seoFilter = (string)($_GET['seo_filter'] ?? '');
    if (!empty($R['seo_details'])) {
        $seoConditions = [
            'missing_keyword' => "focus_keyword = ''",
            'missing_title' => "seo_title = ''",
            'missing_description' => "seo_desc = ''",
            'noindex' => 'noindex = 1',
        ];
        if (isset($seoConditions[$seoFilter])) $where .= ($where === '' ? ' WHERE ' : ' AND ') . $seoConditions[$seoFilter];
    }
    [$list, $pager] = paginate("SELECT * FROM {$R['table']}{$where} ORDER BY {$R['order']}", "SELECT COUNT(*) FROM {$R['table']}{$where}", $args, 50);
    ob_start(); ?>
<?php if (!empty($R['intro'])): ?><p class="muted"><?= e($R['intro']) ?></p><?php endif ?>
<form class="filters" method="get">
  <input type="hidden" name="r" value="list"><input type="hidden" name="t" value="<?= e($t) ?>">
  <input type="search" name="q" value="<?= e($s) ?>" placeholder="Search <?= e(strtolower($R['title'])) ?>">
  <button class="btn ghost" type="submit">Search</button>
  <?php if (!empty($R['seo_details'])): ?><select name="seo_filter"><option value="">All SEO statuses</option><option value="missing_keyword"<?= $seoFilter === 'missing_keyword' ? ' selected' : '' ?>>Focus keyword missing</option><option value="missing_title"<?= $seoFilter === 'missing_title' ? ' selected' : '' ?>>SEO title missing</option><option value="missing_description"<?= $seoFilter === 'missing_description' ? ' selected' : '' ?>>SEO description missing</option><option value="noindex"<?= $seoFilter === 'noindex' ? ' selected' : '' ?>>Noindex items</option></select><?php endif ?>
  <?php if (empty($R['nocreate'])): ?><a class="btn" href="/admin/?r=edit&amp;t=<?= e($t) ?>">+ New <?= e($R['one']) ?></a><?php endif ?>
</form>
<section class="card">
<?php if (!$list): ?><p class="empty">Nothing here yet.</p><?php else: ?>
<table class="tbl"><thead><tr><?php foreach ($R['list'] as $l): ?><th><?= e($l) ?></th><?php endforeach ?><?php if (!empty($R['seo_details'])): ?><th>SEO Details</th><?php endif ?><th></th></tr></thead><tbody>
<?php foreach ($list as $row): ?>
<tr>
<?php $first = true; foreach ($R['list'] as $k => $l): $v = $row[$k]; ?>
  <td><?php
    if ($first) { echo '<a href="/admin/?r=edit&amp;t=' . e($t) . '&amp;id=' . $row['id'] . '">' . e($k === 'slug' ? path_for((string)$v) : $v) . '</a>'; $first = false; }
    elseif ($k === 'status') echo status_pill((string)$v, ['published' => 'Published', 'draft' => 'Draft', 'closed' => 'Closed']);
    elseif (in_array($k, ['featured', 'top'], true)) echo $v ? '✓' : '';
    elseif (in_array($k, ['updated_at', 'created_at'], true)) echo e(ago((string)$v));
    elseif ($k === 'published_at') echo e(fmt_date($v)) . ($v > date('Y-m-d') ? ' <span class="pill draft">Scheduled</span>' : '');
    elseif ($k === 'code') echo (int)$v === 302 ? 'Temporary' : 'Permanent';
    else echo e(str_limit((string)$v, 70));
  ?></td>
<?php endforeach ?>
<?php if (!empty($R['seo_details'])): $seo = seo_report($row, $t); ?>
  <td class="seo-details"><span class="seo-list-score<?= $seo['score'] === null ? ' na' : ($seo['score'] >= 70 ? ' good' : ' low') ?>"><?= $seo['score'] === null ? 'N/A' : $seo['score'] . '/100' ?></span><small>Keyword: <?= e($seo['keyword'] ?: 'Not set') ?></small><small>Schema: <?= e($seo['schema']) ?></small><small>Links: <?= (int)$seo['internal'] ?> internal · <?= (int)$seo['external'] ?> external</small></td>
<?php endif ?>
  <td class="acts"><?php if (!empty($R['url'])): ?><a href="<?= e(($R['url'])($row)) ?><?= ($row['status'] ?? 'published') !== 'published' ? '?preview=1' : '' ?>" target="_blank">View ↗</a><?php endif ?></td>
</tr>
<?php endforeach ?>
</tbody></table><?= $pager ?>
<?php endif ?>
</section>
<?php
    admin_page($R['title'], ob_get_clean(), $t);
}

function admin_edit(): void
{
    [$t, $R] = res_or_404();
    $id = (int)($_GET['id'] ?? 0);
    $rec = $id ? row("SELECT * FROM {$R['table']} WHERE id = ?", [$id]) : null;
    if ($id && !$rec) redirect('/admin/?r=list&t=' . $t);
    if (!$id && !empty($R['nocreate'])) redirect('/admin/?r=list&t=' . $t);
    if ($rec && $t === 'posts' && !can_edit_post($rec)) {
        admin_forbidden((int)($rec['created_by'] ?? 0) === (int)admin_user()['id']
            ? 'This article is published. Your role cannot change published articles - ask an editor.'
            : 'You can only open articles you wrote.');
    }
    $errors = [];

    if (is_post()) {
        if (isset($_POST['delete']) && $rec && empty($R['nodelete'])) {
            q("DELETE FROM {$R['table']} WHERE id = ?", [$id]); cache_purge_all();
            flash(ucfirst($R['one']) . ' deleted.'); redirect('/admin/?r=list&t=' . $t);
        }
        $data = [];
        foreach ($R['fields'] as $k => $f) {
            if (in_array('readonly', $f, true) || !field_applies($f, $rec)) continue;
            $type = $f[1]; $raw = $_POST[$k] ?? '';
            switch ($type) {
                case 'check': $v = empty($_POST[$k]) ? 0 : 1; break;
                case 'number': $v = (int)$raw; break;
                case 'date': $v = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$raw) ? $raw : null; break;
                case 'gallery':
                    $g = json_decode((string)$raw, true);
                    $v = json_encode(array_values(array_filter(is_array($g) ? $g : [], fn($x) => is_array($x) && isset($x[0]) && is_string($x[0]))), JSON_UNESCAPED_SLASHES);
                    break;
                case 'select': $v = array_key_exists((string)$raw, $f['options']) ? (string)$raw : (string)array_key_first($f['options']); break;
                case 'slug': $v = slugify((string)($raw !== '' ? $raw : ($_POST[$f['from'] ?? ''] ?? ''))); break;
                default: $v = trim((string)$raw);
            }
            if (in_array('required', $f, true) && ($v === '' || $v === null)) $errors[] = $f[0] . ' is required.';
            $data[$k] = $v;
        }
        if ($t === 'posts' && !$rec && ($data['author'] ?? '') === '') $data['author'] = (string)admin_user()['name'];
        if ($t === 'projects' && ($data['category_id'] ?? '') === '') $data['category_id'] = null;
        if ($t === 'project_categories' && !$errors && (int)val('SELECT COUNT(*) FROM project_categories WHERE name = ? AND id <> ?', [$data['name'], $id])) {
            $errors[] = 'A project category with that name already exists.';
        }
        // table-specific rules
        if (isset($data['slug']) && !$errors) {
            if ((int)val("SELECT COUNT(*) FROM {$R['table']} WHERE slug = ? AND id <> ?", [$data['slug'], $id])) $errors[] = 'Another ' . $R['one'] . ' already uses that URL.';
        }
        if ($t === 'redirects') {
            $data['from_path'] = '/' . trim(parse_url($data['from_path'], PHP_URL_PATH) ?: '', '/') . '/';
            if ($data['from_path'] === '//') $errors[] = 'Enter the old URL path.';
            if ((int)val('SELECT COUNT(*) FROM redirects WHERE from_path = ? AND id <> ?', [$data['from_path'], $id])) $errors[] = 'A redirect for that path already exists.';
            $data['code'] = (int)$data['code'];
        }
        if (($data['linkedin'] ?? '') !== '' && !preg_match('~^https?://~i', $data['linkedin'])) $errors[] = 'The LinkedIn profile must be a full link starting with https://';
        if (($data['canonical_url'] ?? '') !== '') {
            $canonical = (string)$data['canonical_url'];
            $isPath = preg_match('~^/(?!/)[^\s]*$~', $canonical);
            $isUrl = filter_var($canonical, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $canonical);
            if (!$isPath && !$isUrl) $errors[] = 'Canonical URL must be a full http(s) URL or a site path beginning with /.';
            if (mb_strlen($canonical) > 500) $errors[] = 'Canonical URL must be 500 characters or fewer.';
        }
        if (mb_strlen((string)($data['focus_keyword'] ?? '')) > 190) $errors[] = 'Focus keyword must be 190 characters or fewer.';
        if (!$errors) {
            if ($rec) {
                q("UPDATE {$R['table']} SET " . implode(', ', array_map(fn($c) => "$c = ?", array_keys($data))) . ' WHERE id = ?', [...array_values($data), $id]);
                // URL changed on a live item → keep the old address working
                if (!empty($R['url']) && isset($data['slug']) && $data['slug'] !== $rec['slug']) {
                    $old = ($R['url'])($rec); $new = ($R['url'])(['slug' => $data['slug']] + $rec);
                    q('INSERT INTO redirects (from_path, to_path, code) VALUES (?, ?, 301) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)', [$old, $new]);
                    q('UPDATE redirects SET to_path = ? WHERE to_path = ?', [$new, $old]);   // no redirect chains
                    flash('The URL changed, so a redirect from the old address was added automatically.', 'info');
                }
            } else {
                if ($t === 'posts') {
                    if (($data['author'] ?? '') === '') $data['author'] = (string)admin_user()['name'];
                    $data['created_by'] = (int)admin_user()['id'];
                }
                q("INSERT INTO {$R['table']} (" . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data));
                $id = (int)db()->lastInsertId();
            }
            cache_purge_all();
            flash(ucfirst($R['one']) . ' saved.');
            redirect("/admin/?r=edit&t={$t}&id={$id}");
        }
        $rec = array_merge($rec ?? [], $data);
    }

    if ($t === 'posts' && !$rec) $R['defaults']['author'] = (string)admin_user()['name'];
    $vals = $rec ?? ($R['defaults'] ?? []);
    $main = $side = $seo = '';
    foreach ($R['fields'] as $k => $f) {
        if (!field_applies($f, $rec)) continue;
        $html = field_html($k, $f, $vals[$k] ?? '');
        if (($f['group'] ?? '') === 'seo') $seo .= $html;
        elseif (in_array($f[1], ['select', 'check', 'date', 'number'], true) || in_array($k, ['slug', 'category', 'author', 'city', 'size', 'location', 'experience', 'cover', 'thumb', 'hero', 'og_image', 'foot_label', 'foot_title', 'photo'], true)) $side .= $html;
        else $main .= $html;
    }
    $url = $rec && !empty($R['url']) ? ($R['url'])($rec) : '';
    ob_start(); ?>
<p><a href="/admin/?r=list&amp;t=<?= e($t) ?>">← All <?= e(strtolower($R['title'])) ?></a><?php if ($url): ?> · <a href="<?= e($url) ?><?= ($rec['status'] ?? 'published') !== 'published' ? '?preview=1' : '' ?>" target="_blank">View on site ↗</a><?php endif ?></p>
<?php foreach ($errors as $er): ?><p class="msg err"><?= e($er) ?></p><?php endforeach ?>
<form method="post" class="edit<?= $seo !== '' ? ' has-seo' : '' ?>" id="editForm">
  <?= csrf_field() ?>
  <div class="edit-main"><?= $main ?></div>
  <aside class="edit-side">
    <div class="card"><?= $side ?>
      <div class="row-btns"><button class="btn" type="submit">Save</button><?php if ($rec && empty($R['nodelete'])): ?><button class="btn danger" type="submit" name="delete" value="1" formnovalidate data-confirm="<?= e($t === 'project_categories' ? 'Delete this category? Assigned projects will become uncategorized.' : 'Delete this ' . $R['one'] . ' permanently? Its page will stop working.') ?>">Delete</button><?php endif ?></div>
    </div>
  </aside>
<?php if ($seo !== ''): ?>
  <section class="card seo-card" data-seo-panel data-source="<?= e($R['seo_source'] ?? '') ?>">
    <div class="seo-overview">
      <div class="seo-score-box"><strong data-seo-score>-</strong><span>SEO score</span></div>
      <p class="small muted" data-seo-note>Add a focus keyword to start the on-page checks.</p>
      <ul class="checks seo-checklist" data-seo-checks></ul>
    </div>
    <div class="seo-editor">
      <div class="seo-preview-grid">
        <div><h3>Google search preview</h3><div class="serp" data-url="<?= e(abs_url($url ?: '/')) ?>"><span class="serp-url"></span><span class="serp-t"></span><span class="serp-d"></span></div></div>
        <div><h3>Social sharing preview</h3><div class="social-preview"><img data-social-image alt="" hidden><div><b data-social-title></b><p data-social-desc></p></div></div></div>
      </div>
      <div class="seo-fields"><?= $seo ?></div>
    </div>
  </section>
<?php endif ?>
</form>
<?php
    admin_page(($rec ? 'Edit ' : 'New ') . $R['one'] . ($rec ? ': ' . ($rec['title'] ?? $rec['name'] ?? path_for((string)($rec['slug'] ?? $rec['from_path'] ?? ''))) : ''), ob_get_clean(), $t);
}

function field_applies(array $f, ?array $rec): bool
{
    if (empty($f['only'])) return true;
    foreach ($f['only'] as $k => $v) if (($rec[$k] ?? null) !== $v) return false;
    return true;
}

/* ============================================================== settings */
function admin_settings(): void
{
    $zonePhotoHelp = 'One starter photo from OneDrive is included. Add, reorder, or remove photos; only this gallery appears on the homepage.';
    $fields = [
        'Business' => [
            'site_name' => ['Business name', 'text'],
            'phone' => ['Phone (as shown)', 'text'], 'phone_e164' => ['Phone for links (+91…)', 'text'],
            'whatsapp' => ['WhatsApp number (digits, with 91)', 'text'],
            'email' => ['Public email', 'text'],
            'address_head' => ['Head office address', 'textarea'], 'address_reg' => ['Registered office address', 'textarea'],
            'geo_lat' => ['Map latitude', 'text'], 'geo_lng' => ['Map longitude', 'text'],
        ],
        'Notifications' => [
            'notify_email' => ['Send new enquiries and applications to', 'text', 'help' => 'One address, or several separated by commas.'],
        ],
        'SEO & tracking' => [
            'default_og_image' => ['Default social sharing image', 'image'],
            'social_links' => ['Social profile links', 'textarea', 'help' => 'One per line: LinkedIn, Instagram, Facebook, Google Business Profile… Helps Google link them to your business.'],
            'gsc_verification' => ['Google Search Console verification code', 'text', 'help' => 'Only the content="…" value from the HTML-tag method.'],
            'ga4_id' => ['Google Analytics 4 measurement ID', 'text', 'help' => 'Looks like G-XXXXXXXXXX.'],
        ],
        'About page' => [
            'about_vision' => ['Our vision', 'textarea', 'help' => 'One sentence, shown large. Wrap words in <em>…</em> to colour them purple.'],
            'about_mission' => ['Our mission', 'textarea', 'help' => 'One or two sentences shown next to the vision. Team members and testimonials have their own pages in the menu.'],
        ],
        'Homepage' => [
            'home_client_logos' => ['Client logos', 'gallery', 'help' => 'Upload transparent PNG logos, then drag to reorder or remove them. They scroll below the homepage articles.'],
        ],
        'Office areas' => [
            'home_zone_reception' => ['Reception photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_waiting_lounge' => ['Waiting area / lounge photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_workstations' => ['Workstations photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_manager_cabins' => ['Manager cabins photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_meeting_rooms' => ['Meeting rooms photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_conference_boardroom' => ['Conference / boardroom photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_cafeteria_pantry' => ['Cafeteria / pantry photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_breakout' => ['Breakout area photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_recreation' => ['Recreation / game area photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_training_multipurpose' => ['Training / multipurpose photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_display_experience' => ['Display / experience area photos', 'gallery', 'help' => $zonePhotoHelp],
            'home_zone_washrooms' => ['Washrooms photos', 'gallery', 'help' => $zonePhotoHelp],
        ],
        'Content' => [
            'disclaimer' => ['Portfolio disclaimer', 'textarea'],
        ],
    ];
    if (is_post()) {
        foreach ($fields as $group) foreach ($group as $k => $f) {
            $value = trim((string)($_POST[$k] ?? ''));
            if (($f[1] ?? '') === 'gallery') {
                $gallery = json_decode($value, true);
                $validImagePath = fn($path) => is_string($path) &&
                    (preg_match('~^/(?:uploads|images|media)/[A-Za-z0-9._/-]+$~', $path) ||
                    preg_match('~^/OneDrive_1_11-26-2026/(?:[A-Za-z0-9._-]|%20|/)+$~', $path)) &&
                    strpos($path, '..') === false;
                $gallery = is_array($gallery) ? array_values(array_filter($gallery, fn($item) =>
                    is_array($item) && isset($item[0], $item[1]) &&
                    $validImagePath($item[0]) && $validImagePath($item[1])
                )) : [];
                $value = json_encode($gallery, JSON_UNESCAPED_SLASHES);
            }
            q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $value]);
        }
        cache_purge_all(); flash('Settings saved.'); redirect('/admin/?r=settings');
    }
    ob_start(); ?>
<form method="post" class="settings">
  <?= csrf_field() ?>
<?php $zonePhotoDefaults = home_zone_photo_defaults(); ?>
<?php foreach ($fields as $g => $group): ?>
  <section class="card"><h2><?= e($g) ?></h2><div class="grid2">
<?php foreach ($group as $k => $f):
    $value = setting($k);
    if (($f[1] ?? '') === 'gallery' && $value === '') $value = json_encode($zonePhotoDefaults[$k] ?? [], JSON_UNESCAPED_SLASHES);
    echo field_html($k, $f, $value);
endforeach; ?>
  </div></section>
<?php endforeach ?>
  <button class="btn" type="submit">Save settings</button>
</form>
<?php
    admin_page('Settings', ob_get_clean(), 'settings');
}

/* =============================================================== account */
/** Every signed-in user: see their role, change their own password. */
function admin_account(array $me): void
{
    if (is_post()) {
        $u = row('SELECT pass_hash FROM users WHERE id = ?', [$me['id']]);
        if (!password_verify((string)($_POST['current'] ?? ''), $u['pass_hash'])) flash('Your current password is not correct.', 'err');
        elseif (strlen((string)($_POST['new'] ?? '')) < 10) flash('Use a new password of at least 10 characters.', 'err');
        else { q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash((string)$_POST['new'], PASSWORD_DEFAULT), $me['id']]); session_regenerate_id(true); flash('Password changed.'); }
        redirect('/admin/?r=account');
    }
    ob_start(); ?>
<div class="cols">
  <section class="card">
    <h2>Your account</h2>
    <dl class="kv">
      <dt>Name</dt><dd><?= e($me['name']) ?></dd>
      <dt>Email</dt><dd><?= e($me['email']) ?></dd>
      <dt>Role</dt><dd><span class="pill role"><?= e(role_label($me['role'])) ?></span><small class="help"><?= e(ROLE_HELP[$me['role']] ?? '') ?></small></dd>
    </dl>
    <?php if (can('users')): ?><p><a href="/admin/?r=users">Manage users and roles →</a></p><?php endif ?>
  </section>
  <section class="card">
    <h2>Change your password</h2>
    <form method="post"><?= csrf_field() ?>
      <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
      <label>New password (10+ characters)<input type="password" name="new" minlength="10" required autocomplete="new-password"></label>
      <button class="btn" type="submit">Change password</button>
    </form>
  </section>
</div>
<?php
    admin_page('Account', ob_get_clean(), 'account');
}

/* ================================================================= users */
/** Administrators only (checked by the router): add / remove users, change roles, test accounts. */
function admin_users(array $me): void
{
    if (is_post()) {
        $act = $_POST['act'] ?? '';
        $role = (string)($_POST['role'] ?? '');
        if ($act === 'add') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim((string)($_POST['name'] ?? '')) === '' || strlen((string)($_POST['password'] ?? '')) < 10) flash('Enter a name, a valid email and a password of 10+ characters.', 'err');
            elseif (!isset(ROLES[$role])) flash('Choose a role.', 'err');
            elseif (val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) flash('That email already has an account.', 'err');
            else { q('INSERT INTO users (name, email, pass_hash, role) VALUES (?, ?, ?, ?)', [trim($_POST['name']), $email, password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $role]); flash('User added as ' . role_label($role) . '. Share the password with them securely.'); }
        } elseif ($act === 'role') {
            $uid = (int)($_POST['uid'] ?? 0);
            if ($uid === (int)$me['id']) flash('You cannot change your own role. Ask another administrator.', 'err');
            elseif (!isset(ROLES[$role])) flash('Choose a role.', 'err');
            else { q('UPDATE users SET role = ? WHERE id = ?', [$role, $uid]); flash('Role changed to ' . role_label($role) . '. It applies on their next page load.'); }
        } elseif ($act === 'remove') {
            $uid = (int)($_POST['uid'] ?? 0);
            if ($uid === (int)$me['id']) flash('You cannot remove your own account.', 'err');
            else { q('DELETE FROM users WHERE id = ?', [$uid]); flash('User removed.'); }
        } elseif ($act === 'test_create') {
            $pass = 'Test-' . bin2hex(random_bytes(5));
            $_SESSION['test_accounts'] = test_accounts_create($pass);
            flash('Test accounts are ready - one per role. Password for all of them: ' . $pass . ' (shown only now; press the button again for a new one).', 'info');
        } elseif ($act === 'test_remove') {
            flash(test_accounts_remove() . ' test accounts removed.');
        }
        redirect('/admin/?r=users');
    }
    $users = rows('SELECT id, name, email, role, last_login FROM users ORDER BY FIELD(role, "administrator", "editor", "author", "contributor", "hr", "latepoint_agent", "subscriber"), id');
    $testCount = (int)val('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@' . TEST_DOMAIN]);
    $roleSelect = function (string $current, string $attrs = ''): string {
        $h = '<select name="role"' . $attrs . '>';
        foreach (ROLES as $k => $l) $h .= '<option value="' . e($k) . '"' . ($k === $current ? ' selected' : '') . '>' . e($l) . '</option>';
        return $h . '</select>';
    };
    $made = $_SESSION['test_accounts'] ?? null; unset($_SESSION['test_accounts']);
    ob_start(); ?>
<section class="card">
  <div class="card-h"><h2>Users</h2><span class="muted small"><?= count($users) ?> accounts</span></div>
  <table class="tbl users"><thead><tr><th>User</th><th>Role</th><th>Last sign-in</th><th></th></tr></thead><tbody>
<?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
    <tr>
      <td><?= e($u['name']) ?><?= str_ends_with($u['email'], '@' . TEST_DOMAIN) ? ' <span class="pill draft">Test</span>' : '' ?><small><?= e($u['email']) ?></small></td>
      <td><?php if ($self): ?><span class="pill role"><?= e(role_label($u['role'])) ?></span> <small>(you)</small><?php else: ?>
        <form method="post" class="inline-role"><?= csrf_field() ?><input type="hidden" name="act" value="role"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><?= $roleSelect((string)$u['role'], ' onchange="this.form.submit()" aria-label="Role for ' . e($u['email']) . '"') ?><noscript><button class="btn ghost small" type="submit">Save</button></noscript></form>
      <?php endif ?></td>
      <td class="muted small"><?= $u['last_login'] ? e(ago($u['last_login'])) : 'Never' ?></td>
      <td class="acts"><?php if (!$self): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="remove"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><button class="link danger" type="submit" data-confirm="Remove <?= e($u['email']) ?>?">Remove</button></form><?php endif ?></td>
    </tr>
<?php endforeach ?>
  </tbody></table>
</section>
<div class="cols">
  <section class="card">
    <h2>Add a user</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="add">
      <label>Name<input name="name" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Password (10+ characters)<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
      <label>Role<?= $roleSelect(DEFAULT_ROLE) ?></label>
      <button class="btn" type="submit">Add user</button>
    </form>
  </section>
  <section class="card">
    <h2>What each role can do</h2>
    <dl class="kv roles">
<?php foreach (array_reverse(ROLES, true) as $k => $l): ?>
      <dt><?= e($l) ?></dt><dd><?= e(ROLE_HELP[$k]) ?></dd>
<?php endforeach ?>
    </dl>
  </section>
</div>
<section class="card">
  <h2>Test accounts</h2>
  <p class="muted">One sign-in per role (<code>test.&lt;role&gt;@<?= e(TEST_DOMAIN) ?></code>) so you can sign in as each role and check what it can see. They share one password, generated each time you press the button. Remove them before going live.</p>
<?php if ($made): ?>
  <table class="tbl"><thead><tr><th>Role</th><th>Email</th></tr></thead><tbody>
<?php foreach ($made as $role => $email): ?>
    <tr><td><?= e(role_label($role)) ?></td><td><code><?= e($email) ?></code></td></tr>
<?php endforeach ?>
  </tbody></table>
<?php endif ?>
  <div class="row-btns">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="test_create"><button class="btn ghost" type="submit"><?= $testCount ? 'Reset test accounts (new password)' : 'Create test accounts' ?></button></form>
    <?php if ($testCount): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="test_remove"><button class="btn danger" type="submit" data-confirm="Remove all <?= $testCount ?> test accounts?">Remove test accounts</button></form><?php endif ?>
  </div>
</section>
<?php
    admin_page('Users', ob_get_clean(), 'users');
}

/* ================================================================ upload */
function admin_upload(): void
{
    if (!is_post() || empty($_FILES['file'])) json_out(['ok' => false, 'error' => 'No file.'], 400);
    [$url, $err] = save_image($_FILES['file']);
    $err ? json_out(['ok' => false, 'error' => $err], 422) : json_out(['ok' => true, 'url' => $url]);
}

/* ================================================================ upload diagnostics (admin only) */
function admin_upload_diag(): void
{
    require_cap('*');  // administrator only
    $dir = ROOT . '/uploads';
    $testDir = $dir . '/' . date('Y/m');
    $rows = [
        'PHP version'           => PHP_VERSION,
        'upload_max_filesize'   => ini_get('upload_max_filesize'),
        'post_max_size'         => ini_get('post_max_size'),
        'max_execution_time'    => ini_get('max_execution_time') . 's',
        'memory_limit'          => ini_get('memory_limit'),
        'uploads dir exists'    => is_dir($dir) ? 'yes' : 'NO — missing',
        'uploads dir writable'  => is_writable($dir) ? 'yes' : 'NO — fix chmod 755',
        'uploads subdir'        => $testDir,
        'subdir exists'         => is_dir($testDir) ? 'yes' : 'not yet (created on first upload)',
        'tmp_name writable'     => is_writable(sys_get_temp_dir()) ? 'yes' : 'NO',
        'ROOT'                  => ROOT,
    ];
    // try creating the subdir
    if (!is_dir($testDir)) {
        $made = @mkdir($testDir, 0755, true);
        $rows['mkdir test'] = $made ? 'OK — created ' . $testDir : 'FAILED — ' . (error_get_last()['message'] ?? 'unknown error');
    }
    admin_page('Upload Diagnostics', function () use ($rows) {
        echo '<div class="card" style="max-width:640px"><h2>Upload Diagnostics</h2><dl class="kv" style="margin-top:16px">';
        foreach ($rows as $k => $v) {
            $bad = strpos($v, 'NO') !== false || strpos($v, 'FAILED') !== false;
            echo '<dt>' . e($k) . '</dt><dd' . ($bad ? ' style="color:var(--red,#c00);font-weight:600"' : '') . '>' . e($v) . '</dd>';
        }
        echo '</dl></div>';
    });
}
