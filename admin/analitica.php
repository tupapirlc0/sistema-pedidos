<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

$u = require_user(['admin', 'ventas']);
$days = in_array((int) ($_GET['dias'] ?? 30), [7, 30, 90], true) ? (int) ($_GET['dias'] ?? 30) : 30;
$todayLocal = (new DateTimeImmutable('today', new DateTimeZone(ACS_TZ)));
$since = local_date_to_utc($todayLocal->modify('-' . ($days - 1) . ' day')->format('Y-m-d'));
$tz = "DATE(CONVERT_TZ(created_at, '+00:00', '-04:00'))";

$ev = [];
foreach (rows('SELECT type, COUNT(*) AS n, COUNT(DISTINCT visitor_id) AS v FROM analytics_events WHERE created_at >= ? GROUP BY type', [$since]) as $r) $ev[$r['type']] = $r;
$orders = row("SELECT COUNT(*) AS n, SUM(whatsapp_opened_at IS NOT NULL) AS wa, COALESCE(SUM(CASE WHEN status <> 'cancelado' THEN total END), 0) AS amount FROM orders WHERE created_at >= ?", [$since]);
$views = []; foreach (rows("SELECT $tz AS d, COUNT(DISTINCT visitor_id) AS n FROM analytics_events WHERE type = 'page_view' AND created_at >= ? GROUP BY d", [$since]) as $r) $views[$r['d']] = $r['n'];
$ordDay = []; foreach (rows("SELECT $tz AS d, COUNT(*) AS n FROM orders WHERE created_at >= ? GROUP BY d", [$since]) as $r) $ordDay[$r['d']] = $r['n'];
$top = rows("SELECT i.product_name, i.presentation, SUM(i.quantity) AS qty, COUNT(DISTINCT i.order_id) AS orders FROM order_items i JOIN orders o ON o.id = i.order_id
             WHERE o.created_at >= ? AND o.status <> 'cancelado' GROUP BY i.product_name, i.presentation ORDER BY qty DESC LIMIT 10", [$since]);
$adds = rows("SELECT p.name, v.presentation, COUNT(*) AS n FROM analytics_events a JOIN product_variants v ON v.id = a.variant_id JOIN products p ON p.id = v.product_id
              WHERE a.type = 'product_add' AND a.created_at >= ? GROUP BY a.variant_id ORDER BY n DESC LIMIT 10", [$since]);
$refs = rows("SELECT referrer, COUNT(DISTINCT visitor_id) AS n FROM analytics_events WHERE type = 'page_view' AND created_at >= ? AND referrer IS NOT NULL GROUP BY referrer ORDER BY n DESC LIMIT 6", [$since]);

$visitors = (int) ($ev['page_view']['v'] ?? 0);
$funnel = [
    'Visitantes' => $visitors,
    'Abrieron el pedido' => (int) ($ev['click_order']['v'] ?? 0),
    'Agregaron productos' => (int) ($ev['product_add']['v'] ?? 0),
    'Enviaron el pedido' => (int) $orders['n'],
    'Abrieron WhatsApp' => (int) $orders['wa'],
];
admin_head('Analítica', $u, 'analitica.php');
?>
<div class="mt-2 flex flex-wrap items-center justify-between gap-3">
  <p class="text-ink-2">Métricas anónimas de la página (sin datos personales). Hora de Bolivia.</p>
  <nav aria-label="Periodo" class="flex rounded-full border border-line bg-paper p-1 text-sm">
    <?php foreach ([7, 30, 90] as $d): ?><a href="?dias=<?= $d ?>" <?= $d === $days ? 'aria-current="true"' : '' ?> class="rounded-full px-4 py-1.5 font-semibold <?= $d === $days ? 'bg-forest text-cream' : 'text-forest hover:bg-cream-2' ?>"><?= $d ?> días</a><?php endforeach; ?>
  </nav>
</div>
<div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= kpi('Visitantes únicos', (string) $visitors, (int) ($ev['page_view']['n'] ?? 0) . ' visitas en total') ?>
  <?= kpi('Pedidos', (string) (int) $orders['n'], $visitors ? 'Conversión ' . number_format(100 * $orders['n'] / $visitors, 1, ',', '') . ' %' : '') ?>
  <?= kpi('Abrieron WhatsApp', (string) (int) $orders['wa'], $orders['n'] ? number_format(100 * $orders['wa'] / $orders['n'], 0) . ' % de los pedidos' : '') ?>
  <?= kpi('Monto con precio', bs((float) $orders['amount']), 'Sin cancelados ni precios por confirmar') ?>
</div>

<div class="grid gap-6 xl:grid-cols-2">
  <?php card_open('Visitantes por día'); ?><div class="mt-4"><?= bar_chart(daily_series($views, $days), "Visitantes únicos por día, últimos $days días", 'visitantes') ?></div><?php card_close(); ?>
  <?php card_open('Pedidos por día'); ?><div class="mt-4"><?= bar_chart(daily_series($ordDay, $days), "Pedidos por día, últimos $days días", 'pedidos') ?></div><?php card_close(); ?>
</div>

<?php card_open('Del interés al pedido'); ?>
  <ol class="mt-4 space-y-3">
    <?php $base = max(1, $funnel['Visitantes']); foreach ($funnel as $label => $n): $pct = min(100, 100 * $n / $base); ?>
      <li><div class="flex justify-between text-sm"><span><?= e($label) ?></span><span class="tabular font-semibold"><?= $n ?><?= $label !== 'Visitantes' && $funnel['Visitantes'] ? ' · ' . number_format($pct, 0) . ' %' : '' ?></span></div>
        <div class="mt-1 h-3 rounded-full bg-cream-2" aria-hidden="true"><div class="h-3 rounded-full bg-leaf" style="width:<?= $n ? max(1.5, $pct) : 0 ?>%"></div></div></li>
    <?php endforeach; ?>
  </ol>
<?php card_close(); ?>

<div class="grid gap-6 xl:grid-cols-3">
  <?php card_open('Más pedidos'); ?>
    <?php if (!$top): ?><?= empty_state('Sin pedidos en el periodo.') ?><?php else: ?>
    <ol class="mt-3 divide-y divide-line text-sm"><?php foreach ($top as $t): ?><li class="flex justify-between gap-2 py-2"><span><?= e(variant_name($t['product_name'], $t['presentation'])) ?></span><span class="tabular font-semibold"><?= (int) $t['qty'] ?></span></li><?php endforeach; ?></ol><?php endif; ?>
  <?php card_close(); ?>
  <?php card_open('Más agregados al pedido'); ?>
    <?php if (!$adds): ?><?= empty_state('Sin datos en el periodo.') ?><?php else: ?>
    <ol class="mt-3 divide-y divide-line text-sm"><?php foreach ($adds as $t): ?><li class="flex justify-between gap-2 py-2"><span><?= e(variant_name($t['name'], $t['presentation'])) ?></span><span class="tabular font-semibold"><?= (int) $t['n'] ?></span></li><?php endforeach; ?></ol><?php endif; ?>
  <?php card_close(); ?>
  <?php card_open('De dónde llegan'); ?>
    <?php if (!$refs): ?><?= empty_state('Visitas directas o sin dato de origen.') ?><?php else: ?>
    <ol class="mt-3 divide-y divide-line text-sm"><?php foreach ($refs as $t): ?><li class="flex justify-between gap-2 py-2"><span class="truncate"><?= e($t['referrer']) ?></span><span class="tabular font-semibold"><?= (int) $t['n'] ?></span></li><?php endforeach; ?></ol><?php endif; ?>
  <?php card_close(); ?>
</div>
<?php admin_foot();
