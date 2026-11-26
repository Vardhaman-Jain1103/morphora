<?php
/*
 * Page sections shared by the public views. They produce the markup
 * that assets/site.css and assets/site.js expect.
 *
 * Page bodies stored in the database can include live sections as shortcodes:
 *   <!--sc:projects {"label":"( Our work )","h2":"Recent <em>projects.</em>","slugs":["codal-ahmedabad"],"three":false}-->
 *   <!--sc:posts {"label":"( Keep reading )","limit":3}-->
 *   <!--sc:faq {"label":"( FAQs )","items":[["Question","Answer"],…]}-->
 *   <!--sc:nums {}-->   <!--sc:testimonials {}-->   <!--sc:map {"label":"( Pan-India )"}-->
 * Legacy <!--sc:process {...}--> markers render testimonials.
 *   <!--sc:founder {}-->  <!--sc:vision {}-->  <!--sc:team {}-->  <!--sc:testimonials {}-->   (About page; optional "label" / "h2")
 */

/* ------------------------------------------------------------------ data */
function latest_posts(int $n = 3, ?int $exceptId = null, ?string $preferCat = null): array
{
    if ($preferCat !== null) {
        $same = rows('SELECT * FROM posts WHERE status="published" AND published_at <= CURDATE() AND category = ? AND id <> ? ORDER BY published_at DESC LIMIT ' . $n, [$preferCat, (int)$exceptId]);
        if (count($same) >= $n) return $same;
        $ids = array_merge([(int)$exceptId], array_column($same, 'id'));
        $more = rows('SELECT * FROM posts WHERE status="published" AND published_at <= CURDATE() AND id NOT IN (' . implode(',', array_map('intval', $ids)) . ') ORDER BY published_at DESC LIMIT ' . ($n - count($same)));
        return array_merge($same, $more);
    }
    return rows('SELECT * FROM posts WHERE status="published" AND published_at <= CURDATE() AND id <> ? ORDER BY published_at DESC LIMIT ' . $n, [(int)$exceptId]);
}
function projects_list(bool $featuredOnly = false): array
{
    return rows('SELECT * FROM projects WHERE status="published"' . ($featuredOnly ? ' AND featured=1' : '') . ' ORDER BY sort_order, id');
}
function cities_count(): int { static $n; return $n ??= (int)val('SELECT COUNT(*) FROM cities WHERE status="published"'); }

/* ----------------------------------------------------------- building blocks */
function crumbs_html(array $items): string
{
    $h = '<nav class="crumb mono" aria-label="Breadcrumb"><a href="/">Home</a>';
    foreach ($items as [$t, $p]) $h .= ' <span>/</span> ' . ($p !== null ? '<a href="' . e($p) . '">' . e($t) . '</a>' : '<span aria-current="page">' . e($t) . '</span>');
    return $h . '</nav>';
}

/** Page hero. $o: crumb, h1 (HTML), lead (HTML), btns(false = none), cta, btn2 [label, href], img, imgAlt */
function phero(array $o): string
{
    $act = ($o['btns'] ?? true) === false ? '' :
        '<div class="btns"><a class="btn" href="#contact">' . e($o['cta'] ?? 'Get a free consultation') . '</a>' .
        (!empty($o['btn2']) ? '<a class="btn ghost" href="' . e($o['btn2'][1]) . '">' . e($o['btn2'][0]) . '</a>' : '') . '</div>';
    $h = "<section class=\"phero\">\n  <div class=\"wrap\">\n    " . crumbs_html($o['crumb'] ?? []) . "\n    <h1 class=\"disp\">{$o['h1']}</h1>\n    <div class=\"phero-meta\"><p>{$o['lead']}</p>{$act}</div>\n  </div>\n</section>";
    if (!empty($o['img'])) $h .= "\n<section class=\"pimg\"><div class=\"wrap\"><div><img src=\"" . e($o['img']) . '" alt="' . e($o['imgAlt'] ?? '') . '" fetchpriority="high"></div></div></section>';
    return $h;
}

