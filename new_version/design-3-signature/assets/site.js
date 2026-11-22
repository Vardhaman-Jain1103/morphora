/* Morphora Spaces — shared behaviour for every Signature page */
(function () {
  const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
  const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
  // Site root (…/design-3-signature/) and its local copy of the live-site uploads, resolved from this script's own URL
  const ROOT = new URL('../', document.currentScript.src);
  const UPLOADS = new URL('media/', ROOT);

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

  // ---- font switcher (client showcase; delete this block + the matching CSS once a font is picked) ----
  // Each option sets the display (headings) and body fonts. ?font=1..7 in the URL preselects one; 0 = original Jost.
  const FONTS = [
    ['Original', 'Jost — current geometric', 'Jost', 'Jost'],
    ['Luxe Serif', 'Cormorant Garamond + Manrope', 'Cormorant Garamond', 'Manrope'],
    ['Editorial', 'Fraunces + DM Sans', 'Fraunces', 'DM Sans'],
    ['Studio', 'Syne + Inter', 'Syne', 'Inter'],
    ['Technical', 'Space Grotesk + IBM Plex Sans', 'Space Grotesk', 'IBM Plex Sans'],
    ['Clean Modern', 'Plus Jakarta Sans', 'Plus Jakarta Sans', 'Plus Jakarta Sans'],
    // free look-alikes for Megalito Slab (condensed geometric slab serif, commercial)
    ['Slab Condensed', 'Stint Ultra Condensed + IBM Plex Sans', 'Stint Ultra Condensed', 'IBM Plex Sans'],
    ['Slab Geometric', 'Rokkitt + IBM Plex Sans', 'Rokkitt', 'IBM Plex Sans'],
  ];
  const stack = (f, g) => f.split(',').map(n => `"${n.trim()}"`).join(',') + ',' + g;
  const right = $('header .right');
  if (right) {
    const fl = document.createElement('link'); fl.rel = 'stylesheet';
    fl.href = 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Manrope:wght@300;400;500&family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&family=DM+Sans:wght@300;400;500&family=Syne:wght@400;500;600&family=Inter:wght@300;400;500&family=Space+Grotesk:wght@300;400;500&family=IBM+Plex+Sans:wght@300;400;500&family=Plus+Jakarta+Sans:wght@300;400;500&family=Rokkitt:wght@300;400;500&family=Stint+Ultra+Condensed&display=swap';
    document.head.appendChild(fl);
    const store = { get() { try { return localStorage.getItem('msFont'); } catch (e) { return null; } }, set(v) { try { localStorage.setItem('msFont', v); } catch (e) {} } };
    const q = new URLSearchParams(location.search).get('font');
    const fsw = document.createElement('div'); fsw.className = 'fsw';
    fsw.innerHTML = `<button class="fsw-b" type="button" aria-haspopup="true" aria-expanded="false"><i>Aa</i><span>Fonts</span></button><div class="fsw-p" role="radiogroup" aria-label="Choose a font">${FONTS.map((f, i) => `<button type="button" role="radio" data-f="${i}"><b>0${i}</b><strong style='font-family:${stack(f[2], 'serif')}'>${f[0]}</strong><small style='font-family:${stack(f[3], 'sans-serif')}'>${f[1]}</small></button>`).join('')}</div>`;
    right.insertBefore(fsw, right.firstChild);
    const btn = fsw.querySelector('.fsw-b');
    function applyFont(i) {
      i = FONTS[i] ? +i : 0;
      const f = FONTS[i], r = document.documentElement.style;
      r.setProperty('--disp', stack(f[2], 'system-ui,sans-serif'));
      r.setProperty('--sans', stack(f[3], 'system-ui,sans-serif'));
      r.setProperty('--mono', stack(f[3], 'system-ui,sans-serif'));
      fsw.querySelectorAll('[data-f]').forEach(b => b.setAttribute('aria-checked', b.dataset.f == i));
      store.set(i);
    }
    function openFsw(o) { fsw.classList.toggle('on', o); btn.setAttribute('aria-expanded', o); }
    applyFont(q !== null ? q : store.get() || 0);
    btn.onclick = e => { e.stopPropagation(); openFsw(!fsw.classList.contains('on')); };
    fsw.querySelectorAll('[data-f]').forEach(b => b.onclick = () => { applyFont(b.dataset.f); dispatchEvent(new Event('resize')); });
    addEventListener('click', e => { if (!fsw.contains(e.target)) openFsw(false); });
    addEventListener('keydown', e => { if (e.key === 'Escape') openFsw(false); });
  }

  // ---- pan-India map: draw routes when in view; list rows and pins highlight each other ----
  const pan = $('#pan');
  if (pan) {
    const po = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { pan.classList.add('in'); po.disconnect(); } }), { threshold: .2 });
    po.observe(pan);
    const items = [...pan.querySelectorAll('[data-city]')];
    const hl = c => items.forEach(el => el.classList.toggle('on', !!c && el.dataset.city.split(' ').includes(c)));
    items.forEach(el => {
      const c = el.dataset.city.split(' ')[0];
      el.addEventListener('mouseenter', () => hl(c)); el.addEventListener('mouseleave', () => hl(null));
      el.addEventListener('focus', () => hl(c)); el.addEventListener('blur', () => hl(null));
    });
  }

  // ---- shared footer: ONE file (partials/footer.html) loaded into <div id="site-footer"> on every page ----
  const footSlot = $('#site-footer');
  if (footSlot) {
    fetch(new URL('partials/footer.html', ROOT)).then(r => { if (!r.ok) throw new Error(r.status); return r.text(); }).then(html => {
      const t = document.createElement('template'); t.innerHTML = html;
      // links in the file are relative to the site root; resolve them so they work from any sub-folder
      t.content.querySelectorAll('[href],[src]').forEach(el => {
        const a = el.hasAttribute('href') ? 'href' : 'src', v = el.getAttribute(a);
        if (!/^([a-z][a-z0-9+.-]*:|#|\/)/i.test(v)) el.setAttribute(a, new URL(v, ROOT).href);
      });
      if (footSlot.dataset.label) t.content.querySelector('[data-slot="label"]').textContent = footSlot.dataset.label;
      if (footSlot.dataset.title) t.content.querySelector('[data-slot="title"]').innerHTML = footSlot.dataset.title;
      footSlot.replaceWith(t.content);
      initFooter();
      if (location.hash) { const el = document.getElementById(location.hash.slice(1)); if (el) el.scrollIntoView(); }
    }).catch(err => console.warn('Footer could not be loaded:', err));
  }

  function initFooter() {
    const yr = $('#yr'); if (yr) yr.textContent = new Date().getFullYear();
    initForm();
  }

  // ---- cursor ----
  const cur = $('#cur');
  if (cur) {
    let mx = innerWidth / 2, my = innerHeight / 2, cx = mx, cy = my;
    addEventListener('pointermove', e => { mx = e.clientX; my = e.clientY; });
    (function loop() { cx += (mx - cx) * .2; cy += (my - cy) * .2; cur.style.transform = `translate(${cx}px,${cy}px)`; requestAnimationFrame(loop); })();
    $$('[data-cur]').forEach(el => { el.addEventListener('mouseenter', () => { cur.querySelector('span').textContent = el.dataset.cur; cur.classList.add('big'); }); el.addEventListener('mouseleave', () => cur.classList.remove('big')); });
  }

  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const canHover = matchMedia('(hover: hover)').matches;

  // ---- hero headings: split into words that rise in one after another ----
  function splitWords(el) {
    let i = 0;
    (function walk(n) {
      [...n.childNodes].forEach(c => {
        if (c.nodeType === 3) {
          const frag = document.createDocumentFragment();
          c.textContent.split(/(\s+)/).forEach(p => {
            if (!p) return;
            if (/^\s+$/.test(p)) { frag.append(p); return; }
            const o = document.createElement('span'), s = document.createElement('span');
            o.className = 'sw'; s.textContent = p; s.style.setProperty('--i', i++); o.append(s); frag.append(o);
          });
          c.replaceWith(frag);
        } else if (c.nodeType === 1) walk(c);
      });
    })(el);
  }
  if (!reduce) $$('.phero h1, .art-head h1').forEach(splitWords);

  // ---- inner-page blocks fade up in sequence; photos wipe in ----
  $$('.sec-head,.split>*,.steps>div,.tick li,.facts>div,.loc-top a,.ccards>*,.faq details,.prose>h2,.prose>h3,.prose>p,.prose>ul,.prose>ol,.prose>img,.note,.chips>*').forEach(el => el.classList.add('rv'));
  $$('.rv').forEach(el => {
    if (el.style.transitionDelay) return;
    const sib = [...el.parentElement.children].filter(c => c.classList.contains('rv')), k = sib.indexOf(el);
    if (k > 0) el.style.transitionDelay = (k % 6) * 90 + 'ms';
  });
  $$('.wcard .ph,.post .ph,.feat .ph,.gal a,.pcard .ph').forEach(el => el.classList.add('ir'));

  // ---- reveal + counters ----
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }), { threshold: .12 });
  $$('.rv').forEach(el => io.observe(el));
  // .ir photos are hidden with clip-path, and Chromium's IntersectionObserver counts a fully
  // clipped element as never visible — so check their position on scroll instead.
  $$('.gal a.ir').forEach((el, k) => { el.style.transitionDelay = (k % 3) * 120 + 'ms'; });
  let irs = $$('.ir');
  const irCheck = () => {
    if (!irs.length) return;
    const h = innerHeight * .92;
    irs = irs.filter(el => { if (el.getBoundingClientRect().top < h) { el.classList.add('in'); return false; } return true; });
  };
  addEventListener('scroll', irCheck, { passive: true }); addEventListener('resize', irCheck); irCheck();
  const co = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    const el = e.target, n = +el.dataset.c, t0 = performance.now();
    (function f(t) { const p = clamp((t - t0) / 2000); el.textContent = Math.round(n * (1 - Math.pow(1 - p, 4))); p < 1 && requestAnimationFrame(f); })(t0);
    co.unobserve(el);
  }), { threshold: .5 });
  $$('[data-c]').forEach(el => co.observe(el));

  // ---- enquiry form → WhatsApp (static site, no backend); runs once the shared footer is in the page ----
  function initForm() {
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

  // ---- scroll-driven effects: progress bar, hero parallax, process line ----
  const prog = document.createElement('div'); prog.className = 'prog'; document.body.append(prog);
  const par = $$('.pimg img,.art-img img'), lines = $$('.steps.line');
  (function frame() {
    const max = document.documentElement.scrollHeight - innerHeight;
    prog.style.transform = `scaleX(${max > 0 ? clamp(scrollY / max) : 0})`;
    if (!reduce) {
      par.forEach(im => {
        const r = im.parentElement.getBoundingClientRect();
        if (r.bottom < 0 || r.top > innerHeight) return;
        im.style.transform = `translateY(${((r.top + r.height / 2 - innerHeight / 2) / innerHeight) * -7}%) scale(1.12)`;
      });
    }
    lines.forEach(l => { const r = l.getBoundingClientRect(); l.style.setProperty('--p', clamp((innerHeight * .8 - r.top) / (r.height + innerHeight * .25))); });
    requestAnimationFrame(frame);
  })();

  // ---- 3D tilt on project / featured photos, magnetic buttons ----
  if (canHover && !reduce) {
    $$('[data-tilt]').forEach(el => {
      el.addEventListener('mousemove', e => { const r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5; el.style.transform = `perspective(900px) rotateY(${x * 7}deg) rotateX(${-y * 7}deg)`; });
      el.addEventListener('mouseleave', () => { el.style.transform = ''; });
    });
    $$('.btn,.hbtn,.phero .circle-btn').forEach(b => {
      b.addEventListener('mousemove', e => { const r = b.getBoundingClientRect(); b.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * .2}px,${(e.clientY - r.top - r.height / 2) * .3}px)`; });
      b.addEventListener('mouseleave', () => { b.style.transform = ''; });
    });
  }

  // ---- locations: live city search ----
  const lq = $('#locq');
  if (lq) {
    const items = $$('.loc-grid a'), cnt = $('#locn'), empty = $('#loce');
    lq.oninput = () => {
      const q = lq.value.trim().toLowerCase(); let n = 0;
      items.forEach(a => { const m = a.textContent.toLowerCase().includes(q); a.hidden = !m; n += m; });
      cnt.textContent = n + (n === 1 ? ' city' : ' cities'); empty.hidden = n > 0;
    };
  }

  // ---- blog category filter ----
  const fb = $$('.filters button');
  fb.forEach(b => b.onclick = () => {
    fb.forEach(x => x.classList.toggle('on', x === b));
    $$('.blog-grid.all .post').forEach(p => p.hidden = b.dataset.cat !== '*' && p.dataset.cat !== b.dataset.cat);
  });
})();
