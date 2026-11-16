// Registra que el cliente abrió WhatsApp desde el resumen del pedido.
document.querySelectorAll('[data-wa-token]').forEach((a) => {
  a.addEventListener('click', () => {
    fetch(a.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token: a.dataset.waToken }), keepalive: true }).catch(() => {});
  });
});
