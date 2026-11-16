<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post('id');
    handle_post(['admin', 'socio', 'ventas'], function (array $u) use ($id) {
        $before = row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$before) throw new UserError('El cliente no existe.');
        $name = post('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) throw new UserError('Escribe un nombre válido.');
        $zone = (int) post('zone_id') ?: null;
        q('UPDATE customers SET name = ?, zone_id = ?, address = ?, reference = ?, notes = ? WHERE id = ?',
            [$name, $zone, mb_substr(post('address'), 0, 300), mb_substr(post('reference'), 0, 300), mb_substr(post('notes'), 0, 2000) ?: null, $id]);
        audit('update', 'customer', $id, $before, null, $u['id']);
        return 'Cliente guardado.';
    }, url('admin/cliente.php?id=' . $id));
}

$u = require_user(['admin', 'ventas']);
$c = row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [(int) ($_GET['id'] ?? 0)]);
if (!$c) redirect('admin/clientes.php');
$orders = rows('SELECT id, code, total, has_unpriced_items, status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 50', [$c['id']]);
admin_head($c['name'], $u, 'clientes.php');
?>
<p class="mt-1 text-ink-2"><a href="<?= e(url('admin/clientes.php')) ?>" class="underline underline-offset-4">Clientes</a> › <span class="tabular"><?= e(pretty_phone($c['phone'])) ?></span> · cliente desde <?= e(fecha($c['created_at'])) ?></p>
<div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
  <?php card_open('Datos'); ?>
    <form method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
      <div><?= label('name', 'Nombre', true) ?><input id="name" name="name" required maxlength="80" value="<?= e($c['name']) ?>" class="field"></div>
      <div><?= label('zone_id', 'Zona') ?><select id="zone_id" name="zone_id" class="field"><option value="">Sin zona</option><?= select_options(array_column(public_zones(), 'name', 'id'), $c['zone_id'] ?? '') ?></select></div>
      <div><?= label('address', 'Dirección') ?><input id="address" name="address" maxlength="300" value="<?= e($c['address']) ?>" class="field"></div>
      <div><?= label('reference', 'Referencia') ?><input id="reference" name="reference" maxlength="300" value="<?= e($c['reference']) ?>" class="field"></div>
      <div><?= label('notes', 'Notas internas') ?><textarea id="notes" name="notes" rows="3" maxlength="2000" class="field"><?= e($c['notes'] ?? '') ?></textarea></div>
      <div class="flex flex-wrap gap-2"><?= btn('Guardar', 'primary') ?><?= link_btn(wa_link($c['phone']), icon_whatsapp(16) . ' WhatsApp', 'wa', 'target="_blank" rel="noopener noreferrer"') ?></div>
    </form>
  <?php card_close(); ?>
  <?php card_open('Pedidos (' . count($orders) . ')'); ?>
    <?php if (!$orders): ?><?= empty_state('Sin pedidos.') ?><?php else: ?>
    <ul class="mt-3 divide-y divide-line text-sm">
      <?php foreach ($orders as $o): ?>
        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5"><a href="<?= e(url('admin/pedido.php?id=' . $o['id'])) ?>" class="tabular font-semibold text-forest hover:underline"><?= e($o['code']) ?></a>
          <span class="text-ink-2"><?= e(fecha($o['created_at'])) ?></span><span class="tabular"><?= e(bs((float) $o['total'])) ?><?= $o['has_unpriced_items'] ? '+' : '' ?></span><?= status_pill($o['status']) ?></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  <?php card_close(); ?>
</div>
<?php admin_foot();
