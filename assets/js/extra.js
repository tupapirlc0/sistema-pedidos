// v2: contadores animados, formulario de ventas y precio automático del paquete. Sin dependencias.
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fmt = (n, dec) => n.toLocaleString('es-BO', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  const bs = (n) => 'Bs ' + fmt(n, n % 1 ? 2 : 0);

  // Números que cuentan desde 0 cuando aparecen en pantalla (precios, stock)
  const countUp = (el) => {
    const end = parseFloat(el.dataset.count); if (!isFinite(end)) return;
    const dec = (el.dataset.count.split('.')[1] || '').length;
    if (reduced) { el.textContent = fmt(end, dec); return; }
    const t0 = performance.now(), dur = 900;
    const step = (t) => {
      const p = Math.min(1, (t - t0) / dur), k = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(end * k, dec);
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };
  const counters = $$('[data-count]');
  if (counters.length) {
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { countUp(e.target); io.unobserve(e.target); } }), { threshold: 0.4 });
      counters.forEach((c) => io.observe(c));
    } else counters.forEach(countUp);
  }

  // Ventas: el precio vigente se completa solo y se muestra el total
  const form = $('#sale-form');
  if (form) {
    const prices = JSON.parse(form.dataset.prices || '{}');
    const prod = $('#product_id', form), pres = $('#presentation', form), qty = $('#qty', form), price = $('#unit_price', form), out = $('#sale-total');
    let touched = false;
    const fill = () => {
      const p = prices[prod.value + '-' + pres.value];
      if (!touched) price.value = p != null ? String(p).replace('.', ',') : '';
      total();
    };
    const total = () => { out.textContent = bs((parseFloat(price.value.replace(',', '.')) || 0) * (parseInt(qty.value, 10) || 0)); };
    price.addEventListener('input', () => { touched = price.value !== ''; total(); });
    [prod, pres].forEach((el) => el.addEventListener('change', () => { touched = false; fill(); }));
    qty.addEventListener('input', total);
    fill();
  }

  // Precios del día: al escribir el precio del maple, el paquete se propone solo (10 maples − descuento)
  const priceForm = $('form[data-package-discount]');
  if (priceForm) {
    const disc = parseFloat(priceForm.dataset.packageDiscount) || 0;
    $$('[data-maple-of]', priceForm).forEach((maple) => {
      const pack = $('[data-pack-of="' + maple.dataset.mapleOf + '"]', priceForm); if (!pack) return;
      let auto = pack.value === '' || parseFloat(pack.value.replace(',', '.')) === Math.round((parseFloat(maple.value.replace(',', '.')) * 10 - disc) * 100) / 100;
      pack.addEventListener('input', () => { auto = false; });
      maple.addEventListener('input', () => {
        const m = parseFloat(maple.value.replace(',', '.'));
        if (auto && m > 0) { pack.value = String(Math.round((m * 10 - disc) * 100) / 100).replace('.', ','); pack.classList.add('ring-2'); setTimeout(() => pack.classList.remove('ring-2'), 600); }
      });
    });
  }
})();

// Ojo para ver la contraseña: se agrega solo a todos los campos de contraseña
(() => {
  'use strict';
  const eye = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  const eyeOff = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A9.9 9.9 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  document.querySelectorAll('input[type=password]:not([hidden])').forEach((inp) => {
    const wrap = document.createElement('span');
    wrap.className = 'pw-wrap';
    inp.parentNode.insertBefore(wrap, inp);
    wrap.appendChild(inp);
    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'pw-eye'; btn.innerHTML = eye;
    btn.setAttribute('aria-label', 'Mostrar contraseña'); btn.setAttribute('aria-pressed', 'false');
    btn.addEventListener('click', () => {
      const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.innerHTML = show ? eyeOff : eye;
      btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      btn.setAttribute('aria-pressed', String(show));
      inp.focus({ preventScroll: true });
    });
    // Al enviar el formulario se vuelve a ocultar (para que el navegador la guarde como contraseña)
    inp.form?.addEventListener('submit', () => { inp.type = 'password'; });
    wrap.appendChild(btn);
  });
})();
