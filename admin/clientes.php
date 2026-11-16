<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

$u = require_user(['admin', 'ventas']);
$q = trim((string) ($_GET['q'] ?? ''));
$where = 'c.deleted_at IS NULL'; $params = [];
if ($q !== '') { $where .= ' AND (c.name LIKE ? OR c.phone LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; $params = [$like, $like]; }
$per = 30; $page = max(1, (int) ($_GET['p'] ?? 1));
$total = (int) val("SELECT COUNT(*) FROM customers c WHERE $where", $params);
$list = rows("SELECT c.id, c.name, c.phone, z.name AS zone, COUNT(o.id) AS orders, COALESCE(SUM(CASE WHEN o.status <> 'cancelado' THEN o.total END), 0) AS spent, MAX(o.created_at) AS last_order
              FROM customers c LEFT JOIN zones z ON z.id = c.zone_id LEFT JOIN orders o ON o.customer_id = c.id
              WHERE $where GROUP BY c.id ORDER BY last_order DESC, c.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
admin_head('Clientes', $u, 'clientes.php');
?>
<p class="mt-1 text-ink-2">Se registran solos al hacer un pedido. Se identifican por su teléfono.</p>
<form method="get" class="mt-5 flex max-w-lg gap-2"><label for="q" class="sr-only">Buscar cliente</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Nombre o teléfono" class="field"><?= btn('Buscar', 'primary') ?></form>
<?php card_open('', 'mt-4'); ?>
  <?php if (!$list): ?><?= empty_state($q ? 'Ningún cliente coincide.' : 'Todavía no hay clientes.') ?>
  <?php else: ?>
  <div class="overflow-x-auto"><table class="w-full min-w-[620px] text-left text-sm">
    <thead><tr class="text-ink-2"><th class="py-2 font-medium">Cliente</th><th class="py-2 font-medium">Teléfono</th><th class="py-2 font-medium">Zona</th><th class="py-2 text-right font-medium">Pedidos</th><th class="py-2 text-right font-medium">Comprado</th><th class="py-2 font-medium">Último pedido</th></tr></thead>
    <tbody><?php foreach ($list as $c): ?>
      <tr class="border-t border-line"><td class="py-2.5"><a href="<?= e(url('admin/cliente.php?id=' . $c['id'])) ?>" class="font-semibold text-forest hover:underline"><?= e($c['name']) ?></a></td>
        <td class="tabular py-2.5"><?= e(pretty_phone($c['phone'])) ?></td><td class="py-2.5"><?= e($c['zone'] ?? '—') ?></td>
        <td class="tabular py-2.5 text-right"><?= (int) $c['orders'] ?></td><td class="tabular py-2.5 text-right"><?= e(bs((float) $c['spent'])) ?></td>
        <td class="py-2.5 text-ink-2"><?= e($c['last_order'] ? fecha($c['last_order']) : '—') ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
<?php card_close(); ?>
<?= pager($page, $total, $per, $q !== '' ? ['q' => $q] : []) ?>
<?php admin_foot();
