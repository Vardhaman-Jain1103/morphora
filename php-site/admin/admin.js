/* Morphora Spaces — admin behaviour: editor toolbar, uploads, gallery, slugs, SEO preview */
(function () {
  const $ = (s, el = document) => el.querySelector(s), $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const CSRF = ($('meta[name=csrf]') || {}).content || '';

  // confirm before destructive buttons
  $$('[data-confirm]').forEach(b => b.addEventListener('click', e => { if (!confirm(b.dataset.confirm)) e.preventDefault(); }));

  // warn before leaving an edited form unsaved
  const form = $('#editForm');
  if (form) {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', () => { dirty = false; });
    addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }

  // ---- uploads ----
  function upload(file) {
    const fd = new FormData(); fd.append('file', file); fd.append('_csrf', CSRF);
    return fetch('/admin/?r=upload', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json()).then(j => { if (!j.ok) throw new Error(j.error || 'Upload failed'); return j.url; });
  }

  // single image fields
  $$('[data-upload]').forEach(inp => inp.addEventListener('change', () => {
    const f = inp.files[0]; if (!f) return;
    const box = inp.closest('.img-field'), text = $('#' + inp.dataset.upload), img = $('img', box), lbl = inp.parentElement;
    lbl.firstChild.textContent = 'Uploading…';
    upload(f).then(url => { text.value = url; img.src = url; img.hidden = false; text.dispatchEvent(new Event('input', { bubbles: true })); })
      .catch(err => alert(err.message)).finally(() => { lbl.firstChild.textContent = 'Upload'; inp.value = ''; });
  }));
  $$('.img-field input:not([type=file])').forEach(t => t.addEventListener('change', () => { const img = $('img', t.closest('.img-field')); img.src = t.value; img.hidden = !t.value; }));

  // gallery fields: [[thumb, full], …] stored as JSON in a hidden input
  $$('.gal-field').forEach(box => {
    const hidden = $('#' + box.dataset.for), list = $('.gal-items', box);
    let items = []; try { items = JSON.parse(hidden.value) || []; } catch (e) {}
    let dragFrom = null;
    const save = () => { hidden.value = JSON.stringify(items); hidden.dispatchEvent(new Event('input', { bubbles: true })); };
    function draw() {
      list.innerHTML = '';
      items.forEach((it, i) => {
        const fig = document.createElement('figure'); fig.draggable = true;
        fig.innerHTML = `<img src="${it[0]}" alt=""><button type="button" title="Remove">✕</button>`;
        $('button', fig).onclick = () => { items.splice(i, 1); save(); draw(); };
        fig.ondragstart = () => { dragFrom = i; fig.classList.add('drag'); };
        fig.ondragend = () => fig.classList.remove('drag');
        fig.ondragover = e => e.preventDefault();
        fig.ondrop = e => { e.preventDefault(); if (dragFrom === null || dragFrom === i) return; const [m] = items.splice(dragFrom, 1); items.splice(i, 0, m); dragFrom = null; save(); draw(); };
        list.append(fig);
      });
    }
    draw();
    const inp = $('[data-gallery]', box), lbl = inp.parentElement;
    inp.addEventListener('change', async () => {
      const files = [...inp.files];
      for (let k = 0; k < files.length; k++) {
        lbl.firstChild.textContent = `Uploading ${k + 1} of ${files.length}…`;
        try { const url = await upload(files[k]); items.push([url, url]); save(); draw(); } catch (err) { alert(files[k].name + ': ' + err.message); }
      }
      lbl.firstChild.textContent = 'Add photos'; inp.value = '';
    });
  });

  // ---- HTML editor toolbar ----
  $$('.ed-bar').forEach(bar => {
    const ta = $('#' + bar.dataset.for), prev = $('.ed-prev', bar.parentElement);
    const wrap = (a, b, ph) => {
      const s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e) || ph || '';
      ta.setRangeText(a + sel + b, s, e, 'end'); ta.focus(); ta.dispatchEvent(new Event('input', { bubbles: true }));
    };
    const list = tag => {
      const s = ta.selectionStart, e = ta.selectionEnd, lines = (ta.value.slice(s, e) || 'Item').split(/\n+/).filter(Boolean);
      ta.setRangeText(`<${tag}>\n${lines.map(l => `  <li>${l.replace(/^[-*•\d.)\s]+/, '')}</li>`).join('\n')}\n</${tag}>`, s, e, 'end'); ta.focus();
    };
    bar.addEventListener('click', e => {
      const b = e.target.closest('button'); if (!b) return;
      const c = b.dataset.cmd;
      if (c === 'h2' || c === 'h3' || c === 'p') wrap(`<${c}>`, `</${c}>`, 'Text');
      else if (c === 'b') wrap('<strong>', '</strong>', 'bold text');
      else if (c === 'i') wrap('<em>', '</em>', 'text');
      else if (c === 'ul' || c === 'ol') list(c);
      else if (c === 'a') { const u = prompt('Link address (e.g. /contact/ or https://…)'); if (u) wrap(`<a href="${u.replace(/"/g, '&quot;')}">`, '</a>', 'link text'); }
      else if (c === 'img') {
        const f = document.createElement('input'); f.type = 'file'; f.accept = 'image/*';
        f.onchange = () => upload(f.files[0]).then(url => { const alt = prompt('Describe the image (for Google and screen readers)', '') || ''; wrap(`<img src="${url}" alt="${alt.replace(/"/g, '&quot;')}" loading="lazy">`, '', ''); }).catch(err => alert(err.message));
        f.click();
      } else if (c === 'preview') {
        const on = prev.hidden; prev.hidden = !on; ta.hidden = on; b.classList.toggle('on', on);
        if (on) prev.innerHTML = ta.value.replace(/<script[\s\S]*?<\/script>/gi, '').replace(/<!--sc:(\w+)[\s\S]*?-->/g, '<p style="padding:10px;border:1px dashed #c9b;border-radius:6px;color:#84a">[ live section: $1 ]</p>');
      }
    });
  });

  // ---- slugs follow the title until edited by hand ----
  $$('input[data-from]').forEach(slug => {
    const src = $('#' + slug.dataset.from); if (!src || slug.readOnly) return;
    let auto = slug.value === '';
    const make = s => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
    src.addEventListener('input', () => { if (auto) { slug.value = make(src.value); slug.dispatchEvent(new Event('input', { bubbles: true })); } });
    slug.addEventListener('input', e => { if (e.isTrusted) auto = slug.value === ''; });
  });

  // ---- character counters ----
  $$('[data-max]').forEach(el => {
    const lab = $('label[for="' + el.id + '"]'); if (!lab) return;
    const c = document.createElement('span'); c.className = 'count'; lab.append(c);
    const upd = () => { const n = el.value.length, m = +el.dataset.max; c.textContent = n + ' / ' + m; c.classList.toggle('over', n > m); };
    el.addEventListener('input', upd); upd();
  });

  // ---- Google result preview ----
  const serp = $('.serp');
  if (serp) {
    const t = $('#f_seo_title'), d = $('#f_seo_desc'), slug = $('input[name=slug]'), title = $('#f_title') || $('#f_name'), ex = $('#f_excerpt');
    const base = serp.dataset.url.replace(/[^/]*\/?$/, '');
    const upd = () => {
      $('.serp-t', serp).textContent = (t && t.value) || (title && title.value ? title.value + ' | Morphora Spaces' : 'Morphora Spaces');
      $('.serp-d', serp).textContent = (d && d.value) || (ex && ex.value) || 'Add an SEO description so Google shows the right summary for this page.';
      $('.serp-url', serp).textContent = serp.dataset.url.replace(/^https?:\/\//, '').replace(/\/$/, '').replace(/\//g, ' › ');
    };
    [t, d, slug, title, ex].forEach(el => el && el.addEventListener('input', upd)); upd();
  }
})();
