#!/usr/bin/env node
/*
 * Builds every inner page of the Signature design from the saved copy of the live
 * site (repo root), using one shared header, footer, stylesheet and script.
 *
 *   node new_version/_build/build-signature.js
 *
 * Output: new_version/design-3-signature/**  (home page header/footer are refreshed
 * between the <!-- @header --> / <!-- @footer --> markers; the rest of home is hand-made).
 */
const fs = require('fs');
const path = require('path');

const REPO = path.resolve(__dirname, '../..');
const SIG = path.join(REPO, 'new_version/design-3-signature');
const LIVE = 'https://morphoraspaces.com/';
const USED = new Set(); // repo-relative uploads to copy into media/

/* ------------------------------------------------------------------ helpers */
const read = rel => fs.readFileSync(path.join(REPO, rel), 'utf8');
const exists = rel => fs.existsSync(path.join(REPO, rel));
const esc = s => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const decode = s => String(s)
  .replace(/&#8217;|&rsquo;|&#039;|&#39;/g, '’').replace(/&#8216;/g, '‘').replace(/&#8220;|&#8221;|&quot;/g, '"')
  .replace(/&#8211;|&ndash;/g, '–').replace(/&#8212;|&mdash;/g, '—').replace(/&#038;|&amp;/g, '&').replace(/&nbsp;/g, ' ')
  .replace(/&hellip;|&#8230;/g, '…').replace(/&#(\d+);/g, (m, n) => String.fromCharCode(+n)).trim();
const text = html => decode(html.replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim();
const meta = (html, key) => { const m = html.match(new RegExp(`<meta (?:name|property)="${key}" content="([^"]*)"`)); return m ? decode(m[1]) : ''; };
const attr = (attrs, name) => { const m = attrs.match(new RegExp(`\\s${name}\\s*=\\s*("([^"]*)"|'([^']*)')`, 'i')); return m ? (m[2] ?? m[3]) : ''; };
const titleCase = s => s.split('-').map(w => w[0].toUpperCase() + w.slice(1)).join(' ');

// inner HTML of the <div> that starts at idx (balanced)
function innerDiv(html, idx) {
  const start = html.indexOf('>', idx) + 1, re = /<\/?div\b[^>]*>/gi;
  re.lastIndex = start; let depth = 1, m;
  while ((m = re.exec(html))) { if (m[0][1] === '/') { if (--depth === 0) return html.slice(start, m.index); } else depth++; }
  return html.slice(start);
}
function widgets(html, type) {
  const out = [], key = `data-widget_type="${type}"`; let i = 0;
  while ((i = html.indexOf(key, i)) >= 0) { out.push(innerDiv(html, html.lastIndexOf('<div', i))); i += key.length; }
  return out;
}

// directory listing cache for picking image size variants
const dirCache = {};
function listDir(relDir) { if (!dirCache[relDir]) { try { dirCache[relDir] = fs.readdirSync(path.join(REPO, relDir)); } catch { dirCache[relDir] = []; } } return dirCache[relDir]; }
function imgBase(file) { return file.replace(/(\.[a-z0-9]+)$/i, '').replace(/-\d+x\d+$/, '').replace(/-scaled$/, ''); }
// pick a local variant of an uploads image: prefs like ['1024x','scaled','orig']
function pickImg(rel, prefs) {
  const dir = path.posix.dirname(rel), file = path.posix.basename(rel), ext = path.posix.extname(file), base = imgBase(file);
  const files = listDir(dir);
  for (const p of prefs) {
    let f;
    if (p === 'orig') f = files.find(x => x === base + ext);
    else if (p === 'scaled') f = files.find(x => x === base + '-scaled' + ext);
    else f = files.find(x => x.startsWith(base + '-' + p) && /-\d+x\d+\.[a-z0-9]+$/i.test(x) && imgBase(x) === base);
    if (f) return dir + '/' + f;
  }
  return exists(rel) ? rel : null;
}

/* ------------------------------------------------------------- site data */
const PROJECTS = [
  { slug: 'lafarge-holcim', name: 'Lafarge Holcim', city: 'Mumbai', size: '18,000 sq ft', by: 'Managed & executed with TOA', img: 'images/IMG_20200919_174008-scaled.jpg' },
  { slug: 'codal-ahmedabad', name: 'Codal', city: 'Ahmedabad', size: '12,000 sq ft', by: 'Office interiors', img: 'images/Lounge-Area-1024x731.jpg' },
  { slug: 'festival-mumbai', name: 'Festival', city: 'Mumbai', size: '3,000 sq ft', by: 'Design & Build', img: 'images/ADI8358-scaled.jpg' },
  { slug: 'ishan-tech-ahmedabad', name: 'Ishan Tech', city: 'Ahmedabad', size: '5,000 sq ft', by: 'Managed & executed with Phi Designs', img: 'images/MicrosoftTeams-image-51-1024x731.jpg' },
  { slug: 'gro-value-mumbai', name: 'Gro Value', city: 'Mumbai', size: '2,500 sq ft', by: 'Managed & executed by Morphora Spaces', img: 'images/office-interior-design-santacruz-mumbai-grovalue09-3-1024x683.jpg' },
  { slug: 'i-i-fidelis-thane', name: 'I & I Fidelis', city: 'Thane', size: '2,000 sq ft', by: 'Managed & executed with ANS Designs', img: 'images/20-I-I-MD-CABIN-1-1024x684.jpg' },
];
const DISCLAIMER = 'Some of the projects shown on this website were completed by our founder while working with other companies. They are included only to show professional experience and design capability.';

const CORE = new Set(['about', 'contact', 'careers', 'pmc', 'real-estate', 'resources', 'portfolio', 'privacy-policy', 'terms-and-conditions']);
const SKIP_POSTS = new Set([...CORE, 'customer-cabinet', 'testimonials', 'job-openings', 'portfolios', 'office-planning-checklist']);
const CITY_ALIAS = { 'thane-2': 'thane', mangalore: 'mangaluru', location: null };
const CAT_FIX = { 'Ofiice Interior Designing': 'Office Interior Design', 'Office Design and Build': 'Design & Build', 'Office PMC': 'Project Management', 'Corporate Real Estate': 'Real Estate' };

// ---- cities ----
const CITIES = fs.readdirSync(REPO).filter(d => d.startsWith('office-interior-designer-in-') && exists(d + '/index.html'))
  .map(d => d.slice('office-interior-designer-in-'.length)).filter(c => !(c in CITY_ALIAS))
  .map(c => ({ slug: c, name: c === 'hubballi-dharwad' || c === 'pimpri-chinchwad' ? c.split('-').map(w => w[0].toUpperCase() + w.slice(1)).join('-') : titleCase(c) }))
  .sort((a, b) => a.name.localeCompare(b.name));
const CITY_SET = new Set(CITIES.map(c => c.slug));
const TOP_CITIES = ['mumbai', 'thane', 'pune', 'bengaluru', 'ahmedabad', 'hyderabad'];

// ---- blog posts ----
// duplicate posts on the live site -> the one we keep
const POST_ALIAS = { 'do-small-offices-need-an-interior-designer-2': 'do-small-offices-need-an-interior-designer', 'premium-small-office-design-guide': 'premium-small-office-design' };
// messy live slugs -> clean URLs
const POST_RENAME = {
  'https-morphoraspaces-com-office-interior-planning-guide': 'office-interior-planning-guide',
  'psychology-of-office-colours-work-zones-jpg': 'psychology-of-office-colours',
  'top-office-design-for-startups-mistakes-and-how-to-avoid-them-morphora-spaces': 'office-design-mistakes-startups',
  'why-big-design-firms-arent-always-the-best-choice-for-start-ups': 'why-big-design-firms-arent-best-for-startups',
};
const POSTS = fs.readdirSync(REPO).filter(d => {
  if (SKIP_POSTS.has(d) || d in POST_ALIAS || d.startsWith('office-interior-designer-in-') || !exists(d + '/index.html')) return false;
  const h = read(d + '/index.html');
  return meta(h, 'og:type') === 'article' && meta(h, 'article:published_time') && h.includes('theme-post-content.default');
}).map(src => {
  const slug = POST_RENAME[src] || src;
  const h = read(src + '/index.html');
  const t = widgets(h, 'theme-post-title.default')[0];
  const title = (t ? text(t) : meta(h, 'og:title')).replace(/\s*[-|–]\s*Morphora Spaces!?\s*$/i, '');
  const og = meta(h, 'og:image').replace(LIVE, '');
  // the live site tags most posts with every category, so only trust a single, real category
  const secs = [...h.matchAll(/<meta property="article:section" content="([^"]*)"/g)].map(m => decode(m[1])).filter(s => s !== 'Uncategorized');
  const cat = secs.length === 1 ? (CAT_FIX[secs[0]] || secs[0]) : 'Office Design';
  return { slug, title, desc: meta(h, 'og:description') || meta(h, 'description'), date: meta(h, 'article:published_time').slice(0, 10), cat, og, raw: widgets(h, 'theme-post-content.default')[0] || '' };
}).filter(p => text(p.raw).length > 200).sort((a, b) => b.date.localeCompare(a.date));
const POST_SET = new Set(POSTS.map(p => p.slug));
const postOut = a => { a = POST_ALIAS[a] || a; a = POST_RENAME[a] || a; return POST_SET.has(a) ? a : null; };

/* --------------------------------------------------------- link routing */
// maps any live-site link (absolute or relative-from-saved-page) to a Signature path ('' = home), or null
function route(href) {
  if (!href) return null;
  href = href.trim();
  if (/^(mailto:|tel:|https:\/\/wa\.me)/i.test(href)) return { raw: href };
  if (href.startsWith('#')) return null;
  let u = href;
  const m = u.match(/^https?:\/\/(?:www\.)?morphoraspaces\.com(\/.*)?$/i);
  if (m) u = m[1] || '/';
  else if (/^(https?:)?\/\//i.test(u)) return { raw: href, ext: true };
  u = u.replace(/[?#].*$/, '').replace(/^(\.\.?\/)+/, '/');
  if (/^\/?wp-content\//.test(u)) return { asset: u.replace(/^\//, '') };
  u = u.replace(/index\.html$/, '').replace(/^\/+|\/+$/g, '');
  const [a, b] = u.split('/');
  if (!a) return { page: '' };
  if (a === 'resources' && b && postOut(b)) return { page: 'resources/' + postOut(b) };
  if (a === 'portfolio') return { page: b && PROJECTS.some(p => p.slug === b) ? 'portfolio/' + b : 'portfolio' };
  if (CORE.has(a)) return { page: a };
  if (a === 'portfolios') return { page: 'portfolio' };
  if (a === 'job-openings') return { page: 'careers' };
  if (a === 'customer-cabinet') return { page: 'contact' };
  if (a === 'testimonials') return { page: 'about' };
  if (['office-planning-checklist', 'category', 'author'].includes(a)) return { page: 'resources' };
  if (postOut(a)) return { page: 'resources/' + postOut(a) };
  if (a.startsWith('office-interior-designer-in-')) {
    const c = a.slice('office-interior-designer-in-'.length), alias = c in CITY_ALIAS ? CITY_ALIAS[c] : c;
    return { page: alias && CITY_SET.has(alias) ? 'office-interior-designer-in-' + alias : 'locations' };
  }
  return null;
}

/* ----------------------------------------------------- page context (paths) */
function ctx(pagePath) {
  const depth = pagePath ? pagePath.split('/').length : 0;
  const root = depth ? '../'.repeat(depth) : './';
  const up = root + '../../'; // repo root (for /wp-content)
  const P = {
    path: pagePath, root, up,
    L: p => p ? root + p + '/index.html' : root + 'index.html',
    img: p => root + p,                       // signature images
    media: rel => { USED.add(rel); return root + 'media/' + rel.replace(/^wp-content\/uploads\//, ''); }, // copied into design-3-signature/media
    asset(src) {                              // any live image url -> local if available
      const r = route(src); if (!r || !r.asset) return /^https?:/.test(src) ? src : LIVE + src.replace(/^(\.\.?\/)+/, '');
      return exists(r.asset) ? P.media(pickImg(r.asset, ['1024x', '768x', 'orig', 'scaled'])) : LIVE + r.asset;
    },
    href(h) {
      const r = route(h); if (!r) return null;
      if (r.raw) return { href: r.raw, ext: r.ext };
      if (r.asset) return { href: exists(r.asset) ? P.media(r.asset) : LIVE + r.asset };
      return { href: P.L(r.page) };
    },
  };
  return P;
}

/* -------------------------------------------------------- HTML sanitising */
const ALLOWED = new Set(['h2', 'h3', 'h4', 'p', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'a', 'img', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'br']);
function clean(html, P) {
  html = html.replace(/<(script|style|noscript|svg|iframe|form|button|select|textarea|nav)\b[\s\S]*?<\/\1>/gi, '').replace(/<!--[\s\S]*?-->/g, '');
  html = html.replace(/<(\/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>/g, (m, cl, tag, attrs) => {
    tag = tag.toLowerCase();
    if (tag === 'h1') tag = 'h2';
    if (tag === 'h5' || tag === 'h6') tag = 'h4';
    if (!ALLOWED.has(tag)) return (tag === 'div' || tag === 'section' || tag === 'figure') && !cl ? '\n' : '';
    if (cl) return `</${tag}>`;
    if (tag === 'a') { const r = P.href(decode(attr(attrs, 'href'))); return r ? `<a href="${esc(r.href)}"${r.ext ? ' target="_blank" rel="noopener"' : ''}>` : '<a>'; }
    if (tag === 'img') {
      const s = attr(attrs, 'data-lazy-src') || attr(attrs, 'data-src') || attr(attrs, 'src');
      if (!s || s.startsWith('data:')) return '';
      return `<img src="${esc(P.asset(decode(s)))}" alt="${esc(decode(attr(attrs, 'alt')))}" loading="lazy">`;
    }
    return `<${tag}>`;
  });
  html = html.replace(/<a>([\s\S]*?)<\/a>/g, '$1');
  for (let i = 0; i < 3; i++) html = html.replace(/<(p|li|h2|h3|h4|strong|b|em|i|blockquote|ul|ol)>(\s|&nbsp;| |<br>)*<\/\1>/g, '');
  return html.replace(/\n\s*\n+/g, '\n').trim();
}

/* ------------------------------------------------------------- partials */
const NAV = [
  ['about', 'About', 'About us', 'Our story, team and way of working.', 'Our story and team', 'up:wp-content/uploads/2025/10/slidebean-TpGIpUF67po-unsplash-scaled.jpg'],
  ['design-and-build', 'Design &amp; Build', 'Design &amp; Build', 'Complete office interiors, from layout to handover.', 'Complete office interiors', 'sig:images/ADI8340-scaled.jpg'],
  ['portfolio', 'Portfolio', 'Portfolio', '150+ offices we have designed and built.', 'Offices we have delivered', 'up:wp-content/uploads/2025/10/ADI8265-1024x692.jpg'],
  ['resources', 'Resources', 'Resources', 'Guides and articles on office design.', 'Guides and articles', 'up:wp-content/uploads/2025/10/MicrosoftTeams-image-51-1024x731.jpg'],
  ['contact', 'Contact Us', 'Contact us', 'Visit our Thane office or call our team.', 'Talk to our team', 'up:wp-content/uploads/2025/10/asia-culturecenter-5h9ex_RrmhY-unsplash-scaled.jpg'],
];
const navImg = (P, s) => s.startsWith('up:') ? P.media(s.slice(3)) : P.root + s.slice(4);

function header(P, active) {
  return `<header>
  <div class="wrap">
    <a class="logo" href="${P.L('')}" aria-label="Morphora Spaces — home"><img src="${P.img('images/Morphora-Logo-1-e1758320614708.png')}" alt="Morphora Spaces"></a>
    <nav aria-label="Main">
      <ul class="nav">
${NAV.map(n => `        <li><a href="${P.L(n[0])}"${active === n[0] ? ' class="on" aria-current="page"' : ''}>${n[1]}</a><span class="prev"><img src="${navImg(P, n[5])}" alt="" loading="lazy"><span><b>${n[2]}</b>${n[3]}</span></span></li>`).join('\n')}
      </ul>
    </nav>
    <div class="right">
      <a class="hbtn" href="#contact">Let’s talk</a>
      <button class="menu-t mono" id="menuT" aria-label="Open menu" aria-expanded="false" aria-controls="mnav"><span id="menuL">Menu</span><b><i></i><i></i></b></button>
    </div>
  </div>
</header>
<div class="mnav" id="mnav">
  <ul>
${NAV.map((n, i) => `    <li><a href="${P.L(n[0])}"><b>0${i + 1}</b><span><strong>${n[1]}</strong><small>${n[4]}</small></span><img src="${navImg(P, n[5])}" alt="" loading="lazy"></a></li>`).join('\n')}
  </ul>
  <div class="mfoot">
    <a class="pri" href="tel:+917719155155">Call us</a>
    <a href="https://wa.me/917719155155" target="_blank" rel="noopener">WhatsApp</a>
    <p>hello@morphoraspaces.com · Thane, Maharashtra</p>
  </div>
</div>`;
}

function footer(P, label = '( Contact us )', h2 = 'Let’s plan <em>your office.</em>') {
  const city = s => `<a href="${P.L('office-interior-designer-in-' + s)}">${CITIES.find(c => c.slug === s).name}</a>`;
  return `<!-- CTA + FOOTER -->
<section class="cta" id="contact">
  <div class="wrap">
    <span class="mono">${label}</span>
    <h2 class="disp">${h2}</h2>
    <p class="cta-lead">Tell us a little about your space. Our team will call you back within one working day with next steps and a rough estimate.</p>
    <div class="cta-grid">
      <div class="cinfo">
        <div><span class="mono">Call us</span><a class="big" href="tel:+917719155155">+91 77191 55155</a></div>
        <div><span class="mono">Email</span><a class="big" href="mailto:hello@morphoraspaces.com">hello@morphoraspaces.com</a></div>
        <div><span class="mono">WhatsApp</span><a class="big" href="https://wa.me/917719155155" target="_blank" rel="noopener">Chat with us ↗</a></div>
        <div><span class="mono">Office</span><address>1304, Dev Corpora, Eastern Express Highway, Khopat, Thane West, Maharashtra 400601</address><a class="map" href="https://maps.google.com/?q=Dev+Corpora+Cadbury+junction+Eastern+Express+Hwy+Thane+400601" target="_blank" rel="noopener">Get directions ↗</a></div>
        <div><span class="mono">We work in</span><p class="cities">Mumbai · Thane · Pune · Ahmedabad · Bengaluru · Delhi · Hyderabad · <a href="${P.L('locations')}" style="color:var(--plum-l)">all locations</a></p></div>
      </div>
      <form class="cform" id="cform" novalidate>
        <h3>Send us an enquiry</h3>
        <label>Your name<input name="name" type="text" autocomplete="name" placeholder="Full name" required></label>
        <label>Company<input name="company" type="text" autocomplete="organization" placeholder="Company name"></label>
        <label>Phone<input name="phone" type="tel" autocomplete="tel" placeholder="+91" required></label>
        <label>Email<input name="email" type="email" autocomplete="email" placeholder="you@company.com"></label>
        <label>City<input name="city" type="text" placeholder="e.g. Mumbai"></label>
        <label>Office size
          <select name="size">
            <option value="">Select size</option>
            <option>Under 2,000 sq ft</option>
            <option>2,000 – 5,000 sq ft</option>
            <option>5,000 – 10,000 sq ft</option>
            <option>10,000 – 25,000 sq ft</option>
            <option>Over 25,000 sq ft</option>
          </select>
        </label>
        <label class="full">What do you need?
          <select name="service">
            <option value="">Select a service</option>
            <option>Office design &amp; build</option>
            <option>Finding office space</option>
            <option>Project management</option>
            <option>Not sure yet</option>
          </select>
        </label>
        <label class="full">Message<textarea name="msg" placeholder="Tell us about your project, timeline or budget"></textarea></label>
        <div class="send"><button type="submit">Send enquiry →</button><span class="note" id="cnote">Opens WhatsApp with your details filled in.</span></div>
      </form>
    </div>
    <div class="ft">
      <div><span class="mono">Company</span><a href="${P.L('about')}">About us</a><a href="${P.L('portfolio')}">Portfolio</a><a href="${P.L('resources')}">Resources</a><a href="${P.L('careers')}">Careers</a><a href="${P.L('contact')}">Contact us</a></div>
      <div><span class="mono">Services</span><a href="${P.L('design-and-build')}">Design &amp; Build</a><a href="${P.L('real-estate')}">Real Estate Advisory</a><a href="${P.L('pmc')}">Project Management</a></div>
      <div><span class="mono">Locations</span>${['mumbai', 'thane', 'pune', 'bengaluru'].map(city).join('')}</div>
      <div><span class="mono">&nbsp;</span>${['ahmedabad', 'delhi', 'hyderabad'].map(city).join('')}<a href="${P.L('locations')}">All locations →</a></div>
    </div>
    <div class="legal mono"><span>© <span id="yr">${new Date().getFullYear()}</span> Morphora Spaces</span><span><a href="${P.L('privacy-policy')}">Privacy</a> · <a href="${P.L('terms-and-conditions')}">Terms</a></span></div>
  </div>
</section>`;
}

function layout(P, o) {
  const canonical = LIVE + (P.path ? P.path + '/' : '');
  return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(o.title)}</title>
<meta name="description" content="${esc(o.desc)}">
<meta property="og:title" content="${esc(o.title)}">
<meta property="og:description" content="${esc(o.desc)}">
<meta property="og:type" content="${o.ogType || 'website'}">
<meta property="og:url" content="${canonical}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="${P.root}assets/site.css">
<script defer src="/_vercel/insights/script.js"></script>
</head>
<body>
<div class="cur" id="cur"><span>VIEW</span></div>

<!-- @header -->
${header(P, o.active)}
<!-- /@header -->

<main>
${o.body}
</main>

<!-- @footer -->
${footer(P, o.footLabel, o.footH2)}
<!-- /@footer -->

<script src="${P.root}assets/site.js"></script>
</body>
</html>
`;
}

/* ------------------------------------------------------- pan-India map */
// Outline: MapSVG "Map of India" (CC BY 4.0), slimmed into _build/india-map.json. Its projection is
// Mercator with x = (lon − 68.18) × 20.94 and the top edge at 37.08°N, so cities are placed from lat/lon.
const INDIA = JSON.parse(fs.readFileSync(path.join(__dirname, 'india-map.json'), 'utf8'));
const MERC = p => Math.log(Math.tan(Math.PI / 4 + p * Math.PI / 360));
const proj = (lat, lon) => [+((lon - 68.18) * 20.94).toFixed(1), +(20.94 * 180 / Math.PI * (MERC(37.08) - MERC(lat))).toFixed(1)];
const HQ = { name: 'Mumbai · Thane', sub: 'Our studio', lat: 19.22, lon: 72.98, slug: 'thane' };
// [display name, city page slug, lat, lon, label side]
const METROS = [
  ['Mumbai', 'mumbai', 19.03, 72.85, 'l'],
  ['Delhi NCR', 'delhi', 28.61, 77.21, 'r'],
  ['Bengaluru', 'bengaluru', 12.97, 77.59, 'l'],
  ['Hyderabad', 'hyderabad', 17.39, 78.49, 'r'],
  ['Chennai', 'chennai', 13.08, 80.27, 'r'],
  ['Kolkata', 'kolkata', 22.57, 88.36, 'r'],
  ['Pune', 'pune', 18.52, 73.86, 'r'],
  ['Ahmedabad', 'ahmedabad', 23.02, 72.57, 'l'],
];
function panIndia(P, label = '( Pan-India )') {
  const [hx, hy] = proj(HQ.lat, HQ.lon);
  const href = s => P.L('office-interior-designer-in-' + s);
  const arcs = METROS.filter(m => m[1] !== 'mumbai').map(([, s, lat, lon], i) => {
    const [x, y] = proj(lat, lon), mx = (hx + x) / 2, my = (hy + y) / 2, dx = x - hx, dy = y - hy;
    const cx = (mx - dy * .22).toFixed(1), cy = (my + dx * .22).toFixed(1);
    return `<path data-city="${s}" d="M${hx} ${hy}Q${cx} ${cy} ${x} ${y}" pathLength="1" style="--i:${i}"/>`;
  }).join('');
  const pin = (name, s, lat, lon, side, i, hq) => {
    const [x, y] = proj(lat, lon), tx = side === 'l' ? -12 : 12, anchor = side === 'l' ? 'end' : 'start';
    const dy = s === 'pune' ? 10 : 4;
    return `<a class="pin${hq ? ' hq' : ''}" href="${href(s)}" data-city="${hq ? 'thane mumbai' : s}" style="--i:${i}" aria-label="${name}"><g transform="translate(${x} ${y})"><circle class="ring" r="${hq ? 9 : 6}"/><circle class="dot" r="${hq ? 6 : 4}"/><text x="${tx}" y="${dy}" text-anchor="${anchor}">${name}</text>${hq ? `<text class="sub" x="${tx}" y="${dy + 15}" text-anchor="${anchor}">${HQ.sub}</text>` : ''}</g></a>`;
  };
  return `<section class="pan" id="pan">
  <div class="wrap pan-grid">
    <div class="pan-txt">
      <span class="mono">${label}</span>
      <h2 class="disp">One studio. <em>Offices across India.</em></h2>
      <p>We design, build and manage offices for clients across India from our studio in Thane. Our team works on-site or remotely, depending on the location and size of your project.</p>
      <ol class="pan-list">
        <li><a href="${href(HQ.slug)}" data-city="${HQ.slug}"><b>HQ</b><span>Thane <small>Our studio</small></span><i>↗</i></a></li>
${METROS.map(([n, s], i) => `        <li><a href="${href(s)}" data-city="${s}"><b>0${i + 1}</b><span>${n}</span><i>↗</i></a></li>`).join('\n')}
      </ol>
      <a class="pan-all" href="${P.L('locations')}">See all ${CITIES.length} cities we work in →</a>
    </div>
    <figure class="pan-map">
      <svg viewBox="${INDIA.viewBox}" role="img" aria-label="Map of India showing the Morphora Spaces studio in Thane and the major metro cities we work in">
        <g class="pan-states">${INDIA.states.map(s => `<path d="${s.d}"/>`).join('')}</g>
        <g class="pan-arcs">${arcs}</g>
        <g class="pan-pins">${METROS.filter(m => m[1] !== 'mumbai').map((m, i) => pin(...m, i + 1)).join('')}${pin(HQ.name, HQ.slug, HQ.lat, HQ.lon, 'l', 0, true)}</g>
      </svg>
      <figcaption>Map outline: <a href="https://mapsvg.com/maps/india" target="_blank" rel="noopener">MapSVG</a>, <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a></figcaption>
    </figure>
  </div>
</section>`;
}

/* ---------------------------------------------------------- section kits */
const crumbs = (P, items) => `<nav class="crumb mono" aria-label="Breadcrumb"><a href="${P.L('')}">Home</a>${items.map(([t, p]) => ` <span>/</span> ${p !== undefined ? `<a href="${P.L(p)}">${t}</a>` : `<span aria-current="page">${t}</span>`}`).join('')}</nav>`;
const badge = () => `<a class="circle-btn" href="#contact" data-cur="TALK"><svg viewBox="0 0 134 134" aria-hidden="true"><defs><path id="cpb" d="M67,67 m-56,0 a56,56 0 1,1 112,0 a56,56 0 1,1 -112,0"/></defs><text fill="currentColor" font-size="10" font-weight="500" letter-spacing="3.6" font-family="Jost"><textPath href="#cpb">START A PROJECT · 60 DAYS · START A PROJECT · </textPath></text></svg><span>Let’s talk ↗</span></a>`;
// big scroll-driven word banner (words differ per page)
const tmq = (words, dark) => `<div class="tmq${dark ? ' dark' : ''}" aria-hidden="true"><div>${[...words, ...words].map(w => `<span>${w}</span><i>✦</i>`).join('')}</div></div>`;
const phero = (P, o) => `<section class="phero">
  <div class="wrap">
    ${crumbs(P, o.crumb)}
    <h1 class="disp">${o.h1}</h1>
    <div class="phero-meta"><p>${o.lead}</p>${o.badge ? badge() : o.btns === false ? '' : `<div class="btns"><a class="btn" href="#contact">${o.cta || 'Get a free consultation'}</a>${o.btn2 ? `<a class="btn ghost" href="${o.btn2[1]}">${o.btn2[0]}</a>` : ''}</div>`}</div>
  </div>
</section>${o.img ? `
<section class="pimg"><div class="wrap"><div><img src="${o.img}" alt="${esc(o.imgAlt || '')}"></div></div></section>` : ''}${o.tmq ? '\n' + tmq(o.tmq, o.tmqDark) : ''}`;

const whyList = items => `<ol>
${items.map(([h, p], i) => `      <li class="rv"><span class="mono">${String(i + 1).padStart(2, '0')}</span><div><h3>${h}</h3><p>${p}</p></div></li>`).join('\n')}
    </ol>`;
const whySec = (label, h2, items, alt) => `<section class="why${alt ? ' sec alt' : ''}">
  <div class="wrap why-grid">
    <div><span class="mono" style="color:var(--plum)">${label}</span><h2 class="disp">${h2}</h2></div>
    ${whyList(items)}
  </div>
</section>`;
const stepsSec = (label, h2, items, alt) => `<section class="sec${alt ? ' alt' : ''}">
  <div class="wrap">
    <div class="sec-head"><div><span class="mono lbl">${label}</span><h2 class="disp">${h2}</h2></div></div>
    <div class="steps line">
${items.map(([h, p], i) => `      <div class="rv"><b>${String(i + 1).padStart(2, '0')}</b><h3>${h}</h3><p>${p}</p></div>`).join('\n')}
    </div>
  </div>
</section>`;
const splitSec = (label, h2, inner, alt) => `<section class="sec${alt ? ' alt' : ''}">
  <div class="wrap split">
    <div><span class="mono lbl">${label}</span><h2 class="disp">${h2}</h2></div>
    <div class="prose">${inner}</div>
  </div>
</section>`;
const wcard = (P, p) => `<a class="wcard rv" href="${P.L('portfolio/' + p.slug)}" data-cur="VIEW"><div class="ph" data-tilt><img src="${P.img(p.img)}" alt="${esc(p.name)} office, ${p.city}" loading="lazy"></div><div class="meta"><div><h3>${esc(p.name)}</h3><span class="by">${esc(p.by)}</span></div><span class="mono">${p.city}<br>${p.size}</span></div></a>`;
const workSec = (P, label, h2, list, three) => `<section class="sec">
  <div class="wrap">
    <div class="sec-head"><div><span class="mono lbl">${label}</span><h2 class="disp">${h2}</h2></div><a class="btn ghost" href="${P.L('portfolio')}">All projects</a></div>
    <div class="work-grid${three ? ' three' : ''}">
${list.map(p => '      ' + wcard(P, p)).join('\n')}
    </div>
  </div>
</section>`;
const postThumb = (P, p) => { const r = p.og && pickImg(p.og, ['1024x', '768x', 'orig', 'scaled']); return r ? P.media(r) : P.img('images/ADI8340-scaled.jpg'); };
const fmtDate = d => new Date(d + 'T00:00:00Z').toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
const postCard = (P, p, all) => `<a class="post rv" href="${P.L('resources/' + p.slug)}"${all ? ` data-cat="${esc(p.cat)}"` : ''}><div class="ph"><img src="${postThumb(P, p)}" alt="" loading="lazy"></div><span class="mono">${esc(p.cat)}</span><h3>${esc(p.title)}</h3><span class="date">${fmtDate(p.date)}</span></a>`;
const blogSec = (P, label, posts) => `<section class="blog">
  <div class="wrap">
    <div class="blog-head"><div><span class="mono" style="color:var(--plum)">${label}</span><h2 class="disp">Ideas for <em>better offices.</em></h2></div><a class="mono" href="${P.L('resources')}">All articles ↗</a></div>
    <div class="blog-grid">
${posts.map(p => '      ' + postCard(P, p)).join('\n')}
    </div>
  </div>
</section>`;
const faqSec = (P, label, items) => `<section class="faq">
  <div class="wrap faq-grid">
    <div><span class="mono" style="color:var(--plum)">${label}</span><h2 class="disp">Common <em>questions.</em></h2><p>Still have a question? <a href="#contact">Talk to our team</a> and we will help you out.</p></div>
    <div>
${items.map(([q, a]) => `      <details><summary>${q}</summary><p>${a}</p></details>`).join('\n')}
    </div>
  </div>
</section>`;
const numsSec = () => `<section class="nums">
  <div class="wrap nums-grid">
    <div class="n rv"><b><span data-c="60">0</span><sup>d</sup></b><span class="mono">Days from design to handover</span></div>
    <div class="n rv"><b><span data-c="2">0</span><sup>M+</sup></b><span class="mono">Sq. ft. of office space designed</span></div>
    <div class="n rv"><b><span data-c="10">0</span><sup>+</sup></b><span class="mono">Cities we work in</span></div>
    <div class="n rv"><b><span data-c="150">0</span><sup>+</sup></b><span class="mono">Offices designed &amp; built</span></div>
    <div class="n rv"><b><span data-c="100">0</span><sup>%</sup></b><span class="mono">Clients who come back for their next office</span></div>
  </div>
</section>`;
const mqSec = () => `<div class="mq" aria-label="Our vendor partners"><span class="mono">Our vendor partners</span><div class="mq-t" id="mq"></div></div>`;
const procSec = (P, label) => `<section class="proc">
  <div class="wrap">
    <div class="proc-head"><h2 class="disp">Four steps, <em>60 days.</em></h2><span class="mono" style="color:var(--mut)">${label}</span></div>
    <div>
${[['Site visit &amp; requirements', 'Our team visits your site to understand your needs, ideas and preferences first-hand.', 'IMG_20200919_173518-scaled.jpg'],
   ['Design &amp; planning', 'Share your ideas with us, or let our designers suggest layouts, materials and 3D views made for your team.', 'pooja-singh-CJVG-BAz4-k-unsplash-scaled.jpg'],
   ['Budget-friendly quote', 'A detailed quote based on your needs, always keeping your budget in mind.', 'ADI8376-scaled.jpg'],
   ['Build &amp; handover', 'We build your office with care and quality checks at every stage, then hand over a space you will be proud of.', 'IMG_1675-scaled.jpg']]
  .map(([h, p, im], i) => `      <article class="pcard"><span class="k">0${i + 1}</span><div><h3>${h}</h3><p>${p}</p><span class="mono">Step ${i + 1} of 4</span></div><div class="ph"><img src="${P.img('images/' + im)}" alt="" loading="lazy"></div></article>`).join('\n')}
    </div>
  </div>
</section>`;

const ADVANTAGES = [
  ['Clear process, full transparency', 'From start to finish you always know where your project stands, with live progress updates on a simple online dashboard.'],
  ['Design that works', 'Offices that look good and are easy to work in — planned around your workflow, productivity and company culture.'],
  ['One expert team', 'Architects, interior designers and workspace planners work together, so your project fits your industry and needs.'],
  ['Local and relevant', 'We use local materials and regional design touches, so the office feels right for your people and your visitors.'],
  ['Support after handover', 'You get user guides, a maintenance kit and space-planning tips, so your team settles in quickly and confidently.'],
];
const FAQ = city => [
  ['What makes Morphora Spaces different from other office interior companies?', 'We go beyond looks. Our team combines creative interior design with smart space planning to deliver offices that work well, match your brand and are ready for the future — all within 60 days.'],
  ['How long does it take to design and build an office?', 'We deliver complete office projects in 60 days. Design, buying materials and construction are all planned together, so there is very little downtime for your business.'],
  city ? [`Do you work in ${city}?`, `Yes. We work with clients in ${city} and across India. Our team manages projects on-site or remotely, depending on the location and size.`]
    : ['Do you work outside Mumbai and Thane?', 'Yes. We work with clients across Mumbai, Thane, Navi Mumbai and other major cities in India. Our team manages projects on-site or remotely, depending on the location and size.'],
  ['What is included in your Design &amp; Build service?', 'Everything from the first idea, 3D views and layout planning to electrical and civil work, furniture buying and final execution — one team handles it all for you.'],
  ['Can you help us find the right office space first?', 'Yes. Our Real Estate Advisory team helps you find, check and finalise the right office location, so the space suits your business before we start designing.'],
  ['What kind of businesses do you work with?', 'Corporate offices, startups, co-working spaces and professional firms. Every project is planned around the company’s goals, work culture and industry needs.'],
];

/* ----------------------------------------------------------------- pages */
const pages = [];
const add = (p, o) => pages.push([p, o]);

// ---- About ----
add('about', P => ({
  title: 'About Us — Morphora Spaces', desc: 'Morphora Spaces is an office interior company in Thane that designs and builds workplaces that look good, work well and are delivered on time.', active: 'about',
  body: [
    phero(P, { tmq: ['We listen', 'We deliver', 'With purpose', 'Since day one'], crumb: [['About']], h1: 'We listen. We deliver. <em>With purpose.</em>', lead: 'Morphora Spaces is an office interior company based in Thane. We design and build workplaces that look good, work well and are delivered on time.', btn2: ['See our work', P.L('portfolio')], img: P.media('wp-content/uploads/2025/10/slidebean-TpGIpUF67po-unsplash-scaled.jpg') }),
    splitSec('( 01 — Who we are )', 'Design with <em>a clear purpose.</em>', `<p>We don’t just decorate offices — we plan them around how your business really works. By mixing creative design with clear planning, we create spaces that help teams do their best work.</p>
<p>Every project is a practical answer to what our clients need today and where they want to grow. We see an office as a living system: it should adapt, inspire and lift the people who use it.</p>
<p>Our team of designers, architects, engineers and project experts can handle projects of any size, with a strong focus on quality, practical design and a look that lasts.</p>`),
    whySec('( 02 — Our values )', 'What we <em>stand for.</em>', [
      ['Empathy', 'We design for people, not just looks — how a space feels and how people use it matters as much as how it looks.'],
      ['Precision', 'No detail is too small. We believe the smallest choices make the biggest impression.'],
      ['Integrity', 'What we promise, we deliver. Honesty, openness and trust guide every conversation.'],
      ['Context matters', 'We design with intention. Spaces should not only look good — they should work better for the people in them.'],
      ['Innovation', 'We use new methods to create offices that are ready for the future: flexible, sustainable and smart.'],
    ], true),
    numsSec(),
    stepsSec('( 03 — What we do )', 'One partner, <em>three services.</em>', [
      [`<a href="${P.L('design-and-build')}">Design &amp; Build ↗</a>`, 'Complete office interiors — layout, 3D design, civil work, furniture and handover — in 60 days.'],
      [`<a href="${P.L('real-estate')}">Real Estate Advisory ↗</a>`, 'Help finding, checking and securing the right office space before you start designing.'],
      [`<a href="${P.L('pmc')}">Project Management ↗</a>`, 'Independent PMC that keeps your project on time, on budget and true to the design.'],
      [`<a href="${P.L('portfolio')}">Our work ↗</a>`, 'See offices we have delivered for companies like Lafarge Holcim, Codal and Ishan Tech.'],
    ]),
    mqSec(),
  ].join('\n\n'),
}));

// ---- Design & Build ----
add('design-and-build', P => ({
  title: 'Office Design & Build in 60 Days — Morphora Spaces', desc: 'Complete office interiors — planning, 3D design, civil and electrical work, furniture and handover — delivered by one team in 60 days.', active: 'design-and-build',
  body: [
    phero(P, { tmq: ['Plan', 'Design', 'Build', 'Hand over', 'In 60 days'], tmqDark: true, crumb: [['Design &amp; Build']], h1: 'Office design &amp; build, <em>in 60 days.</em>', lead: 'From the first idea and detailed planning to smooth execution and a quality finish — one team handles your whole office project, within 60 days.', btn2: ['See our work', P.L('portfolio')], img: P.img('images/ADI8340-scaled.jpg') }),
    splitSec('( 01 — The service )', 'Everything, <em>under one roof.</em>', `<p>Our Design &amp; Build service takes your office from an empty floor to a finished workplace. You work with one team, one budget and one fixed timeline — so there is no chasing separate designers, contractors and vendors.</p>
<p>We plan every area around how your team works, then build it with quality checks at every stage.</p>
<ul class="tick"><li>Concept and space planning</li><li>Realistic 3D views</li><li>Layout and furniture planning</li><li>Civil and electrical work</li><li>Furniture and material buying</li><li>Final execution and handover</li></ul>`),
    procSec(P, '( 02 — How we work )'),
    whySec('( 03 — Why choose us )', 'Why work <em>with us.</em>', ADVANTAGES, true),
    workSec(P, '( 04 — Our work )', 'Recent <em>projects.</em>', PROJECTS.slice(0, 4)),
    faqSec(P, '( 05 — FAQs )', FAQ()),
  ].join('\n\n'),
}));

// ---- Real Estate ----
add('real-estate', P => ({
  title: 'Real Estate Advisory — Find Your Office Space | Morphora Spaces', desc: 'From finding the right location to closing the deal, Morphora’s Real Estate Advisory helps you secure an office that fits your business and growth plans.', active: '',
  body: [
    phero(P, { tmq: ['Mumbai', 'Pune', 'Bengaluru', 'Hyderabad', 'Find your space'], crumb: [['Real Estate Advisory']], h1: 'Find your new <em>office space.</em>', lead: 'From finding the right location to closing the deal, our Real Estate Advisory team helps you secure an office that fits your business, your brand and your growth plans — clearly and quickly.', img: P.media('wp-content/uploads/2025/10/pooja-singh-L4Cev9HX9aU-unsplash-scaled.jpg') }),
    splitSec('( 01 — Corporate real estate )', 'The right space, <em>from day one.</em>', `<p>We help you shortlist and secure commercial properties that match how you work today and where you want to grow.</p>
<p>We look at your space needs, team size, work style and expansion plans, then recommend the best locations — so you are set up for success from day one. When you are ready, the same team can design and build the office or manage the project for you.</p>
<h3>Available inventory</h3>
<div class="chips" style="margin-top:18px">${['bengaluru', 'hyderabad', 'mumbai', 'pune'].map(c => `<a href="${P.L('office-interior-designer-in-' + c)}">${CITIES.find(x => x.slug === c).name}</a>`).join('')}</div>`),
    whySec('( 02 — Why choose us )', 'What you <em>gain.</em>', [
      ['Market insights', 'We use up-to-date market information to guide your choice, so you don’t just pick a space — you pick an advantage.'],
      ['Ready-to-use spaces', 'We show you options that are ready for fit-out right away, or that need only small changes.'],
      ['Negotiation support', 'We negotiate price and terms that protect your growth — lease flexibility, lock-in periods, expansion options and more.'],
      ['Clear cost comparison', 'Every shortlisted space comes with a side-by-side cost comparison, so you see the real value, not just the square footage.'],
      ['Compliance checked', 'We check each property for legal, structural and safety compliance — from fire NOCs to RERA and LEED-certified buildings.'],
    ], true),
    stepsSec('( 03 — Our process )', 'Support from <em>start to finish.</em>', [
      ['Discovery &amp; needs', 'We understand your business goals, team size, work style and growth plans to define your ideal space.'],
      ['Market research', 'We study real estate trends and compare locations to find the best options for your industry.'],
      ['Property shortlist', 'We shortlist properties based on value, room to grow, compliance and budget — clear options, no guesswork.'],
      ['Final recommendation', 'You get a clear proposal with checked properties, deal advice and a plan ready for design or project management.'],
    ]),
    faqSec(P, '( 04 — FAQs )', FAQ()),
  ].join('\n\n'),
}));

// ---- PMC ----
add('pmc', P => ({
  title: 'Project Management Consultancy (PMC) — Morphora Spaces', desc: 'Morphora’s PMC service keeps your office project on time, on budget and true to the design — even when we are not the designers.', active: '',
  body: [
    phero(P, { tmq: ['On time', 'On budget', 'On site', 'No surprises'], tmqDark: true, crumb: [['Project Management']], h1: 'Project management, <em>done right.</em>', lead: 'Our Project Management Consultancy (PMC) makes sure your project is delivered quickly, clearly and under control — even when we are not the designers.', img: P.media('wp-content/uploads/2025/10/MicrosoftTeams-image-48-1024x731.jpg') }),
    splitSec('( 01 — The service )', 'You stay in control, <em>we stay on site.</em>', `<p>Our PMC team makes sure your project is delivered accurately, on time, on budget and without surprises.</p>
<p>We coordinate vendors, check quality and keep the work moving — so you stay in control without having to be on site every day.</p>`),
    whySec('( 02 — Why choose us )', 'What you <em>gain.</em>', [
      ['The build matches the design', 'What gets built on site matches the approved drawings — no changes or misunderstandings.'],
      ['Checked bills', 'Every vendor bill is checked against the work done on site, so you only pay for what is delivered.'],
      ['Snag-free handover', 'We find and fix problems before handover, so you don’t inherit unfinished or faulty work.'],
      ['Milestone tracking', 'The timeline is split into clear milestones, so progress is easy to measure and track.'],
      ['Engineers on site', 'Qualified PMC engineers supervise the site every day to make sure the work is technically right.'],
      ['Support after handover', 'We help with documents, warranty checks and any fixes or vendor closure after handover.'],
    ], true),
    stepsSec('( 03 — Our process )', 'A clear system <em>that works.</em>', [
      ['Site study', 'We visit the site and talk to your team to understand your vision, space limits, technical needs and timeline.'],
      ['Planning &amp; costing', 'We prepare layout options, a detailed bill of quantities (BOQ) and vendor sourcing with clear costs.'],
      ['Work monitoring', 'We coordinate contractors and check quality on site. You track progress through live dashboards and reports.'],
      ['Final handover', 'We hand over the project with user guides, maintenance schedules and support for the long term.'],
    ]),
    workSec(P, '( 04 — Our work )', 'Projects we have <em>managed.</em>', PROJECTS.filter(p => /Managed/.test(p.by))),
  ].join('\n\n'),
}));

// ---- Portfolio ----
add('portfolio', P => ({
  title: 'Portfolio — Offices We Have Delivered | Morphora Spaces', desc: 'A selection of offices designed, built and managed by Morphora Spaces across Mumbai, Thane and Ahmedabad.', active: 'portfolio',
  body: [
    phero(P, { badge: true, tmq: ['Lafarge Holcim', 'Codal', 'Festival', 'Ishan Tech', 'Gro Value', 'I &amp; I Fidelis'], crumb: [['Portfolio']], h1: 'Our work, <em>delivered.</em>', lead: 'A selection of offices we have designed, built and managed — from compact studios to 18,000 sq ft corporate floors.' }),
    `<section class="sec" style="padding-top:20px">
  <div class="wrap">
    <div class="work-grid">
${PROJECTS.map(p => '      ' + wcard(P, p)).join('\n')}
    </div>
    <p class="note">${DISCLAIMER}</p>
  </div>
</section>`,
    numsSec(),
  ].join('\n\n'),
}));

// ---- Project pages ----
for (const pr of PROJECTS) {
  add('portfolio/' + pr.slug, P => {
    const h = read('portfolio/' + pr.slug + '/index.html');
    const a = h.indexOf('>Gallery<'), b = h.indexOf('See More Work', a);
    const region = a >= 0 ? h.slice(a, b > a ? b : undefined) : '';
    const seen = new Set(), gal = [];
    for (const m of region.matchAll(/wp-content\/uploads\/[^"' )?]+?\.(?:jpe?g|png|webp)/gi)) {
      const rel = m[0], key = path.posix.dirname(rel) + '/' + imgBase(path.posix.basename(rel));
      if (seen.has(key) || /logo/i.test(rel)) continue; seen.add(key);
      const thumb = pickImg(rel, ['768x', '1024x', 'scaled', 'orig']), full = pickImg(rel, ['1536x', '1024x', 'scaled', 'orig']);
      if (thumb) gal.push([P.media(thumb), P.media(full || thumb)]);
    }
    const others = PROJECTS.filter(x => x !== pr).slice(0, 3);
    return {
      title: `${pr.name}, ${pr.city} — Office Interior Project | Morphora Spaces`, desc: `${pr.name} office in ${pr.city}: a ${pr.size} office interior project. ${pr.by}.`, active: 'portfolio',
      body: [
        phero(P, { tmq: [esc(pr.name), pr.city, pr.size, 'Delivered'], crumb: [['Portfolio', 'portfolio'], [esc(pr.name)]], h1: `${esc(pr.name)}, <em>${pr.city}.</em>`, lead: `A ${pr.size} office in ${pr.city}. ${pr.by}.`, cta: 'Plan a similar office', img: P.img(pr.img), imgAlt: `${pr.name} office` }),
        `<section class="sec" style="padding-top:60px">
  <div class="wrap">
    <div class="facts"><div><span class="mono">Client</span><b>${esc(pr.name)}</b></div><div><span class="mono">Location</span><b>${pr.city}</b></div><div><span class="mono">Area</span><b>${pr.size}</b></div><div><span class="mono">Delivery</span><b>${esc(pr.by)}</b></div></div>
  </div>
</section>`,
        gal.length ? `<section class="sec" style="padding-top:0">
  <div class="wrap">
    <div class="sec-head"><div><span class="mono lbl">( Gallery )</span><h2 class="disp">Inside the <em>office.</em></h2></div><span class="mono" style="color:var(--mut)">${gal.length} photos · click to enlarge</span></div>
    <div class="gal">
${gal.map(([t, f]) => `      <a href="${f}"><img src="${t}" alt="${esc(pr.name)} office interior" loading="lazy"></a>`).join('\n')}
    </div>
    <p class="note">${DISCLAIMER}</p>
  </div>
</section>` : '',
        workSec(P, '( More work )', 'Other <em>projects.</em>', others, true),
      ].join('\n\n'),
    };
  });
}

// ---- Resources (blog list) ----
add('resources', P => {
  const [first, ...rest] = POSTS;
  const cats = [...new Set(POSTS.map(p => p.cat))].sort();
  return {
    title: 'Resources — Office Design Guides & Articles | Morphora Spaces', desc: 'Guides, tips and articles to help you plan, design and build a better office.', active: 'resources',
    body: [
      phero(P, { tmq: ['Ideas', 'Guides', 'Trends', 'Costs', 'Planning tips'], crumb: [['Resources']], h1: 'Ideas for <em>better offices.</em>', lead: `Guides, tips and articles to help you plan, design and build a better office. ${POSTS.length} articles and counting.`, btns: false }),
      `<section class="sec" style="padding-top:20px">
  <div class="wrap">
    <a class="feat rv" href="${P.L('resources/' + first.slug)}"><div class="ph" data-tilt><img src="${postThumb(P, first)}" alt=""></div><div><span class="mono" style="color:var(--plum)">Latest · ${esc(first.cat)}</span><h2>${esc(first.title)}</h2><p>${esc(first.desc)}</p><span class="post"><span class="date">${fmtDate(first.date)}</span></span></div></a>
${cats.length > 2 ? `    <div class="chips filters" role="group" aria-label="Filter articles"><button class="on" data-cat="*">All</button>${cats.map(c => `<button data-cat="${esc(c)}">${esc(c)}</button>`).join('')}</div>\n` : ''}    <div class="blog-grid all">
${rest.map(p => '      ' + postCard(P, p, true)).join('\n')}
    </div>
  </div>
</section>`,
    ].join('\n\n'),
  };
});

// ---- Articles ----
for (const post of POSTS) {
  add('resources/' + post.slug, P => {
    let body = clean(post.raw, P);
    // live posts repeat the featured image and the title at the top of the content — drop the repeats
    const norm = s => text(s).toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (post.og) { const ob = imgBase(path.posix.basename(post.og)); body = body.replace(/<img [^>]*>/g, m => (m.includes('/' + ob + '-') || m.includes('/' + ob + '.')) ? '' : m); }
    body = body.replace(/<h2>([\s\S]*?)<\/h2>/, (m, t) => norm(t) === norm(post.title) ? '' : m).replace(/^(\s|<p>\s*<\/p>)+/, '');
    const words = text(body).split(' ').length;
    const hero = post.og && pickImg(post.og, ['1536x', '1024x', 'scaled', 'orig']);
    const related = [...POSTS.filter(p => p !== post && p.cat === post.cat), ...POSTS.filter(p => p !== post && p.cat !== post.cat)].slice(0, 3);
    return {
      title: `${post.title} | Morphora Spaces`, desc: post.desc || post.title, active: 'resources', ogType: 'article',
      body: [
        `<section class="art-head">
  <div class="wrap"><div class="in">
    ${crumbs(P, [['Resources', 'resources'], [esc(post.cat)]])}
    <h1 class="disp">${esc(post.title)}</h1>
    <div class="art-meta"><span>${fmtDate(post.date)}</span><span>${Math.max(2, Math.round(words / 220))} min read</span><span>By Morphora Spaces</span></div>
  </div></div>
</section>`,
        hero ? `<section class="art-img"><div class="wrap"><div><img src="${P.media(hero)}" alt=""></div></div></section>` : '',
        `<section class="art-body">
  <div class="wrap">
    <article class="prose">
${body}
    </article>
    <aside class="art-side">
      <span class="mono">Planning a new office?</span>
      <p>Talk to our team about design, build or project management. We deliver complete offices in 60 days.</p>
      <div class="btns"><a class="btn" href="#contact">Get a free consultation</a><a class="btn ghost" href="${P.L('resources')}">More articles</a></div>
    </aside>
  </div>
</section>`,
        blogSec(P, '( Keep reading )', related),
      ].join('\n\n'),
    };
  });
}

// ---- Contact ----
add('contact', P => ({
  title: 'Contact Us — Morphora Spaces', desc: 'Call, email or visit Morphora Spaces in Thane. Tell us about your office and we will call you back within one working day.', active: 'contact',
  footLabel: '( Send an enquiry )', footH2: 'Tell us about <em>your office.</em>',
  body: [
    phero(P, { badge: true, tmq: ['Let’s talk', '+91 77191 55155', 'hello@morphoraspaces.com', 'Thane'], tmqDark: true, crumb: [['Contact']], h1: 'Let’s talk about <em>your office.</em>', lead: 'Call us, send a message or visit our office in Thane. We usually reply within one working day.', cta: 'Send an enquiry' }),
    `<section class="sec" style="padding-top:10px">
  <div class="wrap">
    <div class="ccards">
      <a href="tel:+917719155155"><span class="mono">Call us</span><b>+91 77191 55155</b></a>
      <a href="mailto:hello@morphoraspaces.com"><span class="mono">Email</span><b>hello@morphoraspaces.com</b></a>
      <a href="https://wa.me/917719155155" target="_blank" rel="noopener"><span class="mono">WhatsApp</span><b>Chat with us ↗</b></a>
    </div>
    <div class="split" style="margin-bottom:40px"><div><span class="mono lbl">( Our office )</span><h2 class="disp" style="font-size:clamp(34px,4vw,60px)">Visit us <em>in Thane.</em></h2></div>
      <div class="prose"><p>1304, Dev Corpora, Eastern Express Highway, Khopat, Thane West, Thane, Maharashtra 400601 — near Cadbury Junction.</p><p><a href="https://maps.google.com/?q=Dev+Corpora+Cadbury+junction+Eastern+Express+Hwy+Thane+400601" target="_blank" rel="noopener">Get directions ↗</a></p></div></div>
    <div class="mapbox"><iframe src="https://maps.google.com/maps?q=Dev%20Corpora%20Cadbury%20junction%2C%20Eastern%20Express%20Hwy%2C%20Thane%2C%20Maharashtra%20400601&amp;t=m&amp;z=16&amp;output=embed&amp;iwloc=near" title="Morphora Spaces office on the map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
  </div>
</section>`,
  ].join('\n\n'),
}));

// ---- Careers ----
add('careers', P => ({
  title: 'Careers — Join Morphora Spaces', desc: 'Join Morphora Spaces. We are looking for creative people in interior design, project management and architecture.', active: '',
  body: [
    phero(P, { tmq: ['Interior design', 'Project management', 'Architecture', 'Site execution'], crumb: [['Careers']], h1: 'We’re <em>hiring.</em>', lead: 'Join Morphora Spaces, where creativity meets practical design. We are looking for people who love turning workplaces into great places to work.', btns: false, img: P.media('wp-content/uploads/2025/10/slidebean-14s9hyeWOjM-unsplash-scaled.jpg') }),
    splitSec('( Open to )', 'Who we’re <em>looking for.</em>', `<p>We are always happy to hear from creative and committed people in:</p>
<ul class="tick"><li>Interior design</li><li>Project management</li><li>Architecture</li><li>Site execution</li></ul>
<h3>How to apply</h3>
<p>Send your updated CV and portfolio to <a href="mailto:hello@morphoraspaces.com?subject=Job%20application">hello@morphoraspaces.com</a>. Tell us which role you are interested in and a little about your work.</p>
<div class="btns" style="margin-top:26px"><a class="btn" href="mailto:hello@morphoraspaces.com?subject=Job%20application">Send your CV</a><a class="btn ghost" href="${P.L('about')}">About us</a></div>`),
  ].join('\n\n'),
}));

// ---- Legal ----
for (const [slug, name] of [['privacy-policy', 'Privacy Policy'], ['terms-and-conditions', 'Terms and Conditions']]) {
  add(slug, P => {
    const h = read(slug + '/index.html');
    const best = widgets(h, 'text-editor.default').sort((a, b) => text(b).length - text(a).length)[0] || '';
    return {
      title: `${name} — Morphora Spaces`, desc: `${name} for the Morphora Spaces website.`, active: '',
      body: [
        phero(P, { crumb: [[name]], h1: name, lead: 'Please read this page carefully. If you have any questions, email us at hello@morphoraspaces.com.', btns: false }),
        `<section class="sec" style="padding-top:20px"><div class="wrap"><div class="prose">\n${clean(best, P)}\n</div></div></section>`,
      ].join('\n\n'),
    };
  });
}

// ---- Locations index ----
add('locations', P => ({
  title: 'Locations — Office Interior Designers Across India | Morphora Spaces', desc: `Morphora Spaces designs and builds offices across India, including Mumbai, Thane, Pune, Bengaluru, Ahmedabad and Hyderabad.`, active: '',
  body: [
    phero(P, { tmq: ['Mumbai', 'Thane', 'Pune', 'Bengaluru', 'Ahmedabad', 'Hyderabad', 'Delhi'], crumb: [['Locations']], h1: 'Office interiors <em>across India.</em>', lead: `We design, build and manage offices for clients across India. Our team works on-site or remotely, depending on the location and size of your project.` }),
    panIndia(P, '( Where we work )'),
    `<section class="sec" style="padding-top:20px">
  <div class="wrap">
    <div class="loc-top">
${TOP_CITIES.map(s => `      <a href="${P.L('office-interior-designer-in-' + s)}">${CITIES.find(c => c.slug === s).name}<span>View ↗</span></a>`).join('\n')}
    </div>
    <div class="sec-head" style="margin-bottom:24px"><div><span class="mono lbl">( All cities )</span></div></div>
    <label class="loc-search"><span class="mono" style="color:var(--plum)">Find</span><input id="locq" type="search" placeholder="Type your city…" autocomplete="off" aria-label="Search cities"><span class="loc-count mono" id="locn">${CITIES.length} cities</span></label>
    <p class="loc-empty" id="loce" hidden>No match — we still work across India. <a href="#contact" style="color:var(--plum)">Ask us about your city ↗</a></p>
    <div class="loc-grid">
${CITIES.map(c => `      <a href="${P.L('office-interior-designer-in-' + c.slug)}">${c.name}</a>`).join('\n')}
    </div>
  </div>
</section>`,
  ].join('\n\n'),
}));

// ---- City pages ----
for (const c of CITIES) {
  add('office-interior-designer-in-' + c.slug, P => ({
    title: `Office Interior Designer in ${c.name} — Morphora Spaces`, desc: `Looking for an office interior designer in ${c.name}? Morphora Spaces designs and builds complete offices in ${c.name} in 60 days.`, active: '',
    body: [
      phero(P, { badge: true, tmq: [c.name, 'Design', 'Build', 'In 60 days'], crumb: [['Locations', 'locations'], [c.name]], h1: `Office interior designer in <em>${c.name}.</em>`, lead: `Looking for an office interior designer in ${c.name}? We design and build complete offices in ${c.name} — from the first layout to the final handover, in just 60 days.`, btn2: ['See our work', P.L('portfolio')], img: P.img('images/ADI8415-scaled.jpg'), imgAlt: 'Office interior by Morphora Spaces' }),
      splitSec('( 01 — Our approach )', `Offices that work, <em>in ${c.name}.</em>`, `<p>We don’t just design office interiors in ${c.name} — we rethink how your space should work. By mixing smart planning with good design, we create offices that look good and help your team do their best work.</p>
<p>With our 60-day delivery promise, your office is ready quickly without cutting corners on quality. Every space is planned around your needs today and how your company will grow.</p>
<ul class="tick"><li><a href="${P.L('design-and-build')}">Office design &amp; build</a></li><li><a href="${P.L('real-estate')}">Finding office space</a></li><li><a href="${P.L('pmc')}">Project management</a></li><li>Handover &amp; support</li></ul>`),
      whySec('( 02 — Why choose us )', 'Why work <em>with us.</em>', ADVANTAGES, true),
      numsSec(),
      procSec(P, '( 03 — How we work )'),
      workSec(P, '( 04 — Our work )', 'Recent <em>projects.</em>', PROJECTS.slice(0, 3), true),
      faqSec(P, '( 05 — FAQs )', FAQ(c.name)),
      blogSec(P, '( 06 — From our blog )', POSTS.slice(0, 3)),
    ].join('\n\n'),
    footLabel: `( Office interiors in ${c.name} )`,
  }));
}

/* ------------------------------------------------------------------ write */
// remove previously generated output (everything except the hand-made home, its images and shared assets)
const KEEP = new Set(['index.html', 'images', 'assets']);
for (const d of fs.readdirSync(SIG)) {
  if (KEEP.has(d)) continue;
  const p = path.join(SIG, d);
  // empty media/ rather than deleting it (the folder itself may be held open on Windows)
  if (d === 'media') { for (const c of fs.readdirSync(p)) fs.rmSync(path.join(p, c), { recursive: true, force: true }); }
  else fs.rmSync(p, { recursive: true, force: true });
}
// vendor logos used by the marquee in assets/site.js
for (const f of ['Saint-Gobain_logo.png', 'Armstrong-Logo.png', 'Honeywell.png', 'Legrand_.png', 'Polycab.png', 'DAIKIN-logo.png', 'Philips-logo-scaled.jpg', 'gyproc-logo.png', 'Asian-Paints.png', 'Greenlam-Logo.png', 'merino-laminates-ltd-vector-logo.png', 'Feather-Lite-Logo.jpg', 'Hikvision-logo-scaled.png', 'wipro-logo.jpg', 'Welspun-logo.jpg', 'CommScope-logo-scaled.jpg', 'kubik-logo-1-scaled.png', 'Windor-e1753389106844.png', 'Amardeep-Designs-scaled.png']) USED.add('wp-content/uploads/2025/07/' + f);
let n = 0;
for (const [p, fn] of pages) {
  const P = ctx(p), o = fn(P), out = path.join(SIG, p, 'index.html');
  fs.mkdirSync(path.dirname(out), { recursive: true });
  fs.writeFileSync(out, layout(P, o)); n++;
}

// ---- home: refresh shared header/footer and route old live-site links to the new pages ----
{
  const P = ctx(''), f = path.join(SIG, 'index.html');
  let s = fs.readFileSync(f, 'utf8');
  s = s.replace(/<!-- @header -->[\s\S]*?<!-- \/@header -->/, `<!-- @header -->\n${header(P, '')}\n<!-- /@header -->`);
  s = s.replace(/<!-- @map -->[\s\S]*?<!-- \/@map -->/, `<!-- @map -->\n${panIndia(P, '( 04 — Pan-India )')}\n<!-- /@map -->`);
  s = s.replace(/<!-- @footer -->[\s\S]*?<!-- \/@footer -->/, `<!-- @footer -->\n${footer(P, '( 10 — Contact us )')}\n<!-- /@footer -->`);
  s = s.replace(/href="(\.\.\/\.\.\/[^"]*)"/g, (m, h) => { const r = P.href(h); return r ? `href="${r.href}"` : m; });
  s = s.replace(/src="(?:\.\.\/\.\.\/|\.\/media\/)(?:wp-content\/uploads\/)?([^"]+)"/g, (m, r) => `src="${P.media('wp-content/uploads/' + r)}"`);
  s = s.replace('href="./index.html" data-i="0"', `href="${P.L('design-and-build')}" data-i="0"`);
  s = s.replace(/(<a class="svc-row" href=")[^"]*(" data-i="3")/, `$1${P.L('contact')}$2`);
  fs.writeFileSync(f, s);
}

// copy every image the pages use into design-3-signature/media/ so the site is self-contained on Vercel
let bytes = 0;
for (const rel of USED) {
  const src = path.join(REPO, rel), dst = path.join(SIG, 'media', rel.replace(/^wp-content\/uploads\//, ''));
  if (!fs.existsSync(src)) { console.warn('missing image', rel); continue; }
  fs.mkdirSync(path.dirname(dst), { recursive: true }); fs.copyFileSync(src, dst); bytes += fs.statSync(src).size;
}
console.log(`Copied ${USED.size} images into media/ (${(bytes / 1048576).toFixed(1)} MB).`);
console.log(`Built ${n} pages (+ home): ${POSTS.length} articles, ${CITIES.length} cities, ${PROJECTS.length} projects.`);
