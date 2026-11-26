<?php
/*
 * SEO: <head> meta tags, Open Graph / Twitter cards, canonical URLs and schema.org JSON-LD.
 * Every page passes a $meta array to the layout:
 *   title, desc, path (canonical path), image, type (website|article), robots, crumbs [[name, path|null]…],
 *   schema (extra JSON-LD objects), published / modified (articles)
 */

/** JSON-LD blocks collected while a page renders (e.g. the FAQ section adds FAQPage). */
function schema_add(array $obj): void { $GLOBALS['SCHEMA'][] = $obj; }

function org_id(): string { return abs_url('/#organization'); }

/** The business itself — on every page, so Google can build the knowledge panel and local listing. */
function schema_org(): array
{
    $sameAs = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', setting('social_links')))));
    $org = [
        '@type' => ['Organization', 'LocalBusiness', 'GeneralContractor'],
        '@id' => org_id(),
        'name' => setting('site_name', 'Morphora Spaces'),
        'url' => abs_url('/'),
        'logo' => abs_url('/images/morphora-spaces-logo.png'),
        'image' => abs_url(setting('default_og_image', '/images/morphora-office-8000-sqft-hero.jpg')),
        'description' => 'Office interior design and build company in Thane, Mumbai — complete offices delivered in 60 days.',
        'telephone' => setting('phone', '+91 77191 55155'),
        'email' => setting('email', 'hello@morphoraspaces.com'),
        'priceRange' => '₹₹',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Chirag Infotech, 208, Road No. 16/Z, Indira Nagar, Wagle Estate',
            'addressLocality' => 'Thane West', 'addressRegion' => 'Maharashtra', 'postalCode' => '400604', 'addressCountry' => 'IN',
        ],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float)setting('geo_lat', '19.2035213'), 'longitude' => (float)setting('geo_lng', '72.9539775')],
        'areaServed' => ['@type' => 'Country', 'name' => 'India'],
        'openingHoursSpecification' => [['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], 'opens' => '10:00', 'closes' => '19:00']],
    ];
    if ($sameAs) $org['sameAs'] = $sameAs;
    return $org;
}

function schema_website(): array
{
    return ['@type' => 'WebSite', '@id' => abs_url('/#website'), 'url' => abs_url('/'), 'name' => setting('site_name', 'Morphora Spaces'), 'publisher' => ['@id' => org_id()], 'inLanguage' => 'en-IN'];
}

function schema_breadcrumbs(array $crumbs): ?array
{
    if (!$crumbs) return null;
    $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => abs_url('/')]];
    foreach ($crumbs as $i => [$name, $path]) {
        $it = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => plain($name)];
        if ($path !== null) $it['item'] = abs_url($path);
        $items[] = $it;
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/** Apply editable content-level SEO metadata to the route's existing defaults. */
function seo_apply_record(array $record, array $meta): array
{
    $title = trim((string)($record['seo_title'] ?? ''));
    $desc = trim((string)($record['seo_desc'] ?? ''));
    if ($title !== '') $meta['title'] = $title;
    if ($desc !== '') $meta['desc'] = $desc;
    if (!empty($record['og_image'])) $meta['image'] = $record['og_image'];
    $meta['canonical_url'] = trim((string)($record['canonical_url'] ?? ''));
    $meta['og_title'] = trim((string)($record['og_title'] ?? '')) ?: (string)($meta['title'] ?? '');
    $meta['og_desc'] = trim((string)($record['og_desc'] ?? '')) ?: (string)($meta['desc'] ?? '');
    $meta['schema_type'] = trim((string)($record['schema_type'] ?? 'auto')) ?: 'auto';

    if (empty($meta['robots_force'])) {
        if (!empty($record['noindex']) || !empty($record['nofollow'])) {
            $meta['robots'] = (!empty($record['noindex']) ? 'noindex' : 'index') . ',' . (!empty($record['nofollow']) ? 'nofollow' : 'follow');
        } elseif (isset($meta['robots']) && $meta['robots'] === null) {
            unset($meta['robots']);
        }
    }
    unset($meta['robots_force']);
    return $meta;
}