function split_sec(string $label, string $h2, string $inner, bool $alt = false): string
{
    return '<section class="sec' . ($alt ? ' alt' : '') . "\">\n  <div class=\"wrap split\">\n    <div><span class=\"mono lbl\">{$label}</span><h2 class=\"disp\">{$h2}</h2></div>\n    <div class=\"prose\">{$inner}</div>\n  </div>\n</section>";
}

function why_sec(string $label, string $h2, array $items, bool $alt = false): string
{
    $li = '';
    foreach ($items as $i => [$t, $p]) $li .= '      <li class="rv"><span class="mono">' . sprintf('%02d', $i + 1) . "</span><div><h3>{$t}</h3><p>{$p}</p></div></li>\n";
    return '<section class="why' . ($alt ? ' sec alt' : '') . "\">\n  <div class=\"wrap why-grid\">\n    <div><span class=\"mono\" style=\"color:var(--plum)\">{$label}</span><h2 class=\"disp\">{$h2}</h2></div>\n    <ol>\n{$li}    </ol>\n  </div>\n</section>";
}

function advantages(): array
{
    return [
        ['Clear process, full transparency', 'From start to finish you always know where your project stands, with live progress updates on a simple online dashboard.'],
        ['Design that works', 'Offices that look good and are easy to work in — planned around your workflow, productivity and company culture.'],
        ['One expert team', 'Architects, interior designers and workspace planners work together, so your project fits your industry and needs.'],
        ['Local and relevant', 'We use local materials and regional design touches, so the office feels right for your people and your visitors.'],
        ['Support after handover', 'You get user guides, a maintenance kit and space-planning tips, so your team settles in quickly and confidently.'],
    ];
}

function default_faq(?string $city = null): array
{
    return [
        ['What makes Morphora Spaces different from other office interior design companies?', 'We go beyond looks. Our team combines creative interior design with smart space planning to deliver offices that work well, match your brand and are ready for the future — all within 60 days.'],
        ['How long does it take to design and build a modern office with Morphora?', 'We deliver complete office projects in 60 days. Design, buying materials and construction are all planned together, so there is very little downtime for your business.'],
        $city ? ["Do you work in {$city}?", "Yes. We work with clients in {$city} and across India. Our team manages projects on-site or remotely, depending on the location and size."]
              : ['Do you offer office interior design services outside Mumbai or Thane?', 'Yes. We work with clients across Mumbai, Thane, Navi Mumbai and other major cities in India. Our team manages projects on-site or remotely, depending on the location and size.'],
        ['What is included in your Design &amp; Build service for office interiors?', 'Everything from the first idea, 3D views and layout planning to electrical and civil work, furniture buying and final execution — one team handles it all for you.'],
        ['Can you help us find the right office space before designing it?', 'Yes. Our Real Estate Advisory team helps you find, check and finalise the right office location, so the space suits your business before we start designing.'],
        ['What kind of businesses do you work with for office interiors?', 'Corporate offices, startups, co-working spaces and professional firms. Every project is planned around the company’s goals, work culture and industry needs.'],
    ];
}
function home_faq(): array { return default_faq(); }

/* ------------------------------------------------------------ cards */
function wcard(array $p): string
{
    return '<a class="wcard rv" href="/portfolio/' . e($p['slug']) . '/" data-cur="VIEW"><div class="ph" data-tilt><img src="' . e($p['cover']) . '" alt="' . e($p['name'] . ' office, ' . $p['city']) . '" loading="lazy"></div><div class="meta"><div><h3>' . e($p['name']) . '</h3><span class="by">' . e($p['delivery']) . '</span></div><span class="mono">' . e($p['city']) . '<br>' . e($p['size']) . '</span></div></a>';
}

function home_project_cards(): string
{
    $h = '';
    foreach (projects_list(true) as $p) {
        $by = $p['delivery'] !== '' ? '<span class="by">' . e($p['delivery']) . '</span>' : '';
        $h .= '      <a class="hz-card" href="/portfolio/' . e($p['slug']) . '/" data-cur="VIEW"><div class="ph"><img src="' . e($p['cover']) . '" alt="' . e($p['name'] . ' office interior, ' . $p['city']) . '" loading="lazy"></div><div class="meta"><div><h3>' . e($p['name']) . '</h3>' . $by . '</div><span class="mono">' . e($p['city']) . '<br>' . e($p['size']) . "</span></div></a>\n";
    }
    return $h;
}

