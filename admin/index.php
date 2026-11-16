<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

$u = require_user();
$tz = "CONVERT_TZ(created_at, '+00:00', '-04:00')";
$todayStart = local_date_to_utc((new DateTimeImmutable('today', new DateTimeZone(ACS_TZ)))->format('Y-m-d'));
$weekStart = gmdate('Y-m-d H:i:s', strtotime($todayStart . ' UTC') - 6 * 86400);
$allOrders = can('orders_all', $u['role']);
$scope = $allOrders ? '' : ' WHERE assigned_to = ' . (int) $u['id'];
$k = row("SELECT
    SUM(created_at >= ?) AS hoy,
    SUM(status = 'nuevo') AS pendientes,
    SUM(status = 'confirmado') AS en_curso,
    SUM(created_at >= ? AND status <> 'cancelado') AS semana,
    SUM(CASE WHEN created_at >= ? AND status <> 'cancelado' THEN total ELSE 0 END) AS monto_semana
  FROM orders$scope", [$todayStart, $weekStart, $weekStart]);
$byDay = [];
foreach (rows("SELECT DATE($tz) AS d, COUNT(*) AS n FROM orders WHERE created_at >= ? AND status <> 'cancelado'" . ($allOrders ? '' : ' AND assigned_to = ' . (int) $u['id']) . " GROUP BY d", [gmdate('Y-m-d H:i:s', strtotime($todayStart . ' UTC') - 13 * 86400)]) as $r) $byDay[$r['d']] = $r['n'];
$latest = rows('SELECT id, code, customer_name, zone_name, total, has_unpriced_items, status, created_at FROM orders' . $scope . ' ORDER BY created_at DESC LIMIT 8');
$pd = public_data();
$soldOut = (int) val("SELECT COUNT(*) FROM inventory_status i JOIN product_variants v ON v.id = i.variant_id JOIN products p ON p.id = v.product_id WHERE i.status = 'agotado' AND v.is_active = 1 AND p.status = 'active' AND p.deleted_at IS NULL");
$draft = val("SELECT id FROM price_publications WHERE status = 'draft' LIMIT 1");

// Negocio en vivo: ventas de los socios, stock y dinero por rendir
$salesToday = row('SELECT COALESCE(SUM(total),0) AS t, COALESCE(SUM(maples),0) AS m, COUNT(*) AS n FROM partner_sales WHERE deleted_at IS NULL AND created_at >= ?', [today_start_utc()]);
$salesWeek = row('SELECT COALESCE(SUM(total),0) AS t, COALESCE(SUM(maples),0) AS m FROM partner_sales WHERE deleted_at IS NULL AND created_at >= ?', [days_ago_utc(6)]);
$salesPrevWeek = (float) val('SELECT COALESCE(SUM(total),0) FROM partner_sales WHERE deleted_at IS NULL AND created_at >= ? AND created_at < ?', [days_ago_utc(13), days_ago_utc(6)]);
$levels = stock_levels();
$stockTotal = array_sum($levels['total']);
$weekBalances = seller_balances(days_ago_utc(6));
$owed = array_sum(array_map(fn($b) => max(0, $b['balance']), $weekBalances));
$ranking = array_filter($weekBalances, fn($b) => $b['sold'] > 0 || in_array($b['role'], ['socio'], true));
uasort($ranking, fn($a, $b) => $b['sold'] <=> $a['sold']);
$topSold = max(1, ...array_map(fn($b) => $b['sold'], $ranking ?: [['sold' => 0]]));
$stockProducts = stock_products();
$maxStock = max(1, ...array_values($levels['total'] ?: [0]));
$hasBusiness = $stockTotal > 0 || (int) $salesWeek['m'] > 0 || (bool) val('SELECT 1 FROM stock_movements LIMIT 1');
$trend = $salesPrevWeek > 0 ? round(((float) $salesWeek['t'] - $salesPrevWeek) / $salesPrevWeek * 100) : null;

admin_head('Resumen', $u, 'index.php');
?>
<p class="mt-1 text-ink-2">Hola, <?= e(explode(' ', $u['name'])[0]) ?>. Esto es lo que pasa hoy.</p>

<?php $todo = (int) $k['pendientes'] + (int) $k['en_curso']; ?>
<a href="<?= e(url('admin/pedidos.php')) ?>" class="o-banner <?= $todo ? 'has-work' : '' ?> mt-4">
  <span class="o-banner-n"><?= $todo ?></span>
  <span><strong><?= $todo === 0 ? 'No tienes pedidos por atender' : ($todo === 1 ? 'Tienes 1 pedido por atender' : "Tienes $todo pedidos por atender") ?></strong>
  <small><?= $todo ? (int) $k['pendientes'] . ' por confirmar · ' . (int) $k['en_curso'] . ' por entregar' : 'Cuando llegue uno, aparecerá aquí.' ?></small></span>
  <span class="o-banner-go">Ver pedidos →</span>
</a>

<?php if ($pd['prices']['status'] !== 'vigente'): ?>
  <div role="status" class="mt-6 flex flex-col gap-3 rounded-[22px] border border-yolk bg-yolk-soft p-5 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex gap-3 text-wood"><?= icon('alert', 22, 'mt-0.5 shrink-0') ?>
      <p><?php if ($pd['prices']['status'] === 'sin_publicar'): ?><strong>Todavía no hay precios publicados.</strong> La página muestra “precio por confirmar” hasta que publiques una lista.
      <?php else: ?><strong>Los precios publicados son del <?= e(fecha_hora($pd['prices']['effective_from'])) ?>.</strong> La página los muestra como precios anteriores por confirmar.<?php endif; ?></p></div>
    <?php if (can('prices', $u['role'])): ?><?= link_btn(url('admin/precios.php' . ($draft ? '?id=' . $draft : '')), $draft ? 'Revisar borrador' : 'Publicar precios', 'primary') ?><?php endif; ?>
  </div>
<?php endif; ?>

<section aria-labelledby="negocio-titulo" class="mt-6">
  <h2 id="negocio-titulo" class="display text-xl font-semibold text-forest"><span class="live-dot" aria-hidden="true"></span>El negocio hoy</h2>
  <div class="x-kpis mt-3">
    <div class="hero-kpi x-rise"><p class="lbl">Vendido hoy</p><p class="val">Bs <span data-count="<?= round((float) $salesToday['t'], 2) ?>"><?= e(number_format((float) $salesToday['t'], 0, ',', '.')) ?></span></p><p class="hint"><?= (int) $salesToday['m'] ?> maples · <?= (int) $salesToday['n'] ?> ventas</p></div>
    <div class="hero-kpi alt x-rise" style="--d:60ms"><p class="lbl">Últimos 7 días</p><p class="val">Bs <span data-count="<?= round((float) $salesWeek['t'], 2) ?>"><?= e(number_format((float) $salesWeek['t'], 0, ',', '.')) ?></span></p><p class="hint"><?= (int) $salesWeek['m'] ?> maples<?= $trend !== null ? ' · ' . ($trend >= 0 ? '▲ ' : '▼ ') . abs($trend) . '% vs. semana anterior' : '' ?></p></div>
    <a href="<?= e(url('admin/stock.php')) ?>" class="hero-kpi alt x-rise" style="--d:120ms"><p class="lbl">Huevos en stock</p><p class="val"><span data-count="<?= $stockTotal ?>"><?= $stockTotal ?></span> <span style="font-size:15px">maples</span></p><p class="hint"><?= number_format($stockTotal * 30, 0, ',', '.') ?> huevos · almacén y vendedores</p></a>
    <a href="<?= e(url('admin/ventas.php')) ?>" class="hero-kpi <?= $owed > 0 ? 'warn' : 'alt' ?> x-rise" style="--d:180ms"><p class="lbl">Por rendir</p><p class="val"><?= e(bs($owed)) ?></p><p class="hint">Cobrado que aún no se entregó</p></a>
  </div>

  <?php if (!$hasBusiness): ?>
    <div class="mt-4 rounded-[22px] border border-dashed border-line bg-paper p-5 text-ink-2">Para ver aquí el stock y las ventas en vivo: 1) en <a class="font-semibold text-forest underline" href="<?= e(url('admin/stock.php')) ?>">Stock de huevos</a> registra los maples que hay en el almacén; 2) cada vendedor registra lo que saca para vender y sus ventas en <a class="font-semibold text-forest underline" href="<?= e(url('admin/ventas.php')) ?>">Ventas y cuentas</a>.</div>
  <?php endif; ?>

  <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
    <?php card_open('Ingresos por día · últimos 14 días'); ?>
      <div class="mt-4"><?= bar_chart(sales_by_day(14), 'Ingresos por día en bolivianos, últimos 14 días', 'Bs') ?></div>
    <?php card_close(); ?>
    <?php card_open('Ventas por vendedor · 7 días'); ?>
      <?php if (!$ranking): ?><?= empty_state('Todavía no hay ventas registradas.') ?>
      <?php else: ?>
        <div class="rank mt-4">
          <?php $i = 0; foreach ($ranking as $b): $i++; ?>
            <div class="rank-row"><span class="rank-pos"><?= $i ?></span>
              <div><p class="text-sm font-semibold text-forest"><?= e($b['name']) ?><?= $b['zone'] ? '<span class="x-muted"> · ' . e($b['zone']) . '</span>' : '' ?></p>
                <div class="rank-bar"><span style="--w:<?= round($b['sold'] / $topSold * 100) ?>%"></span></div></div>
              <p class="tabular text-right text-sm"><strong><?= e(bs($b['sold'])) ?></strong><br><span class="x-muted"><?= $b['maples'] ?> maples</span></p></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php card_close(); ?>
  </div>

  <?php card_open('Stock por categoría'); ?>
    <div class="x-grid mt-4">
      <?php foreach ($stockProducts as $i => $p): $t = $levels['total'][(int) $p['id']] ?? 0; $st = stock_availability($t, 'maple'); ?>
        <div class="egg-card x-rise <?= $st === 'agotado' ? 'is-out' : ($st === 'poco_stock' ? 'is-low' : '') ?>" style="--d:<?= $i * 50 ?>ms">
          <h3><span class="egg-dot <?= $p['grade'] === 'jumbo' ? 'jumbo' : '' ?>" aria-hidden="true"></span><?= e($p['name']) ?></h3>
          <p class="big"><span data-count="<?= $t ?>"><?= $t ?></span> <small>maples</small></p>
          <?= stock_meter($t, $maxStock) ?>
          <p class="sub mt-2"><?= pill(AVAILABILITY[$st][0], AVAILABILITY[$st][1]) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php card_close(); ?>
