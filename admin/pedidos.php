<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';
require dirname(__DIR__) . '/inc/orders_admin.php';

$u = require_user();
$all = can('orders_all', $u['role']);
$_GET['estado'] = (string) ($_GET['estado'] ?? 'abiertos');
[$where, $params, $keep] = order_filters($_GET, $u);
$per = 25; $page = max(1, (int) ($_GET['p'] ?? 1));
$total = (int) val("SELECT COUNT(*) FROM orders o WHERE $where", $params);
$orders = rows("SELECT o.id, o.code, o.customer_name, o.customer_phone, o.zone_name, o.address, o.total, o.has_unpriced_items, o.status, o.created_at, p.full_name AS assigned,
                  (SELECT GROUP_CONCAT(CONCAT(i.quantity, ' ', IF(i.presentation = 'maple', IF(i.quantity = 1, 'maple', 'maples'), IF(i.quantity = 1, 'paquete', 'paquetes')), ' de ', i.product_name) SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) AS items
                FROM orders o LEFT JOIN profiles p ON p.id = o.assigned_to WHERE $where
                ORDER BY FIELD(o.status, 'nuevo', 'confirmado', 'entregado', 'cancelado'), o.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);

// Pestañas: lo que hay que hacer primero, y lo ya terminado aparte
[$scope, $scopeArgs] = order_scope($u);
$open = (int) val("SELECT COUNT(*) FROM orders o WHERE $scope AND o.status IN ('nuevo','confirmado')", $scopeArgs);
$tabs = ['abiertos' => 'Por atender (' . $open . ')', 'entregado' => 'Entregados', 'cancelado' => 'Cancelados', 'todos' => 'Todos'];
$extra = array_diff_key($keep, ['estado' => 1]);
admin_head($all ? 'Pedidos' : 'Mis pedidos', $u, 'pedidos.php');
?>
<p class="mt-1 text-ink-2"><?= $all ? 'Aquí están los pedidos de todas las zonas.' : 'Aquí están los pedidos de tu zona. Toca un pedido para atenderlo.' ?></p>

<nav aria-label="Filtrar pedidos" class="o-tabs mt-4">
  <?php foreach ($tabs as $k => $l): ?>
    <a href="<?= e(url('admin/pedidos.php?' . http_build_query(['estado' => $k] + $extra))) ?>" <?= ($keep['estado'] ?? '') === $k ? 'aria-current="page"' : '' ?>><?= e($l) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($all): ?>
<details class="o-more mt-3" <?= $extra ? 'open' : '' ?>>
  <summary>Buscar o filtrar</summary>
  <form method="get" class="x-form mt-3">
    <input type="hidden" name="estado" value="<?= e($keep['estado'] ?? 'abiertos') ?>">
    <div><?= label('q', 'Buscar') ?><input id="q" name="q" value="<?= e($keep['q'] ?? '') ?>" placeholder="Nombre, teléfono o código" class="field"></div>
    <div><?= label('asignado', 'Vendedor') ?><select id="asignado" name="asignado" class="field"><option value="">Todos</option><option value="nadie" <?= ($keep['asignado'] ?? '') === 'nadie' ? 'selected' : '' ?>>Sin vendedor</option><?= select_options(array_column(sellers(), 'name', 'id'), $keep['asignado'] ?? '') ?></select></div>
    <div><?= label('zona', 'Zona') ?><select id="zona" name="zona" class="field"><option value="">Todas</option><?= select_options(array_column(public_zones(), 'name', 'id'), $keep['zona'] ?? '') ?></select></div>
    <div><?= label('desde', 'Desde') ?><input id="desde" name="desde" type="date" value="<?= e($keep['desde'] ?? '') ?>" class="field"></div>
    <div><?= label('hasta', 'Hasta') ?><input id="hasta" name="hasta" type="date" value="<?= e($keep['hasta'] ?? '') ?>" class="field"></div>
    <div class="flex gap-2"><?= btn('Buscar', 'primary') ?><?php if ($extra): ?><?= link_btn(url('admin/pedidos.php'), 'Limpiar') ?><?php endif; ?></div>
  </form>
  <?php if (can('export', $u['role'])): ?><p class="mt-3"><?= link_btn(url('admin/exportar.php?' . http_build_query($keep)), icon('download', 16) . ' Descargar lista (CSV)') ?></p><?php endif; ?>
</details>
<?php endif; ?>

<?php if (!$orders): ?>
  <?= empty_state(($keep['estado'] ?? '') === 'abiertos' && !$extra ? 'No hay pedidos por atender. Cuando un cliente haga un pedido, aparecerá aquí.' : 'No hay pedidos para mostrar aquí.') ?>
<?php else: ?>
  <ul class="o-list mt-4">
    <?php foreach ($orders as $o): [$hint] = ORDER_NEXT[$o['status']] ?? ['']; ?>
      <li><a class="o-card o-<?= e($o['status']) ?>" href="<?= e(url('admin/pedido.php?id=' . $o['id'])) ?>">
        <span class="o-top"><?= status_pill($o['status']) ?> <span class="x-muted"><?= e(fecha_hora($o['created_at'])) ?></span></span>
        <span class="o-name"><?= e($o['customer_name']) ?></span>
        <span class="o-items"><?= e($o['items'] ?? '') ?></span>
        <span class="o-meta"><?= e($o['zone_name'] ?: 'Sin zona') ?><?= $all ? ' · ' . e($o['assigned'] ?? 'Sin vendedor') : '' ?></span>
        <span class="o-bottom"><strong class="tabular"><?= $o['has_unpriced_items'] ? 'Precio por confirmar' : e(bs((float) $o['total'])) ?></strong><?php if ($hint): ?><span class="o-go"><?= e($hint) ?> →</span><?php endif; ?></span>
      </a></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?= pager($page, $total, $per, $keep) ?>
<?php admin_foot();
