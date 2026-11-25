#!/usr/bin/env node
/*
 * Prepares the PHP site (php-site/) from the Signature design:
 *
 *   node new_version/_build/build-php.js
 *
 * 1. Exports all content (pages, articles, projects, cities, redirects) to
 *    php-site/app/database/seed.json — the installer loads it into MySQL once.
 * 2. Generates the home template (app/views/generated/home.php) from the hand-made
 *    design-3-signature/index.html, and the pan-India map partial.
 * 3. Copies the shared front-end (assets/, images/, media/) into php-site/.
 *
 * The static Signature site stays the design source: edit its CSS/JS/home there, then re-run this.
 * Content edits after go-live happen in the PHP admin, not here (re-seeding never overwrites a live DB).
 */
const fs = require('fs');
const path = require('path');
const B = require('./build-signature.js');

const OUT = path.join(B.REPO, 'php-site');
const GEN = path.join(OUT, 'app/views/generated');
const write = (rel, s) => { const f = path.join(OUT, rel); fs.mkdirSync(path.dirname(f), { recursive: true }); fs.writeFileSync(f, s); };

/* ---------------------------------------------------------------- content */
B.setShortcodes(true);
const STATIC_PAGES = ['about', 'design-and-build', 'real-estate', 'pmc', 'contact', 'careers', 'privacy-policy', 'terms-and-conditions'];
const pages = [];
for (const [p, fn] of B.pages) {
  if (!STATIC_PAGES.includes(p)) continue;
  const o = fn(B.ctxAbs(p));
  pages.push({ slug: p, template: 'page', seo_title: o.title, seo_desc: o.desc, nav: o.active || '', foot_label: o.footLabel || '', foot_title: o.footH2 || '', body: o.body });
}
// template-driven pages: only their SEO text lives in the database
pages.push(
  { slug: '', template: 'home', seo_title: 'Office Interior Designers in Mumbai & Thane — Design & Build in 60 Days | Morphora Spaces', seo_desc: 'Morphora Spaces designs and builds commercial offices in Mumbai, Thane and across India — from layout to handover in 60 days. Get a free consultation.', nav: '', foot_label: '( 09 — Contact us )', foot_title: '', body: '' },
  { slug: 'portfolio', template: 'portfolio', seo_title: 'Portfolio — Offices We Have Delivered | Morphora Spaces', seo_desc: 'A selection of offices designed, built and managed by Morphora Spaces across Mumbai, Thane and Ahmedabad.', nav: 'portfolio', foot_label: '', foot_title: '', body: '' },
  { slug: 'resources', template: 'resources', seo_title: 'Resources — Office Design Guides & Articles | Morphora Spaces', seo_desc: 'Guides, tips and articles to help you plan, design and build a better office.', nav: 'resources', foot_label: '', foot_title: '', body: '' },
  { slug: 'locations', template: 'locations', seo_title: 'Locations — Office Interior Designers Across India | Morphora Spaces', seo_desc: 'Morphora Spaces designs and builds offices across India, including Mumbai, Thane, Pune, Bengaluru, Ahmedabad and Hyderabad.', nav: '', foot_label: '', foot_title: '', body: '' },
);
B.setShortcodes(false);

const P = B.ctxAbs('resources/x');
const posts = B.POSTS.map(p => {
  const { body, hero } = B.articleBody(p, P), thumb = B.postThumbRel(p);
  return { slug: p.slug, title: p.title, excerpt: p.desc || '', category: p.cat, published_at: p.date, body,
    cover: hero ? P.media(hero) : '', thumb: thumb ? P.media(thumb) : '' };
});
const projects = B.PROJECTS.map((pr, i) => ({ slug: pr.slug, name: pr.name, city: pr.city, size: pr.size, delivery: pr.by,
  cover: '/' + pr.img, gallery: B.projectGallery(pr, P), sort_order: i + 1, featured: 1 }));
const cities = B.CITIES.map((c, i) => ({ slug: c.slug, name: c.name, top: B.TOP_CITIES.includes(c.slug) ? 1 : 0 }));

// old WordPress URLs → new ones (301), so existing Google rankings and backlinks carry over
const redirects = [];
const r = (from, to) => redirects.push({ from_path: '/' + from.replace(/^\/|\/$/g, '') + '/', to_path: to });
for (const p of B.POSTS) r(p.slug, '/resources/' + p.slug + '/');
for (const [old, slug] of Object.entries(B.POST_RENAME)) { r(old, '/resources/' + slug + '/'); r('resources/' + old, '/resources/' + slug + '/'); }
for (const [old, keep] of Object.entries(B.POST_ALIAS)) { const s = B.POST_RENAME[keep] || keep; r(old, '/resources/' + s + '/'); r('resources/' + old, '/resources/' + s + '/'); }
for (const [old, to] of Object.entries(B.CITY_ALIAS)) r('office-interior-designer-in-' + old, to ? '/office-interior-designer-in-' + to + '/' : '/locations/');
[['portfolios', '/portfolio/'], ['job-openings', '/careers/'], ['customer-cabinet', '/contact/'], ['testimonials', '/about/'], ['office-planning-checklist', '/resources/']].forEach(([a, b]) => r(a, b));

