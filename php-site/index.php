<?php
/*
 * Morphora Spaces — front controller. Every request that is not a real file comes here (see .htaccess).
 *
 * URL rules (SEO): one canonical URL per page — lowercase, trailing slash, no index.html/index.php.
 * Anything else 301-redirects to it. Unknown paths are checked against the redirects table, then 404.
 */
require __DIR__ . '/app/bootstrap.php';

$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$qs = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
$isGet = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true);

/* ------------------------------------------------------ canonical URL */
if ($isGet && strpos($uri, '/api/') !== 0) {
    $clean = preg_replace('~/{2,}~', '/', $uri);
    $clean = preg_replace('~/index\.(html?|php)$~i', '/', $clean);
    if (!preg_match('~\.(xml|txt)$~i', $clean) && substr($clean, -1) !== '/') $clean .= '/';
    if (preg_match('~[A-Z]~', $clean)) $clean = strtolower($clean);
    if ($clean !== $uri) redirect($clean . $qs, 301);
}
$path = trim($uri, '/');

/* ------------------------------------------------------------ routes */
try {
    if ($path === '') route_home();
    elseif ($path === 'sitemap.xml') route_sitemap();
    elseif ($path === 'robots.txt') route_robots();
    elseif ($path === 'api/enquiry') api_enquiry();
    elseif ($path === 'api/apply') api_apply();
    elseif ($path === 'portfolio') route_portfolio();
    elseif (preg_match('~^portfolio/([a-z0-9-]+)$~', $path, $m)) route_project($m[1]);
    elseif ($path === 'resources') route_resources();
    elseif (preg_match('~^resources/([a-z0-9-]+)$~', $path, $m)) route_article($m[1]);
    elseif ($path === 'locations') route_locations();
    elseif (preg_match('~^office-interior-designer-in-([a-z0-9-]+)$~', $path, $m)) route_city($m[1]);
    elseif ($path === 'careers') route_careers();
    elseif (preg_match('~^careers/([a-z0-9-]+)$~', $path, $m)) route_job($m[1]);
    elseif (preg_match('~^(category|author|tag)/~', $path)) redirect('/resources/', 301);   // old WordPress archives
    else route_page($path);
} catch (PDOException $e) {
    error_log('Morphora DB error: ' . $e->getMessage());
    http_response_code(503);
    header('Retry-After: 300');
    echo cfg('debug') ? e($e->getMessage()) : 'The site is temporarily unavailable. Please try again in a few minutes, or call +91 77191 55155.';
}

/* ================================================================ helpers */

/** Logged-in admins may preview drafts (?preview=1). Never cached. */
function can_preview(): bool
{
    if (empty($_GET['preview']) || empty($_COOKIE['ms_sess'])) return false;
    session_boot();
    return !empty($_SESSION['uid']);
}

function page_row(string $slug): ?array { return row('SELECT * FROM pages WHERE slug = ?', [$slug]); }

/** Meta for template pages, from the pages table (SEO text editable in admin → Pages). */
function meta_from_page(?array $pg, string $path, array $extra = []): array
{
    return array_merge([
        'title' => $pg['seo_title'] ?? setting('site_name', 'Morphora Spaces'),
        'desc' => $pg['seo_desc'] ?? '',
        'path' => $path,
        'image' => $pg['og_image'] ?? '',
        'robots' => !empty($pg['noindex']) ? 'noindex,follow' : null,
        'nav' => $pg['nav'] ?? '',
        'foot_label' => $pg['foot_label'] ?? '',
        'foot_title' => $pg['foot_title'] ?? '',
    ], array_filter($extra, fn($v) => $v !== null));
}

function not_found(): void
{
    http_response_code(404);
    page_out('404', [], ['title' => 'Page not found | ' . setting('site_name', 'Morphora Spaces'), 'desc' => 'This page does not exist.', 'path' => null, 'robots' => 'noindex,follow']);
    exit;
}

/* ================================================================= pages */

function route_home(): void
{
    $pg = page_row('');
    cache_public();
    page_out('home', [], meta_from_page($pg, '/', ['scripts' => (string)@file_get_contents(APP . '/views/generated/home-script.html')]));
}