function post_thumb(array $p): string { return $p['thumb'] ?: ($p['cover'] ?: '/images/ADI8340-scaled.jpg'); }

function post_card(array $p, bool $all = false): string
{
    return '<a class="post rv" href="/resources/' . e($p['slug']) . '/"' . ($all ? ' data-cat="' . e($p['category']) . '"' : '') . '><div class="ph"><img src="' . e(post_thumb($p)) . '" alt="" loading="lazy"></div><span class="mono">' . e($p['category']) . '</span><h3>' . e($p['title']) . '</h3><span class="date">' . e(fmt_date($p['published_at'])) . '</span></a>';
}
function post_cards(array $list, bool $all = false): string { return implode("\n", array_map(fn($p) => '      ' . post_card($p, $all), $list)); }

/* -------------------------------------------------------- shortcode sections */
function sc_projects(array $o): string
{
    $all = projects_list();
    $list = [];
    if (!empty($o['slugs'])) { $by = array_column($all, null, 'slug'); foreach ($o['slugs'] as $s) if (isset($by[$s])) $list[] = $by[$s]; }
    if (!$list) $list = array_slice($all, 0, (int)($o['limit'] ?? (!empty($o['three']) ? 3 : 4)));
    if (!$list) return '';
    $cards = implode("\n", array_map(fn($p) => '      ' . wcard($p), $list));
    return "<section class=\"sec\">\n  <div class=\"wrap\">\n    <div class=\"sec-head\"><div><span class=\"mono lbl\">{$o['label']}</span><h2 class=\"disp\">{$o['h2']}</h2></div><a class=\"btn ghost\" href=\"/portfolio/\">All projects</a></div>\n    <div class=\"work-grid" . (!empty($o['three']) ? ' three' : '') . "\">\n{$cards}\n    </div>\n  </div>\n</section>";
}

function sc_posts(array $o, ?array $list = null): string
{
    $list ??= latest_posts((int)($o['limit'] ?? 3));
    if (!$list) return '';
    return "<section class=\"blog\">\n  <div class=\"wrap\">\n    <div class=\"blog-head\"><div><span class=\"mono\" style=\"color:var(--plum)\">{$o['label']}</span><h2 class=\"disp\">Ideas for <em>better offices.</em></h2></div><a class=\"mono\" href=\"/resources/\">All articles ↗</a></div>\n    <div class=\"blog-grid\">\n" . post_cards($list) . "\n    </div>\n  </div>\n</section>";
}

function sc_faq(array $o): string
{
    $items = $o['items'] ?? default_faq();
    if (!$items) return '';
    $d = '';
    foreach ($items as [$q, $a]) $d .= "      <details><summary>{$q}</summary><p>{$a}</p></details>\n";
    // FAQ rich results
    schema_add(['@type' => 'FAQPage', 'mainEntity' => array_map(fn($i) => ['@type' => 'Question', 'name' => plain($i[0]), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => plain($i[1])]], $items)]);
    return "<section class=\"faq\">\n  <div class=\"wrap faq-grid\">\n    <div><span class=\"mono\" style=\"color:var(--plum)\">{$o['label']}</span><h2 class=\"disp\">Common <em>questions.</em></h2><p>Still have a question? <a href=\"#contact\">Talk to our team</a> and we will help you out.</p></div>\n    <div>\n{$d}    </div>\n  </div>\n</section>";
}

function sc_nums(array $o = []): string
{
    return '<section class="nums">
  <div class="wrap nums-grid">
    <div class="n rv"><b><span data-c="60">0</span><sup>d</sup></b><span class="mono">Days from design to handover</span></div>
    <div class="n rv"><b><span data-c="10">0</span><sup>+</sup></b><span class="mono">Cities we work in</span></div>
    <div class="n rv"><b><span data-c="150">0</span><sup>+</sup></b><span class="mono">Offices designed &amp; built</span></div>
    <div class="n rv"><b><span data-c="100">0</span><sup>%</sup></b><span class="mono">Clients who come back for their next office</span></div>
  </div>
</section>';
}

