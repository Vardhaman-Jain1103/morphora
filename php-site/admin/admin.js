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

  // ---- SEO previews and focus-keyword analysis ----
  const seoPanel = $('[data-seo-panel]');
  if (seoPanel) {
    const serp = $('.serp', seoPanel), titleField = $('#f_seo_title'), descField = $('#f_seo_desc');
    const focus = $('#f_focus_keyword'), canonical = $('#f_canonical_url'), ogTitle = $('#f_og_title'), ogDesc = $('#f_og_desc'), ogImage = $('#f_og_image');
    const title = $('#f_title') || $('#f_name') || $('#f_slug'), excerpt = $('#f_excerpt');
    const source = seoPanel.dataset.source ? $('#f_' + seoPanel.dataset.source) : null;
    const score = $('[data-seo-score]', seoPanel), note = $('[data-seo-note]', seoPanel), checklist = $('[data-seo-checks]', seoPanel);
    const socialTitle = $('[data-social-title]', seoPanel), socialDesc = $('[data-social-desc]', seoPanel), socialImage = $('[data-social-image]', seoPanel);
    const clean = html => {
      const box = document.createElement('textarea');
      box.innerHTML = String(html || '').replace(/<(script|style)\b[\s\S]*?<\/\1>/gi, ' ').replace(/<[^>]*>/g, ' ');
      return box.value.replace(/\s+/g, ' ').trim();
    };
    const normalize = value => String(value || '').toLowerCase().replace(/&amp;/g, '&').replace(/[^a-z0-9]+/g, ' ').trim();
    const contains = (text, phrase) => {
      const haystack = normalize(text), needle = normalize(phrase);
      return !!needle && (` ${haystack} `).includes(` ${needle} `);
    };
    const update = () => {
      const baseTitle = title && title.value.trim() ? title.value.trim() : 'Morphora Spaces';
      const analysisTitle = titleField && titleField.value.trim() ? titleField.value.trim() : baseTitle;
      const heading = titleField && titleField.value.trim() ? titleField.value.trim() : baseTitle + ' | Morphora Spaces';
      const rawBody = source ? source.value : '';
      const text = clean(rawBody), words = text.split(/\s+/).filter(Boolean);
      const description = descField && descField.value.trim() ? descField.value.trim() : (excerpt && excerpt.value.trim() ? excerpt.value.trim() : text.slice(0, 160));
      const keyword = focus ? focus.value.trim() : '';
      const slugValue = $('#f_slug') ? $('#f_slug').value : '';
      const firstPart = words.slice(0, Math.max(1, Math.ceil(words.length * .1))).join(' ');
      const headings = [...rawBody.matchAll(/<h[1-6]\b[^>]*>([\s\S]*?)<\/h[1-6]>/gi)].map(match => clean(match[1])).join(' ');
      const imageTags = [...rawBody.matchAll(/<img\b[^>]*>/gi)].map(match => match[0]);
      const hasMissingAlt = imageTags.some(tag => !/\balt\s*=\s*["'][^"']+["']/i.test(tag));
      const links = [...rawBody.matchAll(/<a\b[^>]*href\s*=\s*(["'])(.*?)\1/gi)].map(match => match[2]);
      const hasInternalLink = links.some(href => {
        try { return new URL(href, location.origin).host === location.host; } catch (e) { return false; }
      });
      const checks = [
        [!!keyword, 'Set a focus keyword'],
        [contains(analysisTitle, keyword), 'Use the keyword in the SEO title'],
        [contains(description, keyword), 'Use the keyword in the description'],
        [contains(slugValue, keyword), 'Use the keyword in the URL'],
        [contains(firstPart, keyword), 'Mention the keyword near the start'],
        [contains(headings, keyword), 'Use the keyword in a heading'],
        [words.length >= 300, 'Write at least 300 words'],
        [analysisTitle.length >= 30 && analysisTitle.length <= 60, 'Keep the SEO title between 30–60 characters'],
        [description.length >= 120 && description.length <= 160, 'Keep the description between 120–160 characters'],
        [!hasMissingAlt, 'Add descriptive alt text to images'],
        [hasInternalLink, 'Add at least one internal link'],
      ];
      const passed = checks.filter(check => check[0]).length;
      const points = Math.round(passed / checks.length * 100);
      if (score) {
        score.textContent = keyword ? points + '/100' : 'N/A';
        score.className = keyword ? (points >= 80 ? 'good' : points >= 50 ? 'mid' : 'low') : '';
      }
      if (note) note.textContent = keyword ? `${passed} of ${checks.length} on-page checks passed. This is a guidance score, not a Google ranking.` : 'Add a focus keyword to calculate the on-page guidance score.';
      if (checklist) checklist.innerHTML = checks.map(([ok, label]) => `<li class="${ok ? 'ok' : 'todo'}"><b>${ok ? '✓' : '!'}</b><span>${label}</span></li>`).join('');
      if (serp) {
        $('.serp-t', serp).textContent = heading;
        $('.serp-d', serp).textContent = description || 'Add an SEO description so Google can show a useful summary.';
        const pageUrl = canonical && canonical.value.trim() ? canonical.value.trim() : serp.dataset.url;
        $('.serp-url', serp).textContent = pageUrl.replace(/^https?:\/\//, '').replace(/\/$/, '').replace(/\//g, ' › ');
      }
      if (socialTitle) socialTitle.textContent = ogTitle && ogTitle.value.trim() ? ogTitle.value.trim() : heading;
      if (socialDesc) socialDesc.textContent = ogDesc && ogDesc.value.trim() ? ogDesc.value.trim() : description;
      if (socialImage) {
        const image = ogImage && ogImage.value.trim();
        if (image) socialImage.src = image;
        else socialImage.removeAttribute('src');
        socialImage.hidden = !image;
      }
    };
    [titleField, descField, focus, canonical, ogTitle, ogDesc, ogImage, title, excerpt, source].forEach(el => {
      if (el) { el.addEventListener('input', update); el.addEventListener('change', update); }
    });
    update();
  }
})();
