// Panel: menú móvil, avisos, confirmaciones y editores de listas. Sin dependencias.
(() => {
  'use strict';
  const btn = document.getElementById('admin-menu');
  const nav = document.getElementById('admin-nav');
  if (btn && nav) btn.addEventListener('click', () => {
    const open = nav.classList.toggle('hidden') === false;
    btn.setAttribute('aria-expanded', String(open));
    btn.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
  });

  // Avisos: se cierran solos (los de error se quedan hasta cerrarlos)
  document.querySelectorAll('#toasts > div').forEach((t) => {
    t.querySelector('[data-dismiss]').addEventListener('click', () => t.remove());
    if (t.getAttribute('role') === 'status') setTimeout(() => t.remove(), 6000);
  });

  // Confirmación antes de acciones delicadas
  document.addEventListener('submit', (e) => {
    const msg = e.submitter?.dataset.confirm || e.target.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
  });

  // Editores de listas (beneficios, preguntas): agregar, quitar y mover filas
  document.querySelectorAll('[data-list]').forEach((list) => {
    const tpl = document.getElementById(list.dataset.list);
    const max = +list.dataset.max || 20;
    const renumber = () => list.querySelectorAll('[data-row]').forEach((row, i) => {
      row.querySelectorAll('[data-name]').forEach((el) => { el.name = el.dataset.name.replace('#', i); });
    });
    list.parentElement.querySelector('[data-add-row]')?.addEventListener('click', () => {
      if (list.querySelectorAll('[data-row]').length >= max) return;
      list.appendChild(tpl.content.cloneNode(true)); renumber();
      list.lastElementChild.querySelector('input,textarea')?.focus();
    });
    list.addEventListener('click', (e) => {
      const row = e.target.closest('[data-row]'); if (!row) return;
      if (e.target.closest('[data-remove-row]')) row.remove();
      else if (e.target.closest('[data-up]') && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
      else if (e.target.closest('[data-down]') && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
      else return;
      renumber();
    });
    renumber();
  });

  // Vista previa de imágenes antes de subir
  document.querySelectorAll('input[type=file][data-preview]').forEach((inp) => {
    inp.addEventListener('change', () => {
      const img = document.getElementById(inp.dataset.preview); const f = inp.files[0];
      if (img && f) { img.src = URL.createObjectURL(f); img.hidden = false; }
    });
  });

  document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => print()));
})();
