#!/usr/bin/env node
/*
 * Link checker for the Signature site.
 *   node new_version/_build/check-links.js
 * Fails (exit 1) if any page has a broken local link/image, links back to an old
 * live-site page instead of a Signature page, or is not linked from any other page.
 */
const fs = require('fs');
const path = require('path');

const REPO = path.resolve(__dirname, '../..');
const SIG = path.join(REPO, 'new_version/design-3-signature');
const UPLOADS = path.join(REPO, 'wp-content');

const files = [];
(function walk(d) { for (const f of fs.readdirSync(d, { withFileTypes: true })) { const p = path.join(d, f.name); if (f.isDirectory()) walk(p); else if (f.name.endsWith('.html')) files.push(p); } })(SIG);

const inbound = new Map(files.map(f => [f, 0]));
const broken = [], escapes = [], liveLinks = [];
let checked = 0;

for (const file of files) {
  const html = fs.readFileSync(file, 'utf8').replace(/<script\b(?![^>]*\bsrc=)[\s\S]*?<\/script>/gi, '');
  for (const m of html.matchAll(/\s(href|src)="([^"]+)"/g)) {
    const [, kind, raw] = m;
    if (/^(mailto:|tel:|data:|#)/.test(raw)) continue;
    if (/^https?:\/\/(www\.)?morphoraspaces\.com/i.test(raw)) { liveLinks.push(`${rel(file)} -> ${raw}`); continue; }
    if (/^(https?:)?\/\//.test(raw)) continue;            // external site
    if (raw.startsWith('/_vercel/')) continue;           // injected by Vercel at deploy time
    checked++;
    let target = path.resolve(path.dirname(file), decodeURI(raw.split(/[?#]/)[0]));
    if (raw.endsWith('/')) target = path.join(target, 'index.html');
    if (!fs.existsSync(target)) { broken.push(`${rel(file)} -> ${raw}`); continue; }
    const inSig = target.startsWith(SIG + path.sep), inUploads = target.startsWith(UPLOADS + path.sep);
    if (!inSig && !inUploads) escapes.push(`${rel(file)} -> ${raw}`);
    if (kind === 'href' && inSig && target.endsWith('.html') && target !== file) inbound.set(target, (inbound.get(target) || 0) + 1);
  }
}
function rel(f) { return path.relative(SIG, f).replace(/\\/g, '/'); }

const home = path.join(SIG, 'index.html');
const orphans = [...inbound].filter(([f, n]) => n === 0 && f !== home).map(([f]) => rel(f));

console.log(`Pages: ${files.length} · local links/images checked: ${checked}`);
const report = (label, list) => { console.log(`${label}: ${list.length}`); list.slice(0, 25).forEach(x => console.log('   ' + x)); };
report('Broken links/images', broken);
report('Links to old live-site pages (outside Signature)', escapes);
report('Absolute links to morphoraspaces.com', liveLinks);
report('Pages not linked from anywhere', orphans);
process.exit(broken.length || escapes.length || liveLinks.length || orphans.length ? 1 : 0);
