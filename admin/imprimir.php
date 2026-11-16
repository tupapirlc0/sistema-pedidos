<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';
require dirname(__DIR__) . '/inc/orders_admin.php';

$u = require_user();
$o = row('SELECT * FROM orders WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$o || !order_visible($o, $u)) redirect('admin/pedidos.php');
$items = rows('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$o['id']]);
security_headers(true);
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pedido <?= e($o['code']) ?></title>
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>"></head>
<body class="bg-white p-6 text-charcoal">
<main class="mx-auto max-w-2xl">
  <div class="no-print mb-6 flex gap-2"><?= btn(icon('print', 16) . ' Imprimir', 'primary', 'type="button" data-print') ?><?= link_btn(url('admin/pedido.php?id=' . $o['id']), 'Volver') ?></div>
  <div class="flex items-start justify-between gap-4 border-b-2 border-forest pb-4">
    <?= logo(setting('logo_path')) ?>
    <div class="text-right"><p class="text-sm text-ink-2">Pedido</p><p class="tabular text-2xl font-bold"><?= e($o['code']) ?></p><p class="text-sm"><?= e(fecha_hora($o['created_at'])) ?></p></div>
  </div>
  <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
    <div><dt class="text-ink-2">Cliente</dt><dd class="font-semibold"><?= e($o['customer_name']) ?></dd></div>
    <div><dt class="text-ink-2">Teléfono</dt><dd class="tabular"><?= e(pretty_phone($o['customer_phone'])) ?></dd></div>
    <div><dt class="text-ink-2">Entrega</dt><dd><?= e($o['address']) ?><?= $o['zone_name'] ? ' · ' . e($o['zone_name']) : '' ?></dd></div>
    <div><dt class="text-ink-2">Referencia</dt><dd><?= e($o['reference'] ?: '—') ?></dd></div>
    <?php if ($o['notes']): ?><div class="col-span-2"><dt class="text-ink-2">Observaciones</dt><dd><?= e($o['notes']) ?></dd></div><?php endif; ?>
  </dl>
  <table class="mt-6 w-full text-left text-sm">
    <thead><tr class="border-b border-charcoal"><th class="py-2">Producto</th><th class="py-2 text-right">Cant.</th><th class="py-2 text-right">Precio</th><th class="py-2 text-right">Subtotal</th></tr></thead>
    <tbody><?php foreach ($items as $i): ?><tr class="border-b border-line"><td class="py-2"><?= e(variant_name($i['product_name'], $i['presentation'])) ?></td><td class="tabular py-2 text-right"><?= (int) $i['quantity'] ?></td><td class="tabular py-2 text-right"><?= $i['unit_price'] === null ? 'Por confirmar' : e(bs((float) $i['unit_price'])) ?></td><td class="tabular py-2 text-right"><?= $i['line_total'] === null ? '—' : e(bs((float) $i['line_total'])) ?></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr><th colspan="3" class="py-3 text-right"><?= $o['has_unpriced_items'] ? 'Total parcial' : 'Total' ?></th><td class="tabular py-3 text-right text-lg font-bold"><?= e(bs((float) $o['total'])) ?></td></tr></tfoot>
  </table>
  <p class="mt-6 text-xs text-ink-2">Estado: <?= e(ORDER_STATUS[$o['status']][0]) ?>. Documento interno, no es factura.</p>
</main>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
</body></html>
