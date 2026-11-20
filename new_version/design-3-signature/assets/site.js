/* Morphora Spaces — shared behaviour for every Signature page */
(function () {
  const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
  const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
  // Site root (…/design-3-signature/) and repo-level uploads, resolved from this script's own URL
  const ROOT = new URL('../', document.currentScript.src);
  const UPLOADS = new URL('../../wp-content/uploads/', ROOT);

  // ---- mobile menu ----
  const hdr = $('header'), mnav = $('#mnav'), mt = $('#menuT');
  function setMenu(o) {
    if (!mnav) return;
    mnav.classList.toggle('open', o); hdr.classList.toggle('open', o); document.body.classList.toggle('lock', o);
    mt.setAttribute('aria-expanded', o); mt.setAttribute('aria-label', o ? 'Close menu' : 'Open menu');
    $('#menuL').textContent = o ? 'Close' : 'Menu';
  }
  if (mt) mt.onclick = () => setMenu(!mnav.classList.contains('open'));
  $$('#mnav a').forEach(a => a.addEventListener('click', () => setMenu(false)));
  addEventListener('keydown', e => { if (e.key === 'Escape') { setMenu(false); closeLb(); } });
  addEventListener('resize', () => { if (innerWidth > 1000 && mnav && mnav.classList.contains('open')) setMenu(false); });

  // ---- sticky header: solid after 40px, hides on scroll down, returns on scroll up ----
  let lastY = scrollY;
  function onHdr() {
    const y = Math.max(0, scrollY);
    hdr.classList.toggle('solid', y > 40);
    if (!hdr.classList.contains('open') && Math.abs(y - lastY) > 4) { hdr.classList.toggle('hide', y > lastY && y > 400); lastY = y; }
  }
  if (hdr) { addEventListener('scroll', onHdr, { passive: true }); onHdr(); }

  // ---- footer year ----
  const yr = $('#yr'); if (yr) yr.textContent = new Date().getFullYear();

  // ---- cursor ----
  const cur = $('#cur');
  if (cur) {
    let mx = innerWidth / 2, my = innerHeight / 2, cx = mx, cy = my;
    addEventListener('pointermove', e => { mx = e.clientX; my = e.clientY; });
    (function loop() { cx += (mx - cx) * .2; cy += (my - cy) * .2; cur.style.transform = `translate(${cx}px,${cy}px)`; requestAnimationFrame(loop); })();
    $$('[data-cur]').forEach(el => { el.addEventListener('mouseenter', () => { cur.querySelector('span').textContent = el.dataset.cur; cur.classList.add('big'); }); el.addEventListener('mouseleave', () => cur.classList.remove('big')); });
  }

  // ---- reveal + counters ----
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .12 });
  $$('.rv').forEach(el => io.observe(el));
  const co = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    const el = e.target, n = +el.dataset.c, t0 = performance.now();
    (function f(t) { const p = clamp((t - t0) / 2000); el.textContent = Math.round(n * (1 - Math.pow(1 - p, 4))); p < 1 && requestAnimationFrame(f); })(t0);
    co.unobserve(el);
  }), { threshold: .5 });
  $$('[data-c]').forEach(el => co.observe(el));

  // ---- enquiry form → WhatsApp (static site, no backend) ----
  const form = $('#cform');
  if (form) form.onsubmit = e => {
    e.preventDefault();
    const d = Object.fromEntries(new FormData(form)), note = $('#cnote');
    if (!d.name.trim() || !d.phone.trim()) { note.textContent = 'Please enter your name and phone number.'; note.style.color = 'var(--plum-l)'; return; }
    const lines = [['Name', d.name], ['Company', d.company], ['Phone', d.phone], ['Email', d.email], ['City', d.city], ['Office size', d.size], ['Service', d.service], ['Message', d.msg]]
      .filter(x => x[1] && x[1].trim()).map(x => `${x[0]}: ${x[1].trim()}`);
    window.open('https://wa.me/917719155155?text=' + encodeURIComponent('New enquiry from the website (' + document.title + ')\n\n' + lines.join('\n')), '_blank', 'noopener');
    note.textContent = 'Thank you! Please press send in WhatsApp to share your details.'; note.style.color = ''; form.reset();
  };

  // ---- vendor partner logos (same set as the live site) ----
  const mq = $('#mq');
  if (mq) {
    const P = [['Saint-Gobain', 'Saint-Gobain_logo.png'], ['Armstrong', 'Armstrong-Logo.png'], ['Honeywell', 'Honeywell.png'], ['Legrand', 'Legrand_.png'], ['Polycab', 'Polycab.png'], ['Daikin', 'DAIKIN-logo.png'], ['Philips', 'Philips-logo-scaled.jpg'], ['Gyproc', 'gyproc-logo.png'], ['Asian Paints', 'Asian-Paints.png'], ['Greenlam', 'Greenlam-Logo.png'], ['Merino', 'merino-laminates-ltd-vector-logo.png'], ['Featherlite', 'Feather-Lite-Logo.jpg'], ['Hikvision', 'Hikvision-logo-scaled.png'], ['Wipro', 'wipro-logo.jpg'], ['Welspun', 'Welspun-logo.jpg'], ['CommScope', 'CommScope-logo-scaled.jpg'], ['Kubik', 'kubik-logo-1-scaled.png'], ['Windor', 'Windor-e1753389106844.png'], ['Amardeep Designs', 'Amardeep-Designs-scaled.png']];
    mq.innerHTML = [...P, ...P].map((p, i) => `<img src="${new URL('2025/07/' + p[1], UPLOADS)}" alt="${i < P.length ? p[0] : ''}" loading="lazy">`).join('');
  }

  // ---- gallery lightbox ----
  const gal = $$('.gal a');
  let lb, lbi = 0;
  function showLb(i) { lbi = (i + gal.length) % gal.length; lb.querySelector('img').src = gal[lbi].href; lb.classList.add('open'); }
  function closeLb() { if (lb) lb.classList.remove('open'); }
  if (gal.length) {
    lb = document.createElement('div'); lb.className = 'lb';
    lb.innerHTML = '<img alt=""><button class="x" aria-label="Close">Close ✕</button><button class="p" aria-label="Previous">←</button><button class="n" aria-label="Next">→</button>';
    document.body.append(lb);
    lb.onclick = e => { if (e.target === lb || e.target.classList.contains('x')) closeLb(); };
    lb.querySelector('.p').onclick = () => showLb(lbi - 1); lb.querySelector('.n').onclick = () => showLb(lbi + 1);
    gal.forEach((a, i) => a.addEventListener('click', e => { e.preventDefault(); showLb(i); }));
  }

  // ---- blog category filter ----
  const fb = $$('.filters button');
  fb.forEach(b => b.onclick = () => {
    fb.forEach(x => x.classList.toggle('on', x === b));
    $$('.blog-grid.all .post').forEach(p => p.hidden = b.dataset.cat !== '*' && p.dataset.cat !== b.dataset.cat);
  });
})();