/** Fixed pages stored in the database: about, services, contact, legal … (and redirects / 404 for unknown paths). */
function route_page(string $slug): void
{
    $pg = page_row($slug);
    if (!$pg || $pg['template'] !== 'page') {
        $r = row('SELECT * FROM redirects WHERE from_path = ?', ['/' . $slug . '/']);
        if ($r) { q('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$r['id']]); redirect($r['to_path'], (int)$r['code'] === 302 ? 302 : 301); }
        not_found();
    }
    cache_public();
    $names = ['about' => 'About', 'design-and-build' => 'Design & Build', 'real-estate' => 'Real Estate Advisory', 'pmc' => 'Project Management', 'contact' => 'Contact', 'privacy-policy' => 'Privacy Policy', 'terms-and-conditions' => 'Terms and Conditions'];
    $crumbs = [[$names[$slug] ?? ucwords(str_replace('-', ' ', $slug)), null]];
    $schema = [];
    if (in_array($slug, ['design-and-build', 'real-estate', 'pmc'], true)) {
        $schema[] = ['@type' => 'Service', 'name' => $names[$slug], 'serviceType' => $names[$slug], 'provider' => ['@id' => org_id()], 'areaServed' => ['@type' => 'Country', 'name' => 'India'], 'url' => abs_url(path_for($slug))];
    }
    if ($slug === 'contact') $schema[] = ['@type' => 'ContactPage', 'url' => abs_url('/contact/'), 'about' => ['@id' => org_id()]];
    if ($slug === 'about') $schema[] = ['@type' => 'AboutPage', 'url' => abs_url('/about/'), 'about' => ['@id' => org_id()]];
    page_out('page', ['page' => $pg], meta_from_page($pg, path_for($slug), ['crumbs' => $crumbs, 'schema' => $schema]));
}

function route_portfolio(): void
{
    cache_public();
    $pg = page_row('portfolio');
    page_out('portfolio', ['projects' => projects_list()], meta_from_page($pg, '/portfolio/', ['crumbs' => [['Portfolio', null]]]));
}

function route_project(string $slug): void
{
    $p = row('SELECT * FROM projects WHERE slug = ?', [$slug]);
    if (!$p || ($p['status'] !== 'published' && !can_preview())) not_found();
    if ($p['status'] === 'published') cache_public();
    $gallery = json_decode((string)$p['gallery'], true) ?: [];
    $others = array_slice(array_values(array_filter(projects_list(), fn($x) => $x['id'] !== $p['id'])), 0, 3);
    $title = $p['seo_title'] ?: "{$p['name']}, {$p['city']} — Office Interior Project | " . setting('site_name', 'Morphora Spaces');
    $desc = $p['seo_desc'] ?: "{$p['name']} office in {$p['city']}: a {$p['size']} office interior project. {$p['delivery']}.";
    $images = array_map(fn($g) => abs_url($g[1]), array_slice($gallery, 0, 10));
    page_out('project', ['p' => $p, 'gallery' => $gallery, 'others' => $others], [
        'title' => $title, 'desc' => $desc, 'path' => "/portfolio/{$slug}/", 'image' => $p['cover'], 'nav' => 'portfolio',
        'crumbs' => [['Portfolio', '/portfolio/'], [$p['name'], null]],
        'schema' => [['@type' => 'CreativeWork', 'name' => "{$p['name']} office, {$p['city']}", 'about' => 'Office interior design and build', 'creator' => ['@id' => org_id()], 'locationCreated' => ['@type' => 'Place', 'name' => $p['city']], 'image' => array_merge([abs_url($p['cover'])], $images), 'url' => abs_url("/portfolio/{$slug}/")]],
        'robots' => $p['status'] !== 'published' ? 'noindex,nofollow' : null,
    ]);
}

function route_resources(): void
{
    cache_public();
    $pg = page_row('resources');
    $posts = rows('SELECT * FROM posts WHERE status="published" AND published_at <= CURDATE() ORDER BY published_at DESC, id DESC');
    page_out('resources', ['posts' => $posts], meta_from_page($pg, '/resources/', [
        'crumbs' => [['Resources', null]],
        'schema' => [['@type' => 'Blog', 'name' => 'Morphora Spaces — Resources', 'url' => abs_url('/resources/'), 'publisher' => ['@id' => org_id()]]],
    ]));
}

function route_article(string $slug): void
{
    $p = row('SELECT * FROM posts WHERE slug = ?', [$slug]);
    $live = $p && $p['status'] === 'published' && $p['published_at'] <= date('Y-m-d');
    if (!$p || (!$live && !can_preview())) {
        $r = row('SELECT * FROM redirects WHERE from_path = ?', ['/resources/' . $slug . '/']);
        if ($r) { q('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$r['id']]); redirect($r['to_path'], 301); }
        not_found();
    }
    if ($live) cache_public();
    $related = latest_posts(3, (int)$p['id'], $p['category']);
    $img = $p['cover'] ?: post_thumb($p);
    page_out('article', ['p' => $p, 'related' => $related], [
        'title' => $p['seo_title'] ?: seo_title($p['title']), 'desc' => $p['seo_desc'] ?: ($p['excerpt'] ?: str_limit(plain((string)$p['body']), 160)),
        'path' => "/resources/{$slug}/", 'image' => $img, 'type' => 'article', 'nav' => 'resources',
        'published' => $p['published_at'], 'modified' => $p['updated_at'],
        'crumbs' => [['Resources', '/resources/'], [$p['title'], null]],
        'schema' => [[
            '@type' => 'BlogPosting', 'headline' => str_limit($p['title'], 110), 'description' => plain((string)($p['excerpt'] ?? '')),
            'image' => [abs_url($img)], 'datePublished' => date('c', strtotime($p['published_at'])), 'dateModified' => date('c', strtotime($p['updated_at'])),
            'author' => ['@type' => 'Organization', 'name' => $p['author'], 'url' => abs_url('/about/')], 'publisher' => ['@id' => org_id()],
            'mainEntityOfPage' => abs_url("/resources/{$slug}/"), 'articleSection' => $p['category'], 'wordCount' => str_word_count(plain((string)$p['body'])), 'inLanguage' => 'en-IN',
        ]],
        'robots' => $live ? null : 'noindex,nofollow',
    ]);
}

function route_locations(): void
{
    cache_public();
    $pg = page_row('locations');
    $cities = rows('SELECT slug, name, top FROM cities WHERE status="published" ORDER BY name');
    page_out('locations', ['cities' => $cities], meta_from_page($pg, '/locations/', ['crumbs' => [['Locations', null]]]));
}

function route_city(string $slug): void
{
    $c = row('SELECT * FROM cities WHERE slug = ?', [$slug]);
    if (!$c || ($c['status'] !== 'published' && !can_preview())) {
        $r = row('SELECT * FROM redirects WHERE from_path = ?', ['/office-interior-designer-in-' . $slug . '/']);
        if ($r) { q('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$r['id']]); redirect($r['to_path'], 301); }
        not_found();
    }
    if ($c['status'] === 'published') cache_public();
    $n = $c['name'];
    page_out('city', ['c' => $c], [
        'title' => $c['seo_title'] ?: "Office Interior Designer in {$n} — Design & Build in 60 Days | " . setting('site_name', 'Morphora Spaces'),
        'desc' => $c['seo_desc'] ?: "Looking for an office interior designer in {$n}? Morphora Spaces designs and builds complete offices in {$n} in 60 days — layout, 3D design, civil work and handover.",
        'path' => "/office-interior-designer-in-{$slug}/", 'image' => $c['hero'] ?: '/images/ADI8415-scaled.jpg',
        'crumbs' => [['Locations', '/locations/'], [$n, null]],
        'foot_label' => "( Office interiors in {$n} )",
        'schema' => [['@type' => 'Service', 'name' => "Office interior design in {$n}", 'serviceType' => 'Office interior design and build', 'provider' => ['@id' => org_id()], 'areaServed' => ['@type' => 'City', 'name' => $n], 'url' => abs_url("/office-interior-designer-in-{$slug}/")]],
        'robots' => $c['status'] !== 'published' ? 'noindex,nofollow' : null,
    ]);
}

function route_careers(): void
{
    cache_public(3600);
    $pg = page_row('careers');
    $jobs = rows('SELECT * FROM jobs WHERE status="published" AND (valid_through IS NULL OR valid_through >= CURDATE()) ORDER BY created_at DESC');
    page_out('careers', ['page' => $pg, 'jobs' => $jobs], meta_from_page($pg, '/careers/', ['crumbs' => [['Careers', null]]]));
}

function route_job(string $slug): void
{
    $j = row('SELECT * FROM jobs WHERE slug = ?', [$slug]);
    if (!$j || ($j['status'] === 'draft' && !can_preview())) not_found();
    $open = $j['status'] === 'published' && (!$j['valid_through'] || $j['valid_through'] >= date('Y-m-d'));
    $schema = [];
    if ($open) {
        cache_public(3600);
        $schema[] = [
            '@type' => 'JobPosting', 'title' => $j['title'], 'description' => (string)$j['description'], 'datePosted' => date('Y-m-d', strtotime($j['created_at'])),
            'validThrough' => $j['valid_through'] ? date('c', strtotime($j['valid_through'] . ' 23:59:59')) : date('c', strtotime($j['created_at'] . ' +90 days')),
            'employmentType' => strtoupper(str_replace(['-', ' '], '_', $j['type'])), 'hiringOrganization' => ['@id' => org_id()],
            'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => explode(',', $j['location'])[0], 'addressRegion' => 'Maharashtra', 'addressCountry' => 'IN']],
            'directApply' => true,
        ];
    }
    page_out('job', ['j' => $j, 'open' => $open], [
        'title' => seo_title("{$j['title']} — Careers"), 'desc' => "{$j['title']} job at Morphora Spaces, {$j['location']}. " . str_limit(plain((string)$j['description']), 120),
        'path' => "/careers/{$slug}/", 'crumbs' => [['Careers', '/careers/'], [$j['title'], null]], 'schema' => $schema,
        'robots' => $open ? null : 'noindex,follow',
    ]);
}

/* ============================================================ sitemap / robots */

function route_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    header('X-LiteSpeed-Cache-Control: public,max-age=3600');
    $u = [];
    $add = function (string $path, ?string $mod = null, string $freq = 'monthly', string $prio = '0.6', ?string $img = null) use (&$u) { $u[] = [$path, $mod, $freq, $prio, $img]; };
    $pages = array_column(rows('SELECT slug, updated_at, template, noindex FROM pages'), null, 'slug');
    $pmod = fn($s) => isset($pages[$s]) ? $pages[$s]['updated_at'] : null;
    $add('/', $pmod(''), 'weekly', '1.0');
    foreach ($pages as $s => $pg) if ($pg['template'] === 'page' && !$pg['noindex'] && $s !== '') $add(path_for($s), $pg['updated_at'], in_array($s, ['privacy-policy', 'terms-and-conditions'], true) ? 'yearly' : 'monthly', in_array($s, ['design-and-build', 'real-estate', 'pmc'], true) ? '0.9' : '0.7');
    $latestPost = val('SELECT MAX(updated_at) FROM posts WHERE status="published"');
    $add('/portfolio/', val('SELECT MAX(updated_at) FROM projects'), 'monthly', '0.8');
    foreach (projects_list() as $p) $add("/portfolio/{$p['slug']}/", $p['updated_at'], 'yearly', '0.6', $p['cover']);
    $add('/resources/', $latestPost, 'weekly', '0.8');
    foreach (rows('SELECT slug, updated_at, cover, thumb FROM posts WHERE status="published" AND published_at <= CURDATE() ORDER BY published_at DESC') as $p) $add("/resources/{$p['slug']}/", $p['updated_at'], 'monthly', '0.7', $p['cover'] ?: $p['thumb']);
    $add('/locations/', val('SELECT MAX(updated_at) FROM cities'), 'monthly', '0.7');
    foreach (rows('SELECT slug, updated_at, top FROM cities WHERE status="published" ORDER BY name') as $c) $add("/office-interior-designer-in-{$c['slug']}/", $c['updated_at'], 'monthly', $c['top'] ? '0.8' : '0.5');
    $add('/careers/', val('SELECT MAX(updated_at) FROM jobs'), 'weekly', '0.5');
    foreach (rows('SELECT slug, updated_at FROM jobs WHERE status="published" AND (valid_through IS NULL OR valid_through >= CURDATE())') as $j) $add("/careers/{$j['slug']}/", $j['updated_at'], 'weekly', '0.4');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    foreach ($u as [$path, $mod, $freq, $prio, $img]) {
        echo '  <url><loc>' . e(abs_url($path)) . '</loc>' . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '') . "<changefreq>{$freq}</changefreq><priority>{$prio}</priority>" .
            ($img ? '<image:image><image:loc>' . e(abs_url($img)) . '</image:loc></image:image>' : '') . "</url>\n";
    }
    echo "</urlset>\n";
}

function route_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nDisallow: /install/\nDisallow: /*?preview=\n\nSitemap: " . abs_url('/sitemap.xml') . "\n";
}

/* ======================================================================= API */

/** Contact form + chat assistant → saved to `enquiries`, team notified by email. */
function api_enquiry(): void
{
    if (!is_post()) json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    $in = fn($k, $n = 190) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);
    // spam guards: hidden honeypot field, and at most 5 enquiries per IP per hour
    if ($in('website') !== '') json_out(['ok' => true]);
    $ip = client_ip();
    if ((int)val('SELECT COUNT(*) FROM enquiries WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$ip]) >= 5) {
        json_out(['ok' => false, 'error' => 'Too many enquiries from your connection. Please call us on ' . setting('phone', '+91 77191 55155') . '.'], 429);
    }
    $d = [
        'source' => in_array($in('source', 10), ['form', 'chat'], true) ? $in('source', 10) : 'form',
        'name' => $in('name', 150), 'company' => $in('company', 150), 'phone' => $in('phone', 40), 'email' => $in('email'),
        'city' => $in('city', 100), 'size' => $in('size', 60), 'service' => $in('service', 100), 'message' => $in('msg', 4000),
        'page' => $in('page', 255), 'referrer' => $in('ref', 255), 'utm' => $in('utm', 255),
    ];
    if ($d['name'] === '') json_out(['ok' => false, 'error' => 'Please enter your name.'], 422);
    if (strlen(preg_replace('/\D/', '', $d['phone'])) < 10) json_out(['ok' => false, 'error' => 'Please enter a valid phone number.'], 422);
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'error' => 'Please check your email address.'], 422);
    $spam = preg_match('~https?://|\[url=~i', $d['name'] . $d['company']) ? 'spam' : 'new';
    q('INSERT INTO enquiries (source, name, company, phone, email, city, size, service, message, page, referrer, utm, status, ip, user_agent) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $d['source'], $d['name'], $d['company'], $d['phone'], $d['email'], $d['city'], $d['size'], $d['service'], $d['message'], $d['page'], $d['referrer'], $d['utm'], $spam, $ip, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    $id = (int)db()->lastInsertId();
    if ($spam === 'new') notify_enquiry($d, $id);
    json_out(['ok' => true]);
}

/** Job application with CV upload → `applications`; CV kept private in app/storage/cv. */
function api_apply(): void
{
    if (!is_post()) json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
    $in = fn($k, $n = 190) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);
    $back = fn(string $msg, bool $ok = false) => (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json')
        ? json_out(['ok' => $ok, 'error' => $ok ? null : $msg, 'message' => $msg], $ok ? 200 : 422)
        : redirect(($in('back', 255) ?: '/careers/') . '?' . ($ok ? 'applied=1' : 'error=' . rawurlencode($msg)) . '#apply', 303);
    if ($in('website') !== '') $back('Thank you!', true);
    $ip = client_ip();
    if ((int)val('SELECT COUNT(*) FROM applications WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$ip]) >= 5) $back('Too many applications from your connection. Please email your CV instead.');
    $name = $in('name', 150); $email = $in('email'); $phone = $in('phone', 40);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $back('Please enter your name and a valid email address.');
    $job = row('SELECT id, title FROM jobs WHERE id = ? AND status = "published"', [(int)($_POST['job_id'] ?? 0)]);
    [$cv, $err] = !empty($_FILES['cv']['name']) ? save_cv($_FILES['cv']) : [null, 'Please attach your CV (PDF or Word).'];
    if ($err) $back($err);
    q('INSERT INTO applications (job_id, job_title, name, email, phone, portfolio, message, cv_file, cv_name, ip) VALUES (?,?,?,?,?,?,?,?,?,?)', [
        $job['id'] ?? null, $job['title'] ?? 'General application', $name, $email, $phone, $in('portfolio', 255), $in('message', 4000), $cv, mb_substr(basename((string)$_FILES['cv']['name']), 0, 255), $ip,
    ]);
    $id = (int)db()->lastInsertId();
    send_mail(setting('notify_email', setting('email')), 'New job application: ' . $name . ' — ' . ($job['title'] ?? 'General'),
        "New application from the careers page.\n\nName:      {$name}\nEmail:     {$email}\nPhone:     {$phone}\nRole:      " . ($job['title'] ?? 'General application') . "\nPortfolio: " . $in('portfolio', 255) . "\n\n" . $in('message', 4000) . "\n\nCV and details: " . abs_url('/admin/?r=applications&id=' . $id) . "\n", $email);
    $back('Thank you! We have received your application and will be in touch if there is a fit.', true);
}