const seed = {
  generated: new Date().toISOString(),
  settings: {
    site_name: 'Morphora Spaces', site_url: B.LIVE.replace(/\/$/, ''), phone: '+91 77191 55155', phone_e164: '+917719155155', whatsapp: '917719155155',
    email: 'hello@morphoraspaces.com', notify_email: 'hello@morphoraspaces.com',
    address_head: 'Chirag Infotech, 208, Road No. 16/Z, Indira Nagar, Wagle Estate, Thane West, Maharashtra 400604',
    address_reg: '1304, Dev Corpora, Eastern Express Highway, Khopat, Thane West, Maharashtra 400601',
    geo_lat: '19.2035213', geo_lng: '72.9539775', default_og_image: '/images/morphora-office-8000-sqft-hero.jpg',
    social_links: '', ga4_id: '', gsc_verification: '', disclaimer: B.DISCLAIMER,
  },
  pages, posts, projects, cities, redirects,
  jobs: [
    { slug: 'interior-designer', title: 'Interior Designer', location: 'Thane, Maharashtra', type: 'Full-time', experience: '2–5 years', description: '<p>Plan and design office interiors from concept to detailed drawings, working closely with clients and the site team.</p><ul><li>Space planning and 3D visualisation</li><li>Material and finish selection</li><li>Working drawings and site coordination</li></ul>', status: 'published' },
    { slug: 'project-manager', title: 'Project Manager — Interiors', location: 'Thane, Maharashtra', type: 'Full-time', experience: '5+ years', description: '<p>Run office fit-out projects on site — timelines, vendors, quality and handover.</p><ul><li>Vendor and contractor coordination</li><li>BOQ checks and billing</li><li>Quality and snag management</li></ul>', status: 'published' },
  ],
};
write('app/database/seed.json', JSON.stringify(seed, null, 1));

/* ---------------------------------------------------------- map partial */
// pan-India map with absolute links; PHP fills in {{label}} and {{count}}
write('app/views/generated/map.html', B.panIndiaAbs() + '\n');

/* ------------------------------------------------------------ home view */
{
  let s = fs.readFileSync(path.join(B.SIG, 'index.html'), 'utf8');
  const cut = (a, b) => { const i = s.indexOf(a), j = s.indexOf(b, i); if (i < 0 || j < 0) throw new Error('home marker missing: ' + a); return [i, j + b.length]; };
  const [h0] = cut('<!-- @header -->', '<!-- /@header -->'), h1 = s.indexOf('<!-- /@header -->') + '<!-- /@header -->'.length;
  const f0 = s.indexOf('<!-- @footer -->'), f1 = s.indexOf('<!-- /@footer -->') + '<!-- /@footer -->'.length;
  let main = s.slice(h1, f0);
  const script = s.slice(s.indexOf('<script>', f1), s.lastIndexOf('</script>') + 9);
  const absolutise = t => t
    .replace(/(href|src)="(?:\.\/)?index\.html"/g, '$1="/"')
    .replace(/(href)="(?:\.\/)?([a-z0-9][a-z0-9/-]*?)\/index\.html(#[^"]*)?"/g, (m, a, p, h) => `${a}="/${p}/${h || ''}"`)
    .replace(/(["'(])\.\/(images|media|assets)\//g, '$1/$2/');
  main = absolutise(main);
  // live sections: projects, map, blog, FAQ come from the database
  main = main.replace(/<div class="hz-track" id="hzTrack">[\s\S]*?<div class="hz-end">/, '<div class="hz-track" id="hzTrack">\n<?= home_project_cards() ?>\n      <div class="hz-end">');
  main = main.replace(/<!-- @map -->[\s\S]*?<!-- \/@map -->/, "<?= sc_map(['label' => '( 04 — Pan-India )']) ?>");
  main = main.replace(/(<section class="blog">[\s\S]*?<div class="blog-grid">)[\s\S]*?(<\/div>\s*<\/div>\s*<\/section>)/, '$1\n<?= post_cards(latest_posts(3)) ?>\n    $2');
  main = main.replace(/<!-- FAQ -->\s*<section class="faq">[\s\S]*?<\/section>/, '<!-- FAQ -->\n<?= sc_faq([\'label\' => \'( 08 — FAQs )\', \'items\' => home_faq()]) ?>');
  if (/\.\/[a-z]/.test(main.replace(/<script[\s\S]*?<\/script>/g, ''))) console.warn('home: some ./relative links remain');
  write('app/views/generated/home.php', `<?php /* GENERATED by new_version/_build/build-php.js from design-3-signature/index.html — edit the source, then rebuild. */ ?>\n${main.trim()}\n`);
  write('app/views/generated/home-script.html', absolutise(script) + '\n');
}

/* --------------------------------------------------------- front-end files */
const copyDir = (from, to, filter) => {
  for (const e of fs.readdirSync(from, { withFileTypes: true })) {
    const a = path.join(from, e.name), b = path.join(to, e.name);
    if (e.isDirectory()) copyDir(a, b, filter);
    else if (!filter || filter(a)) { fs.mkdirSync(to, { recursive: true }); fs.copyFileSync(a, b); }
  }
};
copyDir(path.join(B.SIG, 'assets'), path.join(OUT, 'assets'));
copyDir(path.join(B.SIG, 'images'), path.join(OUT, 'images'));
// media/: the optimised copies in design-3-signature/media (the home page script also builds media paths at runtime, so copy it all)
copyDir(path.join(B.SIG, 'media'), path.join(OUT, 'media'));
const missing = [...B.USED].filter(rel => !fs.existsSync(path.join(B.SIG, 'media', rel.replace(/^wp-content\/uploads\//, '')))).length;
if (missing) console.warn(`${missing} media files not found in design-3-signature/media — run build-signature.js first.`);

console.log(`PHP site ready: ${pages.length} pages, ${posts.length} articles, ${projects.length} projects, ${cities.length} cities, ${redirects.length} redirects.`);
