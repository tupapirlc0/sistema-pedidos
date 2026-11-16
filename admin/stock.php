<?php
// Stock de huevos en maples: almacén central (Zona Sur) y lo que cada socio retiró para vender.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(['admin', 'socio', 'ventas', 'operador'], function (array $u) {
        $type = post('type');
        $pid = (int) post('product_id');
        $unit = post('unit') === 'paquete' ? 10 : 1;
        $qty = (int) post('qty');
        $maples = $qty * $unit;
        $note = post('note');
        if (!isset(MOVE_TYPES[$type]) || $type === 'venta') throw new UserError('Elige qué quieres registrar.');
        if (!val("SELECT id FROM products WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$pid])) throw new UserError('Elige una categoría.');
        if ($qty === 0 || abs($maples) > 100000) throw new UserError('Escribe una cantidad válida.');
        // Solo el administrador ajusta stock o registra por otra persona
        $holder = (int) post('holder_id') ?: $u['id'];
        if ($holder !== $u['id'] && !can('sales_others', $u['role'])) throw new UserError('Solo puedes registrar tus propios retiros y devoluciones.');
        $levels = stock_levels();
        $central = $levels['central'][$pid] ?? 0;
        $inHand = $levels['holders'][$holder][$pid] ?? 0;
        $name = (string) val('SELECT name FROM products WHERE id = ?', [$pid]);
        $batch = new_batch();

        switch ($type) {
            case 'ingreso':
                if ($maples < 0) throw new UserError('Un ingreso debe ser positivo. Para corregir usa un ajuste.');
                add_move($pid, null, $maples, 'ingreso', $u['id'], $note, $batch);
                $msg = "Ingresaron $maples maples de $name al almacén.";
                break;
            case 'retiro':
                if ($maples < 0) throw new UserError('Escribe cuántos maples retiras.');
                if ($maples > $central) throw new UserError("En el almacén solo hay $central maples de $name.");
                add_move($pid, null, -$maples, 'retiro', $u['id'], $note, $batch);
                add_move($pid, $holder, $maples, 'retiro', $u['id'], $note, $batch);
                $msg = "Retiraste $maples maples de $name para vender.";
                break;
            case 'devolucion':
                if ($maples < 0) throw new UserError('Escribe cuántos maples devuelves.');
                if ($maples > $inHand) throw new UserError("Esa persona solo tiene $inHand maples de $name.");
                add_move($pid, $holder, -$maples, 'devolucion', $u['id'], $note, $batch);
                add_move($pid, null, $maples, 'devolucion', $u['id'], $note, $batch);
                $msg = "Volvieron $maples maples de $name al almacén.";
                break;
            default: // ajuste: corrige el almacén (puede ser negativo: huevos rotos, conteo)
                if (!can('stock_adjust', $u['role'])) throw new UserError('Solo el administrador puede ajustar el stock.');
                if ($central + $maples < 0) throw new UserError("El almacén quedaría en negativo (hay $central maples de $name).");
                if (trim($note) === '') throw new UserError('Escribe el motivo del ajuste (por ejemplo, huevos rotos o conteo).');
                add_move($pid, null, $maples, 'ajuste', $u['id'], $note, $batch);
                $msg = "Ajuste registrado: " . ($maples > 0 ? '+' : '') . "$maples maples de $name.";
        }
        audit('stock_' . $type, 'stock', $pid, null, ['maples' => $maples, 'holder' => $holder, 'note' => $note], $u['id']);
        return $msg;
    }, url('admin/stock.php'));
}

