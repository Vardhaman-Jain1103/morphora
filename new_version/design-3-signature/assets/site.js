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

  // ---- sticky header: always on screen, turns solid after 40px ----
  function onHdr() { hdr.classList.toggle('solid', scrollY > 40); }
  if (hdr) { addEventListener('scroll', onHdr, { passive: true }); onHdr(); }

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
  // PHP site: the footer is already in the page (rendered by the server), so wire it up straight away
  if (!footSlot && $('#cform')) initFooter();

  // ---- enquiries: the PHP site sets <body data-api="/api/enquiry/">; the static site has no backend and uses WhatsApp ----
  const API = document.body.dataset.api || '';
  const WA = document.body.dataset.wa || '917719155155';
  const waLink = text => 'https://wa.me/' + WA + '?text=' + encodeURIComponent(text);
  function sendEnquiry(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v == null ? '' : v));
    fd.append('page', location.pathname); fd.append('ref', document.referrer || '');
    const q = new URLSearchParams(location.search), utm = ['utm_source', 'utm_medium', 'utm_campaign'].map(k => q.get(k)).filter(Boolean).join(' / ');
    if (utm) fd.append('utm', utm);
    return fetch(API, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(r => r.json().catch(() => ({ ok: false })).then(j => { if (!r.ok || !j.ok) throw j; return j; }));
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

  // ---- enquiry form: saved by the PHP backend when there is one, otherwise handed to WhatsApp ----
  function initForm() {
  const form = $('#cform');
  if (form) form.onsubmit = e => {
    e.preventDefault();
    const d = Object.fromEntries(new FormData(form)), note = $('#cnote'), btn = form.querySelector('button[type=submit]');
    const say = (t, bad) => { note.textContent = t; note.style.color = bad ? 'var(--plum-l)' : ''; };
    if (!d.name.trim() || !d.phone.trim()) { say('Please enter your name and phone number.', true); return; }
    if (d.phone.replace(/\D/g, '').length < 10) { say('Please enter a valid phone number.', true); return; }
    const lines = [['Name', d.name], ['Company', d.company], ['Phone', d.phone], ['Email', d.email], ['City', d.city], ['Office size', d.size], ['Service', d.service], ['Message', d.msg]]
      .filter(x => x[1] && x[1].trim()).map(x => `${x[0]}: ${x[1].trim()}`);
    const wa = waLink('New enquiry from the website (' + document.title + ')\n\n' + lines.join('\n'));
    if (!API) { window.open(wa, '_blank', 'noopener'); say('Thank you! Please press send in WhatsApp to share your details.'); form.reset(); return; }
    btn.disabled = true; say('Sending…');
    sendEnquiry({ ...d, source: 'form' })
      .then(() => { form.reset(); say('Thank you! Our team will call you back within one working day.'); })
      .catch(j => { if (j && j.error) say(j.error, true); else { window.open(wa, '_blank', 'noopener'); say('We could not send that just now, so WhatsApp is opening with your details instead.'); } })
      .finally(() => { btn.disabled = false; });
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

  // ---- chat assistant (bottom-right on every page): asks a few questions, then hands off to WhatsApp ----
  // Options mirror the enquiry form in partials/footer.html — keep the two lists in sync.
  const CB_CITIES = ['Mumbai', 'Thane', 'Pune', 'Ahmedabad', 'Bengaluru'];
  const CB_SERVICES = ['Office design & build', 'Finding office space', 'Project management', 'Not sure yet'];
  const CB_SIZES = ['Under 2,000 sq ft', '2,000 – 5,000 sq ft', '5,000 – 10,000 sq ft', '10,000 – 25,000 sq ft', 'Over 25,000 sq ft'];
  const cb = document.createElement('div'); cb.className = 'cb';
  cb.innerHTML = `
    <div class="cb-nudge" hidden><button class="cb-nx" type="button" aria-label="Dismiss">✕</button><span>Planning a new office? Let’s talk.</span></div>
    <section class="cb-panel" role="dialog" aria-label="Chat with Morphora Spaces" aria-modal="false" hidden>
      <header class="cb-head"><i class="cb-av">M</i><div><strong>Morphora Spaces</strong><small><b></b>Usually replies within minutes</small></div><button class="cb-x" type="button" aria-label="Close chat">✕</button></header>
      <div class="cb-log" aria-live="polite"></div>
      <div class="cb-in"></div>
    </section>
    <button class="cb-btn" type="button" aria-label="Open chat" aria-expanded="false"><svg class="cb-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.2 3.6c-.5.4-1.3.1-1.3-.6V16A2.5 2.5 0 0 1 4 13.5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="8.5" cy="9.5" r="1" fill="currentColor"/><circle cx="12" cy="9.5" r="1" fill="currentColor"/><circle cx="15.5" cy="9.5" r="1" fill="currentColor"/></svg><svg class="cb-ic2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>`;
  document.body.append(cb);
  const cbP = cb.querySelector('.cb-panel'), cbLog = cb.querySelector('.cb-log'), cbIn = cb.querySelector('.cb-in'), cbB = cb.querySelector('.cb-btn'), cbN = cb.querySelector('.cb-nudge');
  const ss = { get(k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } }, set(k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} } };
  let cbData = {}, cbStarted = false;
  const cbScroll = () => { cbLog.scrollTop = cbLog.scrollHeight; };
  function cbMsg(text, who) { const m = document.createElement('div'); m.className = 'cb-m ' + (who || 'bot'); m.textContent = text; cbLog.append(m); cbScroll(); return m; }
  // bot replies after a short "typing" pause
  function cbSay(lines, then) {
    cbIn.innerHTML = '';
    const run = i => {
      if (i >= lines.length) { if (then) then(); return; }
      const t = document.createElement('div'); t.className = 'cb-m bot cb-typing'; t.innerHTML = '<i></i><i></i><i></i>'; cbLog.append(t); cbScroll();
      setTimeout(() => { t.remove(); cbMsg(lines[i]); run(i + 1); }, reduce ? 120 : 650);
    };
    run(0);
  }
  function cbText({ type = 'text', placeholder, check, chips }, done) {
    cbIn.innerHTML = '';
    if (chips) cbChips(chips, done, true);
    const f = document.createElement('form'); f.className = 'cb-f'; f.noValidate = true;
    f.innerHTML = `<input type="${type}" autocomplete="${type === 'tel' ? 'tel' : 'off'}" placeholder="${placeholder}" aria-label="${placeholder}"><button type="submit" aria-label="Send">↑</button>`;
    const inp = f.querySelector('input');
    f.onsubmit = e => {
      e.preventDefault();
      const v = inp.value.trim(), err = v ? check && check(v) : 'Please type an answer.';
      if (err) { inp.setCustomValidity(err); f.classList.add('bad'); f.dataset.err = err; inp.focus(); return; }
      done(v);
    };
    inp.oninput = () => { f.classList.remove('bad'); inp.setCustomValidity(''); };
    cbIn.append(f); setTimeout(() => inp.focus({ preventScroll: true }), 30);
  }
  function cbChips(opts, done, keep) {
    if (!keep) cbIn.innerHTML = '';
    const w = document.createElement('div'); w.className = 'cb-chips';
    opts.forEach(o => { const b = document.createElement('button'); b.type = 'button'; b.textContent = o; b.onclick = () => done(o); w.append(b); });
    cbIn.append(w);
  }
  const answer = v => cbMsg(v, 'me');
  const STEPS = [
    () => cbSay(['Hi! I’m here to help you plan your new office.', 'To start, what’s your name?'], () => cbText({ placeholder: 'Your name' }, v => { cbData.name = v; answer(v); next(); })),
    () => cbSay([`Nice to meet you, ${cbData.name.split(' ')[0]}. What’s the best phone number to reach you on?`], () => cbText({ type: 'tel', placeholder: '+91 98765 43210', check: v => v.replace(/\D/g, '').length < 10 ? 'Please enter a valid phone number (at least 10 digits).' : '' }, v => { cbData.phone = v; answer(v); next(); })),
    () => cbSay(['Which city is the office in?'], () => cbText({ placeholder: 'Or type another city', chips: CB_CITIES }, v => { cbData.city = v; answer(v); next(); })),
    () => cbSay(['What would you like help with?'], () => cbChips(CB_SERVICES, v => { cbData.service = v; answer(v); next(); })),
    () => cbSay(['Roughly how big is the office?'], () => cbChips([...CB_SIZES, 'Not sure'], v => { cbData.size = v; answer(v); next(); })),
    () => API ? cbFinish() : cbSay([`Thanks, ${cbData.name.split(' ')[0]}! Tap below to send your details to our team on WhatsApp. We’ll get back to you shortly.`], cbFinish),
  ];
  let cbStep = 0;
  const next = () => { cbStep++; if (STEPS[cbStep]) STEPS[cbStep](); };
  function cbFinish() {
    cbIn.innerHTML = '';
    const sum = document.createElement('dl'); sum.className = 'cb-sum';
    [['Name', cbData.name], ['Phone', cbData.phone], ['City', cbData.city], ['Service', cbData.service], ['Office size', cbData.size]].forEach(([k, v]) => { const dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = k; dd.textContent = v; sum.append(dt, dd); });
    cbLog.append(sum); cbScroll();
    const a = document.createElement('a'); a.className = 'cb-go'; a.target = '_blank'; a.rel = 'noopener';
    a.href = waLink('New enquiry from the website chat (' + document.title + ')\n\nName: ' + cbData.name + '\nPhone: ' + cbData.phone + '\nCity: ' + cbData.city + '\nService: ' + cbData.service + '\nOffice size: ' + cbData.size);
    a.textContent = 'Send on WhatsApp ↗';
    const r = document.createElement('button'); r.type = 'button'; r.className = 'cb-re'; r.textContent = 'Start over';
    r.onclick = () => { cbLog.innerHTML = ''; cbData = {}; cbStep = 0; STEPS[0](); };
    if (!API) { cbIn.append(a, r); return; }
    // PHP site: save the lead first; WhatsApp stays available as a second option
    const first = cbData.name.split(' ')[0];
    cbIn.innerHTML = '<p class="cb-wait">Sending your details…</p>';
    sendEnquiry({ source: 'chat', name: cbData.name, phone: cbData.phone, city: cbData.city, service: cbData.service, size: cbData.size })
      .then(() => cbSay([`Thanks, ${first}! Our team has your details and will call you within one working day.`, 'Prefer to chat now? You can also message us on WhatsApp.'], () => { a.textContent = 'Chat on WhatsApp ↗'; cbIn.innerHTML = ''; cbIn.append(a, r); }))
      .catch(() => cbSay([`Thanks, ${first}! Tap below to send your details to our team on WhatsApp.`], () => { cbIn.innerHTML = ''; cbIn.append(a, r); }));
  }
  function cbOpen(o) {
    cb.classList.toggle('on', o); cbP.hidden = !o; cbB.setAttribute('aria-expanded', o); cbB.setAttribute('aria-label', o ? 'Close chat' : 'Open chat');
    if (o) { cbN.hidden = true; ss.set('cbSeen', 1); if (!cbStarted) { cbStarted = true; STEPS[0](); } else { const i = cbIn.querySelector('input'); if (i) i.focus({ preventScroll: true }); } }
  }
  cbB.onclick = () => cbOpen(!cb.classList.contains('on'));
  cb.querySelector('.cb-x').onclick = () => { cbOpen(false); cbB.focus(); };
  cbN.querySelector('span').onclick = () => cbOpen(true);
  cbN.querySelector('.cb-nx').onclick = () => { cbN.hidden = true; ss.set('cbSeen', 1); };
  addEventListener('keydown', e => { if (e.key === 'Escape' && cb.classList.contains('on')) { cbOpen(false); cbB.focus(); } });
  if (!ss.get('cbSeen')) setTimeout(() => { if (!cb.classList.contains('on')) cbN.hidden = false; }, 6000);
})();
