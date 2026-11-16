<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';
require dirname(__DIR__) . '/inc/orders_admin.php';

const EVENT_LABELS = ['created' => 'Pedido recibido desde la página', 'status' => 'Cambio de estado', 'note' => 'Nota', 'assigned' => 'Vendedor', 'whatsapp' => 'WhatsApp', 'edited' => 'Edición'];
const CANCEL_REASONS = ['El cliente ya no quiere el pedido', 'No hay huevos disponibles', 'El cliente no contesta', 'Pedido repetido o de prueba'];

// Cambia el estado y deja constancia en el historial
function set_order_status(array $o, string $to, string $note, array $u): void
{
    q('UPDATE orders SET status = ? WHERE id = ?', [$to, $o['id']]);
    q("INSERT INTO order_events (order_id, type, from_value, to_value, note, actor_id) VALUES (?, 'status', ?, ?, ?, ?)", [$o['id'], $o['status'], $to, $note ?: null, $u['id']]);
    audit('status', 'order', $o['id'], ['status' => $o['status']], ['status' => $to], $u['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post('id');
    $back = 'admin/pedido.php?id=' . $id;
    $action = post('action');
    handle_post(['admin', 'socio', 'ventas', 'operador'], function (array $u) use ($id, $action, $back) {
        $o = row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$id]);
        if (!$o || !order_visible($o, $u)) throw new UserError('Ese pedido no existe o es de otro vendedor.');
        $isAdmin = $u['role'] === 'admin';

        if ($action === 'confirm') {
            if ($o['status'] !== 'nuevo') return ['Este pedido ya estaba confirmado.', $back];
            set_order_status($o, 'confirmado', '', $u);
            return ['Pedido confirmado. Cuando lo entregues, toca “Ya lo entregué”.', $back];
        }
        if ($action === 'deliver') {
            if ($o['status'] !== 'confirmado') throw new UserError('Primero confirma el pedido.');
            $paid = post('paid');
            if ($paid !== '1' && $paid !== '0') throw new UserError('Indica si el cliente ya pagó.');
            // Si quedó algún precio por confirmar, se pide aquí
            $prices = is_array($_POST['price'] ?? null) ? $_POST['price'] : [];
            foreach (rows('SELECT id, quantity FROM order_items WHERE order_id = ? AND unit_price IS NULL', [$id]) as $i) {
                $price = round((float) str_replace(',', '.', (string) ($prices[$i['id']] ?? '')), 2);
                if ($price <= 0 || $price > 99999) throw new UserError('Escribe el precio acordado de cada producto.');
                q('UPDATE order_items SET unit_price = ?, line_total = ? WHERE id = ?', [$price, round($price * (int) $i['quantity'], 2), $i['id']]);
            }
            $sum = (float) val('SELECT COALESCE(SUM(line_total), 0) FROM order_items WHERE order_id = ?', [$id]);
            q('UPDATE orders SET subtotal = ?, total = ?, has_unpriced_items = 0 WHERE id = ?', [$sum, $sum, $id]);
            $short = order_to_sales($o, $paid === '1', $u['id']);
            set_order_status($o, 'entregado', $paid === '1' ? 'Cobrado' : 'Fiado (cobrar después)', $u);
            $msg = 'Pedido entregado. Ya se sumó a las ventas' . ($paid === '1' ? '.' : ' como fiado.');
            if ($short) return [$msg . ' Revisa el stock: faltaban ' . implode(', ', $short) . '.', $back, 'error'];
            return [$msg, $back];
        }
        if ($action === 'cancel') {
            if (!in_array($o['status'], ['nuevo', 'confirmado'], true)) throw new UserError('Este pedido ya no se puede cancelar.');
            $note = mb_substr(post('reason'), 0, 500);
            if ($note === '') throw new UserError('Elige el motivo de la cancelación.');
            set_order_status($o, 'cancelado', $note, $u);
            return ['Pedido cancelado.', $back];
        }
        if ($action === 'undo') {
            // Por si tocaron un botón sin querer. Lo terminado solo lo puede reabrir el administrador.
            if ($o['status'] === 'confirmado') { set_order_status($o, 'nuevo', 'Se volvió atrás', $u); return ['El pedido volvió a “Nuevo”.', $back]; }
            if (!$isAdmin) throw new UserError('Solo el administrador puede corregir un pedido terminado.');
            if ($o['status'] === 'entregado') {
                order_void_sales($id, $u['id']);
                set_order_status($o, 'confirmado', 'Se deshizo la entrega', $u);
                return ['Entrega deshecha: la venta se anuló y los huevos volvieron al stock.', $back];
            }
            if ($o['status'] === 'cancelado') { set_order_status($o, 'nuevo', 'Se reabrió el pedido', $u); return ['El pedido volvió a “Nuevo”.', $back]; }
            throw new UserError('Nada que deshacer.');
        }
        if ($action === 'assign') {
            if (!can('order_assign', $u['role'])) throw new UserError('Solo el administrador puede cambiar el vendedor.');
            $to = (int) post('assigned_to') ?: null;
            if ($to && !val('SELECT 1 FROM profiles WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [$to])) throw new UserError('Usuario no válido.');
            q('UPDATE orders SET assigned_to = ? WHERE id = ?', [$to, $id]);
            $name = $to ? val('SELECT full_name FROM profiles WHERE id = ?', [$to]) : 'Sin vendedor';
            q("INSERT INTO order_events (order_id, type, to_value, actor_id) VALUES (?, 'assigned', ?, ?)", [$id, mb_substr((string) $name, 0, 60), $u['id']]);
            return ['Vendedor del pedido: ' . $name . '.', $back];
        }
        if ($action === 'note') {
            $note = mb_substr(post('note'), 0, 1000);
            if ($note === '') throw new UserError('Escribe la nota.');
            q("INSERT INTO order_events (order_id, type, note, actor_id) VALUES (?, 'note', ?, ?)", [$id, $note, $u['id']]);
            return ['Nota agregada.', $back];
        }
        throw new UserError('Acción no válida.');
    }, url($back));
}

$u = require_user();
$id = (int) ($_GET['id'] ?? 0);
$o = row('SELECT o.*, c.name AS contact_name, c.phone AS contact_phone, p.full_name AS assigned FROM orders o
          LEFT JOIN business_contacts c ON c.id = o.contact_id LEFT JOIN profiles p ON p.id = o.assigned_to WHERE o.id = ?', [$id]);
if (!$o || !order_visible($o, $u)) { http_response_code(404); admin_head('Pedido no encontrado', $u, 'pedidos.php'); echo empty_state('Ese pedido no existe o es de otro vendedor.'); admin_foot(); exit; }
$items = rows('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$id]);
$unpriced = array_filter($items, fn($i) => $i['unit_price'] === null);
$events = rows('SELECT e.*, p.full_name AS actor FROM order_events e LEFT JOIN profiles p ON p.id = e.actor_id WHERE e.order_id = ? ORDER BY e.created_at DESC, e.id DESC', [$id]);
$people = sellers();
$prevCount = (int) val('SELECT COUNT(*) FROM orders WHERE customer_id = ? AND id <> ?', [$o['customer_id'], $id]);
$waText = 'Hola ' . explode(' ', $o['customer_name'])[0] . ', te escribimos de Distribuidora Demo por tu pedido ' . $o['code'] . '.';
$waBtn = '<a class="o-btn o-btn-wa" href="' . e(wa_link($o['customer_phone'], $waText)) . '" target="_blank" rel="noopener noreferrer">' . icon_whatsapp(22) . ' Escribir al cliente</a>';
$callBtn = '<a class="o-btn o-btn-ghost" href="tel:' . e(preg_replace('/\D/', '', $o['customer_phone']) ?? '') . '">Llamar: ' . e(pretty_phone($o['customer_phone'])) . '</a>';
$st = $o['status'];
$steps = ['nuevo' => 'Nuevo', 'confirmado' => 'Confirmado', 'entregado' => 'Entregado'];
$stepAt = array_search($st, array_keys($steps), true);
$hidden = csrf_field() . '<input type="hidden" name="id" value="' . $id . '">';
$sale = $st === 'entregado' ? row('SELECT paid FROM partner_sales WHERE order_id = ? AND deleted_at IS NULL LIMIT 1', [$id]) : null;
admin_head('Pedido de ' . $o['customer_name'], $u, 'pedidos.php');
?>
<p class="mt-2"><a href="<?= e(url('admin/pedidos.php')) ?>" class="o-back">← Volver a los pedidos</a></p>

<?php if ($st !== 'cancelado'): ?>
<ol class="o-steps mt-4" aria-label="Avance del pedido">
  <?php $i = 0; foreach ($steps as $k => $l): ?>
    <li class="<?= $i < $stepAt || $st === 'entregado' ? 'is-done' : ($i === $stepAt ? 'is-now' : '') ?>" <?= $i === $stepAt ? 'aria-current="step"' : '' ?>><span class="o-dot"><?= $i < $stepAt || $st === 'entregado' ? '✓' : $i + 1 ?></span><span><?= e($l) ?></span></li>
  <?php $i++; endforeach; ?>
</ol>
<?php endif; ?>

<section class="o-action o-action-<?= e($st) ?> mt-4" aria-labelledby="que-hacer">
  <?php if ($st === 'nuevo'): ?>
    <h2 id="que-hacer" class="o-title display">Qué hacer ahora</h2>
    <ol class="o-todo">
      <li>Habla con el cliente para acordar la entrega.</li>
      <li>Cuando estén de acuerdo, toca el botón verde.</li>
    </ol>
    <div class="o-row"><?= $waBtn . $callBtn ?></div>
    <form method="post" class="mt-3"><?= $hidden ?><input type="hidden" name="action" value="confirm"><button class="o-btn o-btn-main">✓ Confirmar pedido</button></form>
  <?php elseif ($st === 'confirmado'): ?>
    <h2 id="que-hacer" class="o-title display">Ahora entrega el pedido</h2>
    <p class="o-text">Cuando el cliente lo reciba, marca abajo si pagó y toca el botón verde.</p>
    <form method="post" class="mt-3"><?= $hidden ?><input type="hidden" name="action" value="deliver">
      <?php foreach ($unpriced as $i): ?>
        <div class="mb-3"><?= label('price' . $i['id'], 'Precio acordado por ' . variant_name($i['product_name'], $i['presentation']) . ' (Bs)', true) ?><input id="price<?= (int) $i['id'] ?>" name="price[<?= (int) $i['id'] ?>]" inputmode="decimal" required class="field"></div>
      <?php endforeach; ?>
      <fieldset><legend class="o-legend">¿El cliente ya pagó?</legend>
        <div class="o-choice">
          <label><input type="radio" name="paid" value="1" required><span>Sí, ya pagó</span></label>
          <label><input type="radio" name="paid" value="0" required><span>No, pagará después</span></label>
        </div>
      </fieldset>
      <button class="o-btn o-btn-main mt-3">✓ Ya lo entregué</button>
    </form>
    <div class="o-row mt-3"><?= $waBtn . $callBtn ?></div>
  <?php elseif ($st === 'entregado'): ?>
    <h2 id="que-hacer" class="o-title display">Pedido entregado ✓</h2>
    <p class="o-text"><?= $sale ? ((int) $sale['paid'] ? 'Ya está cobrado y sumado a las ventas.' : 'Está sumado a las ventas como fiado. Cuando te pague, márcalo en “Ventas y cuentas”.') : 'No hay nada más que hacer.' ?></p>
  <?php else: ?>
    <h2 id="que-hacer" class="o-title display">Pedido cancelado</h2>
    <p class="o-text">No hay nada más que hacer con este pedido.</p>
  <?php endif; ?>
</section>

<div class="grid gap-6 xl:grid-cols-2">
  <div>
    <?php card_open('Qué pidió'); ?>
      <ul class="o-lines mt-3">
        <?php foreach ($items as $i): $q = (int) $i['quantity']; ?>
          <li><span><strong><?= $q ?></strong> <?= $i['presentation'] === 'maple' ? ($q === 1 ? 'maple' : 'maples') : ($q === 1 ? 'paquete' : 'paquetes') ?> de <?= e($i['product_name']) ?></span><span class="tabular"><?= $i['line_total'] === null ? 'Por confirmar' : e(bs((float) $i['line_total'])) ?></span></li>
        <?php endforeach; ?>
        <li class="o-total"><span><?= $o['has_unpriced_items'] ? 'Total parcial' : 'Total' ?></span><span class="tabular"><?= e(bs((float) $o['total'])) ?></span></li>
      </ul>
      <?php if ($o['has_unpriced_items']): ?><p class="mt-2 rounded-xl bg-yolk-soft p-3 text-sm text-wood">Falta acordar el precio con el cliente. Lo escribes al marcar la entrega.</p><?php endif; ?>
    <?php card_close(); ?>
  </div>
  <div>
    <?php card_open('Datos del cliente'); ?>
      <dl class="o-data mt-3">
        <div><dt>Nombre</dt><dd><?= can('customers', $u['role']) ? '<a class="underline" href="' . e(url('admin/cliente.php?id=' . $o['customer_id'])) . '">' . e($o['customer_name']) . '</a>' : e($o['customer_name']) ?><?= $prevCount ? ' <span class="x-muted">· ' . $prevCount . ' ' . ($prevCount === 1 ? 'pedido anterior' : 'pedidos anteriores') . '</span>' : '' ?></dd></div>
        <div><dt>Teléfono</dt><dd class="tabular"><?= e(pretty_phone($o['customer_phone'])) ?></dd></div>
        <div><dt>Zona</dt><dd><?= e($o['zone_name'] ?: 'No indicó') ?></dd></div>
        <div><dt>Entrega</dt><dd><?= e($o['address']) ?></dd></div>
        <?php if ($o['reference']): ?><div><dt>Referencia</dt><dd><?= e($o['reference']) ?></dd></div><?php endif; ?>
        <?php if ($o['notes']): ?><div><dt>Observaciones</dt><dd class="whitespace-pre-line"><?= e($o['notes']) ?></dd></div><?php endif; ?>
        <div><dt>Vendedor</dt><dd><?= e($o['assigned'] ?? 'Sin vendedor') ?></dd></div>
        <div><dt>Recibido</dt><dd><?= e(fecha_hora($o['created_at'])) ?></dd></div>
      </dl>
    <?php card_close(); ?>
  </div>
</div>

<details class="o-more mt-6">
  <summary>Más opciones</summary>
  <div class="o-more-in">
    <?php if (in_array($st, ['nuevo', 'confirmado'], true)): ?>
      <form method="post" class="o-sub"><?= $hidden ?><input type="hidden" name="action" value="cancel">
        <h3>Cancelar este pedido</h3>
        <?= label('reason', 'Motivo', true) ?>
        <select id="reason" name="reason" required class="field"><option value="">Elige un motivo</option><?php foreach (CANCEL_REASONS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select>
        <button class="o-btn o-btn-danger mt-3" data-confirm="¿Seguro que quieres cancelar este pedido?">Cancelar pedido</button>
      </form>
    <?php endif; ?>
    <?php if ($st === 'confirmado' || ($u['role'] === 'admin' && in_array($st, ['entregado', 'cancelado'], true))): ?>
      <form method="post" class="o-sub"><?= $hidden ?><input type="hidden" name="action" value="undo">
        <h3>¿Tocaste un botón sin querer?</h3>
        <p class="x-muted">El pedido vuelve al paso anterior<?= $st === 'entregado' ? ', la venta se anula y los huevos regresan al stock' : '' ?>.</p>
        <button class="o-btn o-btn-ghost mt-2" data-confirm="¿Volver este pedido al paso anterior?">Volver atrás</button>
      </form>
    <?php endif; ?>
    <?php if (can('order_assign', $u['role'])): ?>
      <form method="post" class="o-sub"><?= $hidden ?><input type="hidden" name="action" value="assign">
        <h3>Pasar a otro vendedor</h3>
        <?= label('assigned_to', 'Vendedor') ?>
        <select id="assigned_to" name="assigned_to" class="field"><option value="">Sin vendedor</option><?= select_options(array_map(fn($p) => $p['name'] . ($p['zone_name'] ? ' · ' . $p['zone_name'] : ''), array_column($people, null, 'id')), $o['assigned_to'] ?? '') ?></select>
        <button class="o-btn o-btn-ghost mt-3">Guardar vendedor</button>
      </form>
    <?php endif; ?>
    <div class="o-sub"><h3>Imprimir</h3><?= link_btn(url('admin/imprimir.php?id=' . $id), icon('print', 16) . ' Imprimir pedido', 'ghost', 'target="_blank" rel="noopener"') ?></div>
    <div class="o-sub">
      <h3>Notas e historial</h3>
      <form method="post" class="mt-2 flex flex-col gap-2 sm:flex-row"><?= $hidden ?><input type="hidden" name="action" value="note">
        <label for="note" class="sr-only">Nota</label><input id="note" name="note" maxlength="1000" placeholder="Escribe una nota…" class="field" required>
        <?= btn('Agregar nota', 'ghost') ?></form>
      <ol class="mt-5 space-y-4 border-l-2 border-line pl-5">
        <?php foreach ($events as $ev): ?>
          <li class="relative"><span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-paper <?= $ev['type'] === 'note' ? 'bg-yolk' : 'bg-leaf' ?>" aria-hidden="true"></span>
            <p class="text-sm font-semibold text-forest"><?= e(EVENT_LABELS[$ev['type']] ?? $ev['type']) ?><?php if ($ev['type'] === 'status'): ?>: <?= e(order_status_name((string) $ev['from_value'])) ?> → <?= e(order_status_name((string) $ev['to_value'])) ?><?php elseif ($ev['type'] === 'assigned'): ?>: <?= e($ev['to_value']) ?><?php endif; ?></p>
            <?php if ($ev['note']): ?><p class="mt-0.5 whitespace-pre-line text-sm"><?= e($ev['note']) ?></p><?php endif; ?>
            <p class="text-xs text-ink-2"><?= e(fecha_hora($ev['created_at'])) ?><?= $ev['actor'] ? ' · ' . e($ev['actor']) : ($ev['type'] === 'created' || $ev['type'] === 'whatsapp' ? ' · Cliente' : '') ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>
</details>
<?php admin_foot();