</section>

<h2 class="display mt-10 text-xl font-semibold text-forest">Pedidos desde la página</h2>
<div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
  <?= kpi('Pedidos de hoy', (string) (int) $k['hoy'], '', url('admin/pedidos.php?desde=' . (new DateTimeImmutable('today', new DateTimeZone(ACS_TZ)))->format('Y-m-d'))) ?>
  <?= kpi('Por atender', (string) (int) $k['pendientes'], 'Nuevos, por confirmar', url('admin/pedidos.php?estado=abiertos')) ?>
  <?= kpi('Por entregar', (string) (int) $k['en_curso'], 'Confirmados, falta entregar') ?>
  <?= kpi('Últimos 7 días', bs((float) $k['monto_semana']), (int) $k['semana'] . ' pedidos (sin cancelados; no incluye precios por confirmar)') ?>
</div>

<div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
  <?php card_open('Pedidos por día · últimos 14 días'); ?>
    <div class="mt-4"><?= bar_chart(daily_series($byDay, 14), 'Pedidos por día en los últimos 14 días', 'pedidos') ?></div>
  <?php card_close(); ?>

  <?php if ($allOrders): ?><?php card_open('Estado de la página'); ?>
    <dl class="mt-4 space-y-3 text-sm">
      <div class="flex justify-between gap-3"><dt class="text-ink-2">Precios</dt><dd class="text-right"><?= $pd['prices']['status'] === 'vigente' ? pill('Vigentes desde ' . fecha_hora($pd['prices']['effective_from']), 'leaf') : ($pd['prices']['status'] === 'desactualizado' ? pill('Desactualizados', 'yolk') : pill('Sin publicar', 'terracotta')) ?></dd></div>
      <?php if ($pd['prices']['next_effective_from']): ?><div class="flex justify-between gap-3"><dt class="text-ink-2">Próxima lista</dt><dd><?= e(fecha_hora($pd['prices']['next_effective_from'])) ?></dd></div><?php endif; ?>
      <div class="flex justify-between gap-3"><dt class="text-ink-2">Presentaciones agotadas</dt><dd class="tabular font-semibold"><?= $soldOut ?></dd></div>
      <div class="flex justify-between gap-3"><dt class="text-ink-2">Asignación por zona</dt><dd><?= setting('zone_routing_enabled') ? pill('Activa', 'leaf') : pill('Desactivada · todo al contacto principal', 'gray') ?></dd></div>
    </dl>
    <div class="mt-5 flex flex-wrap gap-2">
      <?php if (can('inventory', $u['role'])): ?><?= link_btn(url('admin/inventario.php'), 'Inventario') ?><?php endif; ?>
      <?= link_btn(url(''), 'Ver página ↗', 'ghost', 'target="_blank" rel="noopener"') ?>
    </div>
  <?php card_close(); ?><?php endif; ?>