function sc_map(array $o): string
{
    $f = APP . '/views/partials/india-map.html';
    if (!is_file($f)) return '';
    return strtr((string)file_get_contents($f), ['{{label}}' => $o['label'] ?? '( Pan-India )', '{{count}}' => (string)cities_count()]);
}

/* ------------------------------------------------------------ about page */
/** Signed-in admins viewing ?preview=1 also see draft team members and testimonials. */
function show_drafts(): bool { return function_exists('can_preview') && can_preview(); }

function team_list(): array
{
    try { return rows('SELECT * FROM team' . (show_drafts() ? '' : ' WHERE status = "published"') . ' ORDER BY sort_order, id'); }
    catch (Throwable $e) { return []; }
}
function testimonials_list(): array
{
    try { return rows('SELECT * FROM testimonials' . (show_drafts() ? '' : ' WHERE status = "published"') . ' ORDER BY sort_order, id'); }
    catch (Throwable $e) { return []; }
}

/** Plain text with blank lines → escaped <p> paragraphs. */
function paras(string $text): string
{
    $out = '';
    foreach (preg_split('/\R\s*\R/', trim($text)) as $p) if (trim($p) !== '') $out .= '<p>' . nl2br(e(trim($p)), false) . '</p>';
    return $out;
}
function initials(string $name): string
{
    $w = preg_split('/\s+/', trim($name)) ?: [];
    return mb_strtoupper(mb_substr($w[0] ?? '', 0, 1) . (count($w) > 1 ? mb_substr(end($w), 0, 1) : ''));
}
/** Settings that may contain <em> (to colour words purple) and line breaks; everything else is escaped. */
function rich_setting(string $k, string $default): string { return strip_tags(setting($k, $default), '<em><strong><br>'); }

function sc_founder(array $o): string
{
    $m = null;
    foreach (team_list() as $t) if (trim((string)$t['message']) !== '') { $m = $t; break; }
    if (!$m) return '';
    $ph = $m['photo'] !== ''
        ? '<img src="' . e($m['photo']) . '" alt="' . e($m['name'] . ', ' . $m['role']) . '" loading="lazy">'
        : '<span class="ini" aria-hidden="true">' . e(initials($m['name'])) . '</span>';
    return "<section class=\"sec alt founder\" id=\"founder\">\n  <div class=\"wrap founder-grid\">\n    <figure class=\"founder-ph rv\">{$ph}</figure>\n    <div class=\"founder-txt\">\n      <span class=\"mono lbl\">" . ($o['label'] ?? '( Founder’s message )') . "</span>\n      <h2 class=\"disp\">" . ($o['h2'] ?? 'A note from <em>our founder.</em>') . "</h2>\n      <blockquote class=\"founder-msg\">" . paras((string)$m['message']) . "</blockquote>\n      <p class=\"founder-sign\"><b>" . e($m['name']) . '</b><span class="mono">' . e($m['role']) . "</span></p>\n    </div>\n  </div>\n</section>";
}

function sc_vision(array $o): string
{
    $vision = rich_setting('about_vision', '');
    $mission = rich_setting('about_mission', '');
    if ($vision === '' && $mission === '') return '';
    $side = $mission !== '' ? "\n    <div class=\"vm-side rv\"><span class=\"mono\">Our mission</span><p>{$mission}</p></div>" : '';
    return "<section class=\"sec vm\" id=\"vision\">\n  <div class=\"wrap vm-grid\">\n    <div><span class=\"mono lbl\">" . ($o['label'] ?? '( Vision &amp; mission )') . '</span>' . ($vision !== '' ? "<p class=\"vm-statement disp\"><span class=\"mono\">Our vision</span>{$vision}</p>" : '') . "</div>{$side}\n  </div>\n</section>";
}