/** Everything that goes in <head> for SEO. */
function seo_head(array $m): string
{
    $title = trim($m['title'] ?? setting('site_name', 'Morphora Spaces'));
    $desc = str_limit(plain($m['desc'] ?? ''), 300);
    $canonical = trim((string)($m['canonical_url'] ?? ''));
    $canon = $canonical !== '' ? (preg_match('~^https?://~i', $canonical) ? $canonical : abs_url($canonical)) : (isset($m['path']) ? abs_url($m['path']) : null);
    $img = abs_url(($m['image'] ?? '') ?: setting('default_og_image', '/images/morphora-office-8000-sqft-hero.jpg'));
    $ogTitle = trim((string)($m['og_title'] ?? '')) ?: $title;
    $ogDesc = trim((string)($m['og_desc'] ?? '')) ?: $desc;
    $type = $m['type'] ?? 'website';
    $robots = $m['robots'] ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';

    $h = [];
    $h[] = '<title>' . e($title) . '</title>';
    if ($desc !== '') $h[] = '<meta name="description" content="' . e($desc) . '">';
    $h[] = '<meta name="robots" content="' . e($robots) . '">';
    if ($canon) $h[] = '<link rel="canonical" href="' . e($canon) . '">';
    $h[] = '<meta property="og:locale" content="en_IN">';
    $h[] = '<meta property="og:site_name" content="' . e(setting('site_name', 'Morphora Spaces')) . '">';
    $h[] = '<meta property="og:type" content="' . e($type) . '">';
    $h[] = '<meta property="og:title" content="' . e($ogTitle) . '">';
    if ($ogDesc !== '') $h[] = '<meta property="og:description" content="' . e($ogDesc) . '">';
    if ($canon) $h[] = '<meta property="og:url" content="' . e($canon) . '">';
    $h[] = '<meta property="og:image" content="' . e($img) . '">';
    $h[] = '<meta name="twitter:card" content="summary_large_image">';
    $h[] = '<meta name="twitter:title" content="' . e($ogTitle) . '">';
    if ($ogDesc !== '') $h[] = '<meta name="twitter:description" content="' . e($ogDesc) . '">';
    $h[] = '<meta name="twitter:image" content="' . e($img) . '">';
    if ($type === 'article') {
        if (!empty($m['published'])) $h[] = '<meta property="article:published_time" content="' . e(date('c', strtotime($m['published']))) . '">';
        if (!empty($m['modified'])) $h[] = '<meta property="article:modified_time" content="' . e(date('c', strtotime($m['modified']))) . '">';
    }
    if ($v = setting('gsc_verification')) $h[] = '<meta name="google-site-verification" content="' . e($v) . '">';

    // JSON-LD graph: organisation + website on every page, then breadcrumbs and page-specific types
    $pageSchemas = $m['schema'] ?? [];
    $schemaType = (string)($m['schema_type'] ?? 'auto');
    if ($schemaType !== '' && $schemaType !== 'auto') {
        if ($pageSchemas) {
            $pageSchemas[0]['@type'] = $schemaType;
        } elseif (strpos($robots, 'noindex') === false) {
            $pageSchemas[] = [
                '@type' => $schemaType, '@id' => ($canon ?: abs_url('/')) . '#webpage',
                'url' => $canon ?: abs_url('/'), 'name' => $title, 'description' => $desc,
                'isPartOf' => ['@id' => abs_url('/#website')], 'inLanguage' => 'en-IN',
            ];
        }
    } elseif (!$pageSchemas && strpos($robots, 'noindex') === false) {
        $pageSchemas[] = [
            '@type' => 'WebPage', '@id' => ($canon ?: abs_url('/')) . '#webpage',
            'url' => $canon ?: abs_url('/'), 'name' => $title, 'description' => $desc,
            'isPartOf' => ['@id' => abs_url('/#website')], 'inLanguage' => 'en-IN',
        ];
    }
    $graph = [schema_org(), schema_website()];
    if ($bc = schema_breadcrumbs($m['crumbs'] ?? [])) $graph[] = $bc;
    foreach (array_merge($pageSchemas, $GLOBALS['SCHEMA'] ?? []) as $s) $graph[] = $s;
    $h[] = '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';

    if ($ga = setting('ga4_id')) {
        $h[] = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($ga) . '"></script>';
        $h[] = '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config",' . json_encode($ga) . ');</script>';
    }
    return implode("\n", $h);
}

/** Title helper: "Page — Morphora Spaces" unless the title already names the brand. */
function seo_title(string $t): string
{
    $brand = setting('site_name', 'Morphora Spaces');
    return stripos($t, $brand) !== false ? $t : $t . ' | ' . $brand;
}