</div>

<?php card_open('Últimos pedidos'); ?>
  <?php if (!$latest): ?><?= empty_state('Todavía no hay pedidos. Aparecerán aquí apenas un cliente envíe uno desde la página.') ?>
  <?php else: ?>
  <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[560px] text-left text-sm">
    <thead><tr class="text-ink-2"><th class="py-2 font-medium">Pedido</th><th class="py-2 font-medium">Cliente</th><th class="py-2 font-medium">Fecha</th><th class="py-2 text-right font-medium">Total</th><th class="py-2 text-right font-medium">Estado</th></tr></thead>
    <tbody>
    <?php foreach ($latest as $o): ?>
      <tr class="border-t border-line">
        <td class="py-2.5"><a class="tabular font-semibold text-forest underline-offset-4 hover:underline" href="<?= e(url('admin/pedido.php?id=' . $o['id'])) ?>"><?= e($o['code']) ?></a></td>
        <td class="py-2.5"><?= e($o['customer_name']) ?><?= $o['zone_name'] ? '<span class="text-ink-2"> · ' . e($o['zone_name']) . '</span>' : '' ?></td>
        <td class="py-2.5 text-ink-2"><?= e(fecha_hora($o['created_at'])) ?></td>
        <td class="tabular py-2.5 text-right"><?= e(bs((float) $o['total'])) ?><?= $o['has_unpriced_items'] ? ' <span class="text-xs text-wood">+ por confirmar</span>' : '' ?></td>
        <td class="py-2.5 text-right"><?= status_pill($o['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="mt-4"><?= link_btn(url('admin/pedidos.php'), 'Ver todos los pedidos') ?></div>
  <?php endif; ?>
<?php card_close(); ?>
<?php admin_foot();
