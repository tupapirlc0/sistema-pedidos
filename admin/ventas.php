<?php
// Ventas de los socios y rendición de cuentas. Cada venta descuenta el stock de quien vendió.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

const PERIODS = ['hoy' => 'Hoy', '7d' => '7 días', '30d' => '30 días', 'todo' => 'Todo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    handle_post(['admin', 'socio', 'ventas', 'operador'], function (array $u) use ($action) {
        $isAdmin = can('sales_others', $u['role']);

        if ($action === 'sale') {
            $seller = (int) post('seller_id') ?: $u['id'];
            if ($seller !== $u['id'] && !$isAdmin) throw new UserError('Solo puedes registrar tus propias ventas.');
            if (!val('SELECT id FROM profiles WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [$seller])) throw new UserError('Elige quién vendió.');
            $pid = (int) post('product_id');
            $pres = post('presentation') === 'paquete' ? 'paquete' : 'maple';
            $qty = (int) post('qty');
            $price = (float) str_replace(',', '.', post('unit_price'));
            $name = (string) val("SELECT name FROM products WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$pid]);
            if ($name === '') throw new UserError('Elige una categoría.');
            if ($qty < 1 || $qty > 10000) throw new UserError('Escribe cuántos vendiste.');
            if ($price <= 0 || $price > 99999) throw new UserError('Escribe el precio por ' . $pres . '.');
            $maples = $qty * MAPLES_PER[$pres];
            $central = post('source') === 'central';
            $levels = stock_levels();
            $have = $central ? ($levels['central'][$pid] ?? 0) : ($levels['holders'][$seller][$pid] ?? 0);
            if ($maples > $have) {
                throw new UserError($central ? "En el almacén solo hay $have maples de $name."
                    : "Según el stock, esa persona tiene $have maples de $name. Primero registra el retiro en Stock, o elige vender desde el almacén.");
            }
            $total = round($price * $qty, 2);
            q('INSERT INTO partner_sales (seller_id, product_id, presentation, quantity, maples, unit_price, total, paid, from_central, customer_name, note, created_by)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$seller, $pid, $pres, $qty, $maples, $price, $total, post('paid') === '0' ? 0 : 1, $central ? 1 : 0, mb_substr(post('customer'), 0, 80), mb_substr(post('note'), 0, 200), $u['id']]);
            $sid = (int) db()->lastInsertId();
            add_move($pid, $central ? null : $seller, -$maples, 'venta', $u['id'], '', null, $sid);
            audit('create', 'sale', $sid, null, ['total' => $total, 'maples' => $maples], $u['id']);
            return "Venta registrada: $qty " . ($pres === 'maple' ? ($qty === 1 ? 'maple' : 'maples') : ($qty === 1 ? 'paquete' : 'paquetes')) . " de $name por " . bs($total) . '.';
        }

        if ($action === 'paid' || $action === 'delete') {
            $s = row('SELECT * FROM partner_sales WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [(int) post('id')]);
            if (!$s) throw new UserError('Esa venta ya no existe.');
            $own = (int) $s['seller_id'] === $u['id'];
            if ($action === 'paid') {
                if (!$own && !$isAdmin) throw new UserError('Solo quien vendió puede marcarla como cobrada.');
                q('UPDATE partner_sales SET paid = 1 WHERE id = ?', [$s['id']]);
                audit('paid', 'sale', $s['id'], ['paid' => 0], ['paid' => 1], $u['id']);
                return 'Marcada como cobrada.';
            }
            if ($s['order_id']) throw new UserError('Esta venta viene de un pedido. Corrígela desde el pedido, en “Más opciones”.');
            // Borrar: el administrador siempre; quien vendió, solo durante las primeras 12 horas
            if (!$isAdmin && !($own && strtotime($s['created_at'] . ' UTC') > time() - 12 * 3600)) throw new UserError('Solo el administrador puede anular ventas de más de 12 horas.');
            q('UPDATE partner_sales SET deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$s['id']]);
            add_move((int) $s['product_id'], (int) $s['from_central'] ? null : (int) $s['seller_id'], (int) $s['maples'], 'ajuste', $u['id'], 'Venta anulada #' . $s['id'], null, (int) $s['id']);
            audit('delete', 'sale', $s['id'], $s, null, $u['id']);
            return 'Venta anulada y los maples volvieron al stock.';
        }

        if ($action === 'settle') {
            if (!can('settle', $u['role'])) throw new UserError('Solo el administrador registra rendiciones.');
            $seller = (int) post('seller_id');
            $amount = round((float) str_replace(',', '.', post('amount')), 2);
            if (!val('SELECT id FROM profiles WHERE id = ?', [$seller])) throw new UserError('Elige quién rinde cuentas.');
            if ($amount <= 0 || $amount > 9999999) throw new UserError('Escribe el monto que entregó.');
            q('INSERT INTO settlements (seller_id, amount, note, created_by) VALUES (?, ?, ?, ?)', [$seller, $amount, mb_substr(post('note'), 0, 200), $u['id']]);
            audit('create', 'settlement', db()->lastInsertId(), null, ['seller' => $seller, 'amount' => $amount], $u['id']);
            return 'Rendición registrada: ' . bs($amount) . '.';
        }
        throw new UserError('Acción no válida.');
    }, url('admin/ventas.php' . (isset($_GET['p']) ? '?p=' . urlencode((string) $_GET['p']) : '')));
}

$u = require_user(['admin', 'socio', 'ventas', 'operador']);
$isAdmin = can('sales_others', $u['role']);
$period = array_key_exists($_GET['p'] ?? '', PERIODS) ? $_GET['p'] : '7d';
$from = match ($period) { 'hoy' => today_start_utc(), '7d' => days_ago_utc(6), '30d' => days_ago_utc(29), default => null };
$seller = (int) ($_GET['vendedor'] ?? 0);

$products = stock_products();
$people = sellers();
$levels = stock_levels();
$balances = seller_balances($from);
$where = 's.deleted_at IS NULL' . ($from ? ' AND s.created_at >= ?' : '') . ($seller ? ' AND s.seller_id = ?' : '');
$args = array_values(array_filter([$from, $seller ?: null]));
$sales = rows("SELECT s.*, p.name AS product, pr.full_name AS seller FROM partner_sales s JOIN products p ON p.id = s.product_id JOIN profiles pr ON pr.id = s.seller_id
               WHERE $where ORDER BY s.created_at DESC LIMIT 200", $args);
$sum = ['total' => 0.0, 'maples' => 0, 'credit' => 0.0];
foreach ($sales as $s) { $sum['total'] += (float) $s['total']; $sum['maples'] += (int) $s['maples']; if (!(int) $s['paid']) $sum['credit'] += (float) $s['total']; }
$settlements = rows('SELECT t.*, pr.full_name AS seller, c.full_name AS author FROM settlements t JOIN profiles pr ON pr.id = t.seller_id LEFT JOIN profiles c ON c.id = t.created_by
                     WHERE t.deleted_at IS NULL' . ($from ? ' AND t.created_at >= ?' : '') . ' ORDER BY t.created_at DESC LIMIT 30', $from ? [$from] : []);

// Precios vigentes para completar el formulario solo
$priceMap = [];
foreach ($products as $p) foreach (['maple', 'paquete'] as $pres) $priceMap[$p['id'] . '-' . $pres] = current_price_for((int) $p['id'], $pres);
$myStock = $levels['holders'][$u['id']] ?? [];

admin_head('Ventas y cuentas', $u, 'ventas.php');
?>
<p class="mt-1 text-ink-2">Cada vendedor registra lo que vende; el stock se descuenta solo y aquí se ve cuánto se vendió, cuánto falta cobrar y cuánto falta rendir.</p>

<nav class="mt-5 flex flex-wrap items-center gap-3" aria-label="Período">
  <span class="x-seg"><?php foreach (PERIODS as $k => $l): ?><a href="?<?= e(http_build_query(['p' => $k] + ($seller ? ['vendedor' => $seller] : []))) ?>" <?= $k === $period ? 'aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?></span>
  <form method="get" class="flex items-center gap-2"><input type="hidden" name="p" value="<?= e($period) ?>">
    <select name="vendedor" class="field" aria-label="Filtrar por persona" onchange="this.form.submit()"><option value="0">Todas las personas</option><?php foreach ($people as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $seller === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  </form>
</nav>

<div class="x-kpis mt-5">
  <div class="hero-kpi x-rise"><p class="lbl">Vendido · <?= e(mb_strtolower(PERIODS[$period])) ?></p><p class="val"><?= e(bs($sum['total'])) ?></p><p class="hint"><?= count($sales) ?> ventas</p></div>
  <div class="hero-kpi alt x-rise" style="--d:60ms"><p class="lbl">Maples vendidos</p><p class="val"><?= $sum['maples'] ?></p><p class="hint"><?= number_format($sum['maples'] * 30, 0, ',', '.') ?> huevos</p></div>
  <div class="hero-kpi alt x-rise" style="--d:120ms"><p class="lbl">Fiado (por cobrar)</p><p class="val"><?= e(bs($sum['credit'])) ?></p><p class="hint">Ventas sin cobrar todavía</p></div>
  <?php $owed = array_sum(array_map(fn($b) => max(0, $b['balance']), $seller ? array_intersect_key($balances, [$seller => 1]) : $balances)); ?>
  <div class="hero-kpi <?= $owed > 0 ? 'warn' : 'alt' ?> x-rise" style="--d:180ms"><p class="lbl">Por rendir</p><p class="val"><?= e(bs($owed)) ?></p><p class="hint">Cobrado que todavía no se entregó</p></div>
</div>

<?php card_open('Registrar una venta'); ?>
  <?php if (!$isAdmin): ?><p class="x-muted mt-1">Tienes para vender: <?= $myStock ? implode(' · ', array_map(fn($p) => e($p['name']) . ' ' . (int) ($myStock[(int) $p['id']] ?? 0), array_filter($products, fn($p) => ($myStock[(int) $p['id']] ?? 0) > 0))) : 'nada registrado. Si vendes desde el almacén, elígelo abajo.' ?></p><?php endif; ?>
  <form method="post" class="mt-4" id="sale-form" data-prices="<?= json_attr($priceMap) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="sale">
    <div class="x-form">
      <?php if ($isAdmin): ?><div><?= label('seller_id', 'Quién vendió', true) ?><select id="seller_id" name="seller_id" class="field"><?php foreach ($people as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $u['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div><?= label('product_id', 'Categoría', true) ?><select id="product_id" name="product_id" class="field"><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      <div><?= label('presentation', 'Por', true) ?><select id="presentation" name="presentation" class="field"><option value="maple">Maple (30 huevos)</option><option value="paquete">Paquete (10 maples)</option></select></div>
      <div><?= label('qty', 'Cantidad', true) ?><input id="qty" name="qty" type="number" min="1" value="1" required class="field tabular"></div>
      <div><?= label('unit_price', 'Precio c/u (Bs)', true) ?><input id="unit_price" name="unit_price" inputmode="decimal" required class="field tabular" placeholder="0"></div>
      <div><?= label('source', 'Desde') ?><select id="source" name="source" class="field"><option value="mine">Lo que tengo para vender</option><option value="central">El almacén</option></select></div>
      <div><?= label('paid', 'Pago') ?><select id="paid" name="paid" class="field"><option value="1">Cobrado</option><option value="0">Fiado (cobrar después)</option></select></div>
      <div><?= label('customer', 'Cliente (opcional)') ?><input id="customer" name="customer" maxlength="80" class="field"></div>
    </div>
    <div class="mt-5 flex flex-wrap items-center gap-4"><?= btn('Registrar venta', 'primary') ?><p class="display text-2xl font-semibold text-forest" aria-live="polite">Total: <span id="sale-total">Bs 0</span></p></div>
  </form>
<?php card_close(); ?>

<?php card_open('Cuentas por persona · ' . mb_strtolower(PERIODS[$period])); ?>
  <div class="mt-3 overflow-x-auto"><table class="x-table">
    <thead><tr><th>Persona</th><th class="num">Maples vendidos</th><th class="num">Vendido</th><th class="num">Fiado</th><th class="num">Rindió</th><th class="num">Saldo por rendir (total)</th></tr></thead>
    <tbody>
    <?php foreach ($balances as $id => $b): if (!$b['sold'] && !$b['balance'] && !$b['settled'] && !in_array($b['role'], ['socio', 'ventas'], true)) continue; ?>
      <tr><td><strong class="text-forest"><?= e($b['name']) ?></strong><?= $b['zone'] ? '<span class="x-muted"> · ' . e($b['zone']) . '</span>' : '' ?></td>
        <td class="num"><?= $b['maples'] ?></td><td class="num"><?= e(bs($b['sold'])) ?></td><td class="num"><?= $b['credit'] > 0 ? '<span class="text-terracotta-2">' . e(bs($b['credit'])) . '</span>' : '—' ?></td>
        <td class="num"><?= e(bs($b['settled'])) ?></td><td class="num"><strong class="<?= $b['balance'] > 0 ? 'text-terracotta-2' : 'text-leaf-2' ?>"><?= e(bs($b['balance'])) ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php if (can('settle', $u['role'])): ?>
    <form method="post" class="mt-5 rounded-2xl bg-cream-2 p-4">
      <?= csrf_field() ?><input type="hidden" name="action" value="settle">
      <p class="font-semibold text-forest">Registrar rendición de cuentas</p>
      <div class="x-form mt-3">
        <div><?= label('st_seller', 'Quién entrega', true) ?><select id="st_seller" name="seller_id" class="field"><?php foreach ($people as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?><?= $balances[(int) $s['id']]['balance'] > 0 ? ' · debe ' . e(bs($balances[(int) $s['id']]['balance'])) : '' ?></option><?php endforeach; ?></select></div>
        <div><?= label('st_amount', 'Monto (Bs)', true) ?><input id="st_amount" name="amount" inputmode="decimal" required class="field tabular"></div>
        <div><?= label('st_note', 'Nota') ?><input id="st_note" name="note" maxlength="200" class="field" placeholder="Ej.: transferencia, efectivo"></div>
        <div><?= btn('Registrar', 'accent') ?></div>
      </div>
    </form>
  <?php endif; ?>
<?php card_close(); ?>

<?php card_open('Ventas · ' . mb_strtolower(PERIODS[$period])); ?>
  <?php if (!$sales): ?><?= empty_state('No hay ventas en este período.') ?>
  <?php else: ?>
  <div class="mt-3 overflow-x-auto"><table class="x-table">
    <thead><tr><th>Fecha</th><th>Vendió</th><th>Detalle</th><th>Cliente</th><th class="num">Total</th><th>Pago</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sales as $s): $own = (int) $s['seller_id'] === $u['id']; $canDel = !$s['order_id'] && ($isAdmin || ($own && strtotime($s['created_at'] . ' UTC') > time() - 12 * 3600)); ?>
      <tr><td class="x-muted"><?= e(fecha_hora($s['created_at'])) ?></td><td><?= e($s['seller']) ?></td>
        <td><?= (int) $s['quantity'] ?> <?= $s['presentation'] === 'maple' ? 'maple' : 'paquete' ?><?= (int) $s['quantity'] > 1 ? 's' : '' ?> · <strong><?= e($s['product']) ?></strong> <span class="x-muted">a <?= e(bs((float) $s['unit_price'])) ?><?= (int) $s['from_central'] ? ' · del almacén' : '' ?></span></td>
        <td><?= e($s['customer_name'] ?: '—') ?></td>
        <td class="num"><strong><?= e(bs((float) $s['total'])) ?></strong></td>
        <td><?= (int) $s['paid'] ? pill('Cobrado', 'leaf') : pill('Fiado', 'yolk') ?></td>
        <td class="num">
          <?php if (!(int) $s['paid'] && ($own || $isAdmin)): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="paid"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="text-sm font-semibold text-leaf-2 underline">Cobrar</button></form><?php endif; ?>
          <?php if ($canDel): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="ml-2 text-sm text-terracotta-2 underline" data-confirm="¿Anular esta venta? Los maples vuelven al stock.">Anular</button></form><?php endif; ?>
        </td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
<?php card_close(); ?>

<?php if ($settlements): card_open('Rendiciones · ' . mb_strtolower(PERIODS[$period])); ?>
  <div class="mt-3 overflow-x-auto"><table class="x-table">
    <thead><tr><th>Fecha</th><th>Entregó</th><th class="num">Monto</th><th>Recibió</th><th>Nota</th></tr></thead>
    <tbody><?php foreach ($settlements as $t): ?><tr><td class="x-muted"><?= e(fecha_hora($t['created_at'])) ?></td><td><?= e($t['seller']) ?></td><td class="num"><strong><?= e(bs((float) $t['amount'])) ?></strong></td><td><?= e($t['author'] ?? '—') ?></td><td class="x-muted"><?= e($t['note']) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
<?php card_close(); endif; ?>
<?php admin_foot();