$u = require_user(['admin', 'socio', 'ventas', 'operador']);
$products = stock_products();
$levels = stock_levels();
$people = sellers();
$isAdmin = can('sales_others', $u['role']);
$maxStock = max(1, ...array_values($levels['total'] ?: [0]));
$moves = rows("SELECT m.*, p.name AS product, h.full_name AS holder, c.full_name AS author
               FROM stock_movements m JOIN products p ON p.id = m.product_id LEFT JOIN profiles h ON h.id = m.holder_id LEFT JOIN profiles c ON c.id = m.created_by
               WHERE NOT (m.type IN ('retiro','devolucion') AND m.holder_id IS NULL)
               ORDER BY m.created_at DESC, m.id DESC LIMIT 40");
$location = (string) setting('stock_location', 'Zona Sur');

admin_head('Stock de huevos', $u, 'stock.php');
?>
<p class="mt-1 text-ink-2">Todo se cuenta en <strong>maples de 30 huevos</strong> (1 paquete = 10 maples). La página pública muestra sola “Disponible”, “Poco stock” o “Agotado” según lo que hay aquí.</p>

<?php card_open('Almacén · ' . $location); ?>
  <p class="x-muted mt-1">Lo que queda en el almacén, más lo que tienen los vendedores para vender.</p>
  <div class="x-grid mt-4">
    <?php foreach ($products as $i => $p): $pid = (int) $p['id']; $c = $levels['central'][$pid] ?? 0; $t = $levels['total'][$pid] ?? 0; $st = stock_availability($t, 'maple'); ?>
      <div class="egg-card x-rise <?= $st === 'agotado' ? 'is-out' : ($st === 'poco_stock' ? 'is-low' : '') ?>" style="--d:<?= $i * 60 ?>ms">
        <h3><span class="egg-dot <?= $p['grade'] === 'jumbo' ? 'jumbo' : '' ?>" aria-hidden="true"></span><?= e($p['name']) ?></h3>
        <p class="big"><span data-count="<?= $c ?>"><?= $c ?></span> <small>en almacén</small></p>
        <p class="sub"><?= $t ?> maples en total · <?= intdiv($t, 10) ?> paquetes</p>
        <?= stock_meter($t, $maxStock) ?>
        <p class="sub mt-2"><?= pill(AVAILABILITY[$st][0], AVAILABILITY[$st][1]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
<?php card_close(); ?>

<?php card_open('Registrar un movimiento'); ?>
  <form method="post" class="mt-4">
    <?= csrf_field() ?>
    <div class="x-form">
      <div><?= label('type', 'Qué pasó', true) ?>
        <select id="type" name="type" class="field">
          <option value="ingreso">Llegaron huevos de la granja</option>
          <option value="retiro">Saco huevos para vender</option>
          <option value="devolucion">Devuelvo huevos al almacén</option>
          <?php if (can('stock_adjust', $u['role'])): ?><option value="ajuste">Ajuste (rotos, conteo…)</option><?php endif; ?>
        </select></div>
      <div><?= label('product_id', 'Categoría', true) ?><select id="product_id" name="product_id" class="field"><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      <div><?= label('qty', 'Cantidad', true) ?><input id="qty" name="qty" type="number" required step="1" value="10" class="field tabular"></div>
      <div><?= label('unit', 'En') ?><select id="unit" name="unit" class="field"><option value="maple">Maples</option><option value="paquete">Paquetes (10 maples)</option></select></div>
      <?php if ($isAdmin): ?>
        <div><?= label('holder_id', 'Socio (para retiros y devoluciones)') ?><select id="holder_id" name="holder_id" class="field"><?php foreach ($people as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $u['id'] ? 'selected' : '' ?>><?= e($s['name']) ?><?= $s['zone_name'] ? ' · ' . e($s['zone_name']) : '' ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div><?= label('note', 'Nota (opcional)') ?><input id="note" name="note" maxlength="200" class="field" placeholder="Ej.: entrega del lunes"></div>
    </div>
    <div class="mt-5"><?= btn('Registrar', 'primary') ?></div>
  </form>
<?php card_close(); ?>

<?php card_open('Lo que tiene cada vendedor para vender'); ?>
  <div class="mt-3 overflow-x-auto"><table class="x-table">
    <thead><tr><th>Persona</th><?php foreach ($products as $p): ?><th class="num"><?= e($p['name']) ?></th><?php endforeach; ?><th class="num">Total</th></tr></thead>
    <tbody>
    <?php $any = false; foreach ($people as $s): $h = $levels['holders'][(int) $s['id']] ?? []; $sum = array_sum($h); if (!$sum && !in_array($s['role_id'], ['socio', 'ventas'], true)) continue; $any = true; ?>
      <tr><td><strong class="text-forest"><?= e($s['name']) ?></strong><?= $s['zone_name'] ? '<span class="x-muted"> · ' . e($s['zone_name']) . '</span>' : '' ?></td>
        <?php foreach ($products as $p): ?><td class="num"><?= (int) ($h[(int) $p['id']] ?? 0) ?: '—' ?></td><?php endforeach; ?>
        <td class="num"><strong><?= $sum ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php if (!$any): ?><?= empty_state('Cuando un vendedor saque huevos para vender, aparecerá aquí lo que tiene en mano.') ?><?php endif; ?>
<?php card_close(); ?>

<?php card_open('Últimos movimientos'); ?>
  <?php if (!$moves): ?><?= empty_state('Todavía no hay movimientos. Empieza registrando lo que hay hoy en el almacén con “Llegaron huevos de la granja”.') ?>
  <?php else: ?>
  <div class="mt-3 overflow-x-auto"><table class="x-table">
    <thead><tr><th>Fecha</th><th>Qué</th><th>Categoría</th><th class="num">Maples</th><th>Quién</th><th>Nota</th></tr></thead>
    <tbody>
    <?php foreach ($moves as $m): ?>
      <tr><td class="x-muted"><?= e(fecha_hora($m['created_at'])) ?></td>
        <td><?= e(MOVE_TYPES[$m['type']]) ?><?= $m['holder'] && $m['type'] !== 'venta' ? '<span class="x-muted"> · ' . e($m['holder']) . '</span>' : '' ?></td>
        <td><?= e($m['product']) ?></td>
        <td class="num"><strong class="<?= (int) $m['maples'] < 0 ? 'text-terracotta-2' : 'text-leaf-2' ?>"><?= (int) $m['maples'] > 0 ? '+' : '' ?><?= (int) $m['maples'] ?></strong></td>
        <td><?= e($m['author'] ?? '—') ?></td><td class="x-muted"><?= e($m['note']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
<?php card_close(); ?>
<?php admin_foot();
