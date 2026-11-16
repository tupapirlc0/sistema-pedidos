<?php
// Resumen público de un pedido (enlace con token secreto, sin datos de otros clientes).
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/orders.php';
require __DIR__ . '/inc/ui.php';

security_headers(true);
$token = (string) ($_GET['t'] ?? '');
$o = preg_match('/^[a-f0-9]{32}$/', $token) ? row('SELECT o.*, c.name AS contact_name, c.phone AS contact_phone FROM orders o LEFT JOIN business_contacts c ON c.id = o.contact_id WHERE o.public_token = ?', [$token]) : null;
if (!$o) { http_response_code(404); require __DIR__ . '/404.php'; exit; }
$items = rows('SELECT product_name, presentation, unit_price, quantity, line_total FROM order_items WHERE order_id = ? ORDER BY id', [$o['id']]);
$msgItems = array_map(fn($i) => ['product' => $i['product_name'], 'presentation' => $i['presentation'], 'quantity' => (int) $i['quantity'],
    'unit_price' => $i['unit_price'] === null ? null : (float) $i['unit_price'], 'line_total' => $i['line_total'] === null ? null : (float) $i['line_total']], $items);
$message = order_message(['code' => $o['code'], 'items' => $msgItems, 'total' => (float) $o['total'], 'has_unpriced_items' => (bool) $o['has_unpriced_items'],
    'contact' => ['name' => $o['contact_name']], 'customer' => ['name' => $o['customer_name'], 'phone' => $o['customer_phone'], 'zone' => $o['zone_name'],
    'address' => $o['address'], 'reference' => $o['reference'], 'notes' => $o['notes']]]);
[$stLabel] = ORDER_STATUS[$o['status']] ?? [$o['status']];
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pedido <?= e($o['code']) ?> · Distribuidora Demo</title><meta name="robots" content="noindex">
<link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png"><link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>"></head>
<body class="min-h-screen bg-cream-2 px-4 py-10">
<main class="mx-auto max-w-lg">
  <a href="<?= e(url('')) ?>" class="inline-block"><?= logo(setting('logo_path')) ?></a>
  <div class="mt-6 rounded-[28px] border border-line bg-paper p-6 shadow-sm">
    <p class="text-sm text-ink-2">Pedido</p>
    <h1 class="tabular display text-3xl font-semibold text-forest"><?= e($o['code']) ?></h1>
    <p class="mt-1 text-sm text-ink-2"><?= e(fecha_hora($o['created_at'])) ?> · Estado: <strong class="text-forest"><?= e($stLabel) ?></strong></p>
    <ul class="mt-5 divide-y divide-line rounded-2xl border border-line">
      <?php foreach ($items as $i): $q = (int) $i['quantity']; ?>
        <li class="flex justify-between gap-3 p-3 text-sm"><span><?= $q ?> <?= $i['presentation'] === 'maple' ? ($q === 1 ? 'maple' : 'maples') : ($q === 1 ? 'paquete' : 'paquetes') ?> de <?= e($i['product_name']) ?></span>
          <span class="tabular font-semibold"><?= $i['line_total'] === null ? 'por confirmar' : e(bs((float) $i['line_total'])) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="mt-4 flex justify-between text-lg font-bold text-forest"><span><?= $o['has_unpriced_items'] ? 'Total parcial' : 'Total' ?></span><span class="tabular"><?= (float) $o['total'] > 0 || !$o['has_unpriced_items'] ? e(bs((float) $o['total'])) : 'por confirmar' ?></span></p>
    <?php if ($o['has_unpriced_items']): ?><p class="mt-1 text-xs text-ink-2">Hay productos con precio por confirmar.</p><?php endif; ?>
    <dl class="mt-5 space-y-1 text-sm">
      <div><dt class="inline font-semibold">Entrega:</dt> <dd class="inline"><?= e($o['address']) ?><?= $o['zone_name'] ? ' · ' . e($o['zone_name']) : '' ?></dd></div>
      <?php if ($o['reference']): ?><div><dt class="inline font-semibold">Referencia:</dt> <dd class="inline"><?= e($o['reference']) ?></dd></div><?php endif; ?>
    </dl>
    <?php if ($o['contact_phone'] && !in_array($o['status'], ['entregado', 'cancelado'], true)): ?>
      <a href="<?= e(wa_link($o['contact_phone'], $message)) ?>" target="_blank" rel="noopener noreferrer" data-wa-token="<?= e($token) ?>" data-api="<?= e(url('api/whatsapp.php')) ?>"
        class="mt-6 flex items-center justify-center gap-2 rounded-full bg-wa px-6 py-4 font-semibold text-white hover:bg-wa-2"><?= icon_whatsapp(22) ?> Enviar pedido por WhatsApp</a>
    <?php endif; ?>
    <p class="mt-4 text-center text-xs text-ink-2">La confirmación final de precio, disponibilidad y entrega la realiza nuestro equipo.</p>
  </div>
</main>
<script src="<?= e(asset('assets/js/pedido.js')) ?>" defer></script>
</body></html>