function sc_team(array $o): string
{
    $list = team_list();
    if (!$list) return '';
    $cards = '';
    foreach ($list as $t) {
        $ph = $t['photo'] !== '' ? '<img src="' . e($t['photo']) . '" alt="' . e($t['name'] . ', ' . $t['role']) . '" loading="lazy">' : '<span class="ini" aria-hidden="true">' . e(initials($t['name'])) . '</span>';
        $cards .= '      <article class="tm rv"><div class="ph">' . $ph . '</div><h3>' . e($t['name']) . '</h3><span class="mono">' . e($t['role']) . '</span>'
            . (trim((string)$t['bio']) !== '' ? '<p>' . e($t['bio']) . '</p>' : '')
            . ($t['linkedin'] !== '' ? '<a class="tm-in" href="' . e($t['linkedin']) . '" target="_blank" rel="noopener">LinkedIn ↗</a>' : '') . "</article>\n";
        if ($t['status'] === 'published') {
            $person = ['@type' => 'Person', 'name' => $t['name'], 'jobTitle' => $t['role'], 'worksFor' => ['@id' => org_id()]];
            if ($t['photo'] !== '') $person['image'] = abs_url($t['photo']);
            if ($t['linkedin'] !== '') $person['sameAs'] = [$t['linkedin']];
            schema_add($person);
        }
    }
    return "<section class=\"sec team\" id=\"team\">\n  <div class=\"wrap\">\n    <div class=\"sec-head\"><div><span class=\"mono lbl\">" . ($o['label'] ?? '( Our team )') . '</span><h2 class="disp">' . ($o['h2'] ?? 'The people behind <em>your office.</em>') . "</h2></div></div>\n    <div class=\"team-grid\">\n{$cards}    </div>\n  </div>\n</section>";
}

function sc_testimonials(array $o): string
{
    $list = testimonials_list();
    $label = $o['label'] ?? '( Client stories )';
    $title = $o['h2'] ?? 'What our clients <em>say.</em>';
    $head = '<div class="sec-head"><div><span class="mono lbl">' . $label . '</span><h2 class="disp">' . $title . '</h2></div><a class="btn ghost" href="/portfolio/">See their offices</a></div>';
    if (!$list) {
        return "<section class=\"sec alt testi\" id=\"testimonials\">\n  <div class=\"wrap\">\n    {$head}\n    <p class=\"testi-empty\">Client testimonials will appear here once they are published.</p>\n  </div>\n</section>";
    }
    $q = '';
    foreach ($list as $t) {
        $who = implode(', ', array_filter([$t['role'], $t['company']], fn($x) => $x !== ''));
        $ph = $t['photo'] !== '' ? '<img src="' . e($t['photo']) . '" alt="" loading="lazy">' : '<span class="ini" aria-hidden="true">' . e(initials($t['name'])) . '</span>';
        $q .= '      <figure class="tq rv"><span class="tq-mark" aria-hidden="true">“</span><blockquote>' . paras((string)$t['quote']) . '</blockquote><figcaption>' . $ph . '<div><b>' . e($t['name']) . '</b>' . ($who !== '' ? '<span>' . e($who) . '</span>' : '') . "</div></figcaption></figure>\n";
    }
    return "<section class=\"sec alt testi\" id=\"testimonials\">\n  <div class=\"wrap\">\n    {$head}\n    <div class=\"testi-grid\">\n{$q}    </div>\n  </div>\n</section>";
}

/** Replace <!--sc:name {json}--> markers in stored HTML with live sections. */
function render_shortcodes(string $html): string
{
    return preg_replace_callback('/<!--sc:([a-z]+)\s*(\{.*?\})\s*-->/s', function ($m) {
        $legacyProcess = $m[1] === 'process';
        $fn = $legacyProcess ? 'sc_testimonials' : 'sc_' . $m[1];
        $o = json_decode($m[2], true) ?: [];
        if ($legacyProcess) $o['label'] = '( Client stories )';
        return function_exists($fn) ? $fn($o) : '';
    }, $html);
}
