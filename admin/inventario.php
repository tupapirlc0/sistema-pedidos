<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

const INV_OPTS = ['disponible' => 'Disponible', 'poco_stock' => 'Poco stock', 'agotado' => 'Agotado', 'por_confirmar' => 'Consultar disponibilidad'];

function inv_rows(): array
{
    return rows("SELECT v.id, v.presentation, p.name, i.status, i.quantity, i.low_threshold, i.updated_at, pr.full_name AS updated_by
                 FROM product_variants v JOIN products p ON p.id = v.product_id
                 LEFT JOIN inventory_status i ON i.variant_id = v.id LEFT JOIN profiles pr ON pr.id = i.updated_by
                 WHERE v.is_active = 1 AND p.deleted_at IS NULL AND p.status <> 'archived'
                 ORDER BY v.presentation = 'paquete', p.sort_order, p.name");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(['admin', 'operador'], function (array $u) {
        $qtyOn = (bool) setting('inventory_quantities_enabled');
        $changed = 0;
        foreach (inv_rows() as $r) {
            $vid = (int) $r['id'];
            $st = (string) ($_POST['status'][$vid] ?? '');
            if (!isset(INV_OPTS[$st])) continue;
            $qty = $qtyOn && ($_POST['qty'][$vid] ?? '') !== '' ? max(0, min(1000000, (int) $_POST['qty'][$vid])) : $r['quantity'];
            if ($st === $r['status'] && (string) $qty === (string) $r['quantity']) continue;
            q('INSERT INTO inventory_status (variant_id, status, quantity, updated_by) VALUES (?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE status = VALUES(status), quantity = VALUES(quantity), updated_by = VALUES(updated_by)', [$vid, $st, $qty, $u['id']]);
            audit('update', 'inventory', $vid, ['status' => $r['status'], 'quantity' => $r['quantity']], ['status' => $st, 'quantity' => $qty], $u['id']);
            $changed++;
        }
        return $changed ? "Inventario actualizado ($changed " . ($changed === 1 ? 'cambio' : 'cambios') . ').' : 'No había cambios.';
    }, url('admin/inventario.php'));
}

$u = require_user(['admin', 'operador']);
$rows = inv_rows();
$qtyOn = (bool) setting('inventory_quantities_enabled');
admin_head('Inventario', $u, 'inventario.php');
?>
<p class="mt-1 text-ink-2">La disponibilidad se muestra en la página junto a cada producto. “Agotado” impide pedirlo.</p>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach (['maple' => 'Por maple', 'paquete' => 'Por paquete'] as $pres => $t): ?>
    <?php card_open($t); ?>
      <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm">
        <thead><tr class="text-ink-2"><th class="py-2 font-medium">Producto</th><th class="py-2 font-medium">Disponibilidad</th><?php if ($qtyOn): ?><th class="py-2 font-medium">Cantidad</th><?php endif; ?><th class="py-2 font-medium">Último cambio</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): if ($r['presentation'] !== $pres) continue; ?>
          <tr class="border-t border-line">
            <th scope="row" class="py-2.5 pr-3 font-semibold text-forest"><?= e($r['name']) ?></th>
            <td class="py-2.5 pr-3"><select name="status[<?= (int) $r['id'] ?>]" class="field max-w-[230px]" aria-label="Disponibilidad de <?= e($r['name']) ?> por <?= $pres ?>"><?= select_options(INV_OPTS, $r['status'] ?? 'por_confirmar') ?></select></td>
            <?php if ($qtyOn): ?><td class="py-2.5 pr-3"><input name="qty[<?= (int) $r['id'] ?>]" type="number" min="0" value="<?= e($r['quantity']) ?>" class="field tabular max-w-[110px]" aria-label="Cantidad de <?= e($r['name']) ?>"></td><?php endif; ?>
            <td class="py-2.5 text-xs text-ink-2"><?= $r['updated_by'] ? e(fecha_hora($r['updated_at'])) . ' · ' . e($r['updated_by']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php card_close(); ?>
  <?php endforeach; ?>
  <div class="mt-6"><?= btn('Guardar inventario', 'primary') ?></div>
</form>
<?php admin_foot();
