// Página pública: carrito, pestañas, animaciones y envío del pedido. Sin dependencias.
(() => {
  'use strict';
  document.documentElement.classList.add('js');
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const DATA = JSON.parse($('#acs-data').textContent);
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const store = {
    get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* modo privado */ } },
  };
  const money = (n) => 'Bs ' + Number(n).toLocaleString('es-BO', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  // Catálogo por presentación
  const variants = new Map();
  for (const p of DATA.products) for (const v of p.variants) variants.set(v.id, { ...v, product: p.name });

  // ─── Métricas anónimas ───
  let visitor = store.get('acs-visitor', null);
  if (!visitor) { visitor = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(36).slice(2)); store.set('acs-visitor', visitor); }
  const track = (type, extra = {}) => {
    const body = JSON.stringify({ type, visitor_id: visitor, path: location.pathname, referrer: document.referrer, ...extra });
    try { fetch(DATA.api + 'evento.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body, keepalive: true }).catch(() => {}); } catch { /* sin red */ }
  };
  if (!document.querySelector('meta[name="robots"][content*="noindex"]')) track('page_view');

  // ─── Carrito (se guarda en el navegador) ───
  let cart = store.get('acs-cart', []).filter((i) => {
    const v = variants.get(i.variant_id);
    return v && v.availability !== 'agotado' && i.quantity >= 1;
  }).map((i) => ({ variant_id: i.variant_id, quantity: Math.min(500, Math.max(1, i.quantity | 0)) }));
  const save = () => { store.set('acs-cart', cart); render(); };
  const add = (id, qty) => {
    const it = cart.find((i) => i.variant_id === id);
    if (it) it.quantity = Math.min(500, it.quantity + qty); else cart.push({ variant_id: id, quantity: qty });
    save();
  };
  const totals = () => {
    let total = 0, unpriced = false, count = 0;
    for (const i of cart) { const v = variants.get(i.variant_id); count += i.quantity; if (v.price == null) unpriced = true; else total += v.price * i.quantity; }
    return { total: Math.round(total * 100) / 100, unpriced, count };
  };

  const drawer = $('#cart');
  const sheet = $('.cart-sheet', drawer);
  const form = $('form[data-view="form"]', drawer);
  const views = { empty: $('[data-view="empty"]', drawer), form, done: $('[data-view="done"]', drawer) };
  let view = null;
  const show = (name) => { view = name; for (const [k, el] of Object.entries(views)) el.hidden = k !== name; };

  function render() {
    const t = totals();
    for (const el of $$('[data-cart-count]')) {
      el.textContent = t.count; el.classList.toggle('hidden', !t.count); el.classList.toggle('grid', !!t.count);
    }
    const bar = $('#cart-bar');
    bar.hidden = !t.count || drawer.dataset.state === 'open';
    $('[data-bar-count]').textContent = t.count === 1 ? '1 producto' : `${t.count} productos`;
    $('[data-bar-total]').textContent = t.unpriced ? (t.total ? money(t.total) + ' +' : 'Por confirmar') : money(t.total);
    if (view === 'done') return;
    show(cart.length ? 'form' : 'empty');
    $('[data-items]', form).innerHTML = cart.map((i) => {
      const v = variants.get(i.variant_id);
      const pres = v.presentation === 'maple' ? 'Maple · 30 huevos' : 'Paquete · 10 maples';
      const line = v.price == null ? '<span class="text-xs font-semibold text-wood">Precio por confirmar</span>' : `<span class="tabular font-semibold text-forest">${money(v.price * i.quantity)}</span>`;
      return `<li class="flex items-center gap-3 rounded-2xl border border-line bg-paper p-3" data-id="${v.id}">
        <div class="egg h-10 w-8 shrink-0 bg-[radial-gradient(circle_at_35%_30%,#f7dcc0_0%,#d9a273_55%,#b67a4e_100%)]" aria-hidden="true"></div>
        <div class="min-w-0 flex-1"><p class="font-semibold text-forest">${esc(v.product)}</p><p class="text-xs text-ink-2">${pres}${v.price != null ? ' · ' + money(v.price) + ' c/u' : ''}</p><div class="mt-0.5">${line}</div></div>
        <div class="flex items-center rounded-full border border-line bg-cream" role="group" aria-label="Cantidad de ${esc(v.product)}">
          <button type="button" data-cart-step="-1" class="grid h-9 w-8 place-items-center rounded-l-full hover:bg-cream-2" aria-label="Menos">−</button>
          <span class="tabular w-7 text-center text-sm font-semibold">${i.quantity}</span>
          <button type="button" data-cart-step="1" class="grid h-9 w-8 place-items-center rounded-r-full hover:bg-cream-2" aria-label="Más">+</button>
        </div>
        <button type="button" data-remove class="grid h-9 w-9 place-items-center rounded-full text-ink-2 hover:bg-terracotta/10 hover:text-terracotta-2" aria-label="Quitar ${esc(v.product)}">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6.5 7l1 13h9l1-13"/></svg></button>
      </li>`;
    }).join('');
    $('[data-total]', form).textContent = t.unpriced && !t.total ? 'Por confirmar' : money(t.total);
    $('[data-total-label]', form).textContent = t.unpriced ? 'Total parcial' : 'Total';
    $('[data-unpriced]', form).classList.toggle('hidden', !t.unpriced);
  }

  $('[data-items]', form).addEventListener('click', (e) => {
    const li = e.target.closest('li[data-id]'); if (!li) return;
    const id = +li.dataset.id;
    const step = e.target.closest('[data-cart-step]');
    if (step) {
      const it = cart.find((i) => i.variant_id === id);
      it.quantity = Math.min(500, it.quantity + +step.dataset.cartStep);
      if (it.quantity < 1) cart = cart.filter((i) => i !== it);
    } else if (e.target.closest('[data-remove]')) cart = cart.filter((i) => i.variant_id !== id);
    else return;
    save();
    if (!cart.length) $('[data-view="empty"] a', drawer).focus();
  });

  // ─── Abrir y cerrar el panel ───
  let lastFocus = null;
  const focusables = () => $$('a[href],button:not([disabled]),input:not([tabindex="-1"]),select,textarea', sheet).filter((el) => el.offsetParent !== null);
  function openCart(source) {
    if (drawer.dataset.state === 'open') return;
    lastFocus = document.activeElement;
    if (view === 'done' && !cart.length) { view = null; }
    render();
    drawer.hidden = false; drawer.dataset.state = 'open';
    document.body.style.overflow = 'hidden';
    $('#cart-bar').hidden = true;
    sheet.focus();
    if (source) track('click_order', { source });
  }
  function closeCart() {
    if (drawer.dataset.state !== 'open') return;
    drawer.dataset.state = 'closing';
    setTimeout(() => {
      drawer.hidden = true; drawer.dataset.state = 'closed'; document.body.style.overflow = '';
      if (view === 'done') { view = null; }
      render();
      if (lastFocus && document.contains(lastFocus)) lastFocus.focus();
    }, reduced ? 0 : 240);
  }
  document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-open-cart]');
    if (o) { e.preventDefault(); closeMenu(); openCart(o.dataset.track || 'barra'); return; }
    if (e.target.closest('[data-close-cart]')) closeCart();
  });
  document.addEventListener('keydown', (e) => {
    if (drawer.dataset.state !== 'open') { if (e.key === 'Escape') closeMenu(); return; }
    if (e.key === 'Escape') { e.preventDefault(); closeCart(); }
    if (e.key === 'Tab') {
      const f = focusables(); if (!f.length) return;
      if (e.shiftKey && (document.activeElement === f[0] || document.activeElement === sheet)) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  });

  // ─── Lista de precios: cantidades y agregar ───
  for (const row of $$('.product-row')) {
    const id = +row.dataset.variant;
    const qtyEl = $('.qty', row); const btn = $('[data-add]', row);
    if (!btn) continue;
    let qty = 1;
    const sync = () => { qtyEl.textContent = qty; btn.setAttribute('aria-label', `Agregar ${qty} ${btn.dataset.pres}${qty > 1 ? (btn.dataset.pres === 'maple' ? 's' : 's') : ''} de ${btn.dataset.name} al pedido`); };
    row.addEventListener('click', (e) => {
      const s = e.target.closest('[data-step]');
      if (s) { qty = Math.min(500, Math.max(1, qty + +s.dataset.step)); sync(); }
    });
    btn.addEventListener('click', () => {
      add(id, qty);
      track('product_add', { variant_id: id });
      btn.classList.add('added'); btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg> Agregado';
      for (const c of $$('[data-cart-count]')) { c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump'); }
      clearTimeout(btn._t);
      btn._t = setTimeout(() => { btn.classList.remove('added'); btn.textContent = 'Agregar'; }, 1400);
      qty = 1; sync();
    });
  }

  // Métrica: productos vistos (una vez por presentación)
  if ('IntersectionObserver' in window) {
    const seen = new Set();
    const io = new IntersectionObserver((ents) => {
      for (const en of ents) if (en.isIntersecting) { const id = +en.target.dataset.variant; if (!seen.has(id)) { seen.add(id); track('product_view', { variant_id: id }); } io.unobserve(en.target); }
    }, { threshold: 0.6 });
    $$('.product-row').forEach((r) => io.observe(r));
  }

  // ─── Pestañas maple / paquete ───
  const tabs = $$('[role="tab"]');
  const selectTab = (tab, focus) => {
    for (const t of tabs) {
      const on = t === tab;
      t.setAttribute('aria-selected', on); t.tabIndex = on ? 0 : -1;
      $('#' + t.getAttribute('aria-controls')).hidden = !on;
    }
    if (focus) tab.focus();
  };
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => selectTab(t));
    t.addEventListener('keydown', (e) => {
      const k = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
      if (k) { e.preventDefault(); selectTab(tabs[(i + k + tabs.length) % tabs.length], true); }
      if (e.key === 'Home') { e.preventDefault(); selectTab(tabs[0], true); }
      if (e.key === 'End') { e.preventDefault(); selectTab(tabs[tabs.length - 1], true); }
    });
  });

  // ─── Formulario del pedido ───
  const saved = store.get('acs-cliente', {});
  for (const k of ['name', 'phone', 'zone_id', 'address', 'reference']) if (saved[k] && form.elements[k]) form.elements[k].value = saved[k];

  const setError = (name, msg) => {
    const input = form.elements[name]; const p = $('#e-' + name);
    if (input && input.setAttribute) { input.setAttribute('aria-invalid', msg ? 'true' : 'false'); if (msg && p) input.setAttribute('aria-describedby', 'e-' + name); else input.removeAttribute('aria-describedby'); }
    if (p) { p.textContent = msg || ''; p.classList.toggle('hidden', !msg); }
  };
  const serverError = (msg) => { const p = $('[data-server-error]', form); p.textContent = msg || ''; p.classList.toggle('hidden', !msg); p.classList.toggle('flex', !!msg); };
  for (const n of ['name', 'phone', 'address']) form.elements[n].addEventListener('input', () => setError(n, ''));
  for (const r of $$('input[name="zone_id"]', form)) r.addEventListener('change', () => setError('zone_id', ''));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    serverError('');
    const f = Object.fromEntries(new FormData(form));
    const errs = {};
    if ((f.name || '').trim().length < 2) errs.name = 'Escribe tu nombre.';
    if (!/^\d{7,15}$/.test((f.phone || '').replace(/[^\d]/g, '').replace(/^591(?=\d{8}$)/, ''))) errs.phone = 'Escribe un teléfono válido, por ejemplo 71234567.';
    if ((f.address || '').trim().length < 3) errs.address = 'Indica una dirección o punto de entrega.';
    if (f.zone_id === undefined) errs.zone_id = 'Elige tu zona para saber quién te atiende.';
    for (const n of ['name', 'phone', 'zone_id', 'address']) setError(n, errs[n]);
    const firstBad = Object.keys(errs)[0];
    if (firstBad) { (firstBad === 'zone_id' ? $('input[name="zone_id"]', form) : form.elements[firstBad]).focus(); return; }

    const btn = $('button[type="submit"]', form); const label = $('[data-submit-label]', form);
    btn.disabled = true; label.textContent = 'Guardando pedido…';
    try {
      const res = await fetch(DATA.api + 'pedido.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...f, items: cart }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) {
        for (const [k, m] of Object.entries(json.fields || {})) setError(k, m);
        serverError(json.error || 'No pudimos guardar tu pedido. Intenta de nuevo.');
        return;
      }
      store.set('acs-cliente', { name: f.name, phone: f.phone, zone_id: f.zone_id, address: f.address, reference: f.reference });
      cart = []; store.set('acs-cart', []);
      done(json.order);
    } catch {
      serverError('Sin conexión. Revisa tu internet e intenta de nuevo; tu pedido sigue aquí.');
    } finally {
      btn.disabled = false; label.textContent = 'Guardar y enviar por WhatsApp';
    }
  });

  function markWhatsApp(token) {
    fetch(DATA.api + 'whatsapp.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token }), keepalive: true }).catch(() => {});
  }

  function done(o) {
    view = 'done';
    render(); show('done');
    $('[data-done-code]', drawer).textContent = o.code;
    $('[data-done-items]', drawer).innerHTML = o.items.map((i) => {
      const pres = i.presentation === 'maple' ? (i.quantity === 1 ? 'maple' : 'maples') : (i.quantity === 1 ? 'paquete' : 'paquetes');
      return `<li class="flex justify-between gap-3 p-3 text-sm"><span>${i.quantity} ${pres} de ${esc(i.product)}</span><span class="tabular font-semibold">${i.line_total == null ? 'por confirmar' : money(i.line_total)}</span></li>`;
    }).join('') + `<li class="flex justify-between gap-3 p-3 font-bold text-forest"><span>${o.has_unpriced_items ? 'Total parcial' : 'Total'}</span><span class="tabular">${o.has_unpriced_items && !o.total ? 'por confirmar' : money(o.total)}</span></li>`;
    const wa = $('[data-done-wa]', drawer);
    $('[data-done-link]', drawer).href = DATA.orderPage + '?t=' + o.token;
    const note = $('[data-done-note]', drawer);
    if (!o.whatsapp_url) {
      wa.hidden = true;
      note.textContent = 'Guardamos tu pedido. Nuestro equipo te contactará para confirmarlo.';
      return;
    }
    wa.hidden = false; wa.href = o.whatsapp_url;
    wa.onclick = () => markWhatsApp(o.token);
    // Se intenta abrir WhatsApp directamente; solo se registra si el navegador lo permitió
    const w = window.open(o.whatsapp_url, '_blank');
    if (w) {
      try { w.opener = null; } catch { /* otro origen */ }
      markWhatsApp(o.token);
      note.textContent = `Guardamos tu pedido y abrimos WhatsApp con ${o.contact.name}. Envía el mensaje para que te confirmen precio, disponibilidad y entrega.`;
    } else {
      note.textContent = `Tu pedido está guardado. Toca “Enviar pedido por WhatsApp” para mandárselo a ${o.contact.name}.`;
    }
    wa.focus();
  }

  // ─── Cabecera, menú móvil y efectos ───
  const header = $('#site-header');
  const menuBtn = $('#menu-toggle'); const menu = $('#menu-movil');
  function closeMenu() { if (!menu.hidden) { menu.hidden = true; menuBtn.setAttribute('aria-expanded', 'false'); menuBtn.setAttribute('aria-label', 'Abrir menú'); header.classList.remove('menu-open'); } }
  menuBtn.addEventListener('click', () => {
    const open = menu.hidden;
    menu.hidden = !open; menuBtn.setAttribute('aria-expanded', String(open)); menuBtn.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
    header.classList.toggle('menu-open', open);
  });
  menu.addEventListener('click', (e) => { if (e.target.closest('a')) closeMenu(); });

  const parallax = reduced ? [] : $$('[data-parallax]');
  let ticking = false;
  const onScroll = () => {
    header.dataset.solid = String(scrollY > 40);
    for (const el of parallax) if (scrollY < innerHeight * 1.3) el.style.transform = `translate3d(0, ${scrollY * +el.dataset.parallax}px, 0)`;
    ticking = false;
  };
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();

  if ('IntersectionObserver' in window && !reduced) {
    const io = new IntersectionObserver((ents) => { for (const en of ents) if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }, { rootMargin: '0px 0px -8% 0px' });
    $$('[data-reveal]').forEach((el) => io.observe(el));
  } else $$('[data-reveal]').forEach((el) => el.classList.add('in'));

  for (const a of $$('[data-contact]')) a.addEventListener('click', () => track('contact_click', { source: a.dataset.contact }));

  render();
})();
