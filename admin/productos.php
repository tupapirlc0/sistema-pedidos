<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

const PRODUCT_STATUS = ['active' => ['Activo', 'leaf'], 'draft' => ['Borrador', 'yolk'], 'inactive' => ['Oculto', 'gray'], 'archived' => ['Archivado', 'gray']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(['admin'], function (array $u) {
        $id = (int) post('id');
        $dir = post('move') === 'up' ? -1 : 1;
        $list = array_map('intval', array_column(rows('SELECT id FROM products WHERE deleted_at IS NULL ORDER BY sort_order, name'), 'id'));
        $i = array_search($id, $list, true);
        if ($i === false || !isset($list[$i + $dir])) return 'Sin cambios.';
        [$list[$i], $list[$i + $dir]] = [$list[$i + $dir], $list[$i]];
        foreach ($list as $pos => $pid) q('UPDATE products SET sort_order = ? WHERE id = ?', [($pos + 1) * 10, $pid]);
        return 'Orden actualizado.';
    }, url('admin/productos.php'));
}

$u = require_user(['admin']);
$products = rows("SELECT p.*, GROUP_CONCAT(CASE WHEN v.is_active = 1 THEN v.presentation END ORDER BY v.presentation) AS pres
                  FROM products p LEFT JOIN product_variants v ON v.product_id = p.id WHERE p.deleted_at IS NULL GROUP BY p.id ORDER BY p.sort_order, p.name");
admin_head('Productos', $u, 'productos.php');
?>
<div class="mt-1 flex flex-wrap items-center justify-between gap-3">
  <p class="text-ink-2">Categorías de huevo y sus presentaciones. El orden es el de la lista de precios.</p>
  <?= link_btn(url('admin/producto.php'), icon('plus', 16) . ' Nuevo producto', 'primary') ?>
</div>
<?php card_open(); ?>
  <?php if (!$products): ?><?= empty_state('No hay productos.') ?><?php else: ?>
  <ul class="divide-y divide-line">
    <?php foreach ($products as $i => $p): [$sl, $st] = PRODUCT_STATUS[$p['status']]; ?>
      <li class="flex flex-wrap items-center gap-3 py-3">
        <?php if ($p['image_path']): ?><img src="<?= e(url($p['image_path'])) ?>" alt="" class="egg h-12 w-10 object-cover"><?php else: ?><span class="egg h-12 w-10 bg-[radial-gradient(circle_at_35%_30%,#f7dcc0_0%,#d9a273_55%,#b67a4e_100%)]" aria-hidden="true"></span><?php endif; ?>
        <div class="min-w-0 flex-1">
          <a href="<?= e(url('admin/producto.php?id=' . $p['id'])) ?>" class="font-semibold text-forest hover:underline"><?= e($p['name']) ?></a>
          <?= $p['badge'] ? pill($p['badge'], 'yolk') : '' ?>
          <p class="text-sm text-ink-2"><?= e(implode(' y ', array_map(fn($x) => $x === 'maple' ? 'maple' : 'paquete', array_filter(explode(',', (string) $p['pres'])))) ?: 'Sin presentaciones activas') ?></p>
        </div>
        <?= pill($sl, $st) ?>
        <form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button name="move" value="up" class="grid h-9 w-9 place-items-center rounded-full border border-line hover:bg-cream-2 disabled:opacity-30" <?= $i === 0 ? 'disabled' : '' ?> aria-label="Subir <?= e($p['name']) ?>">↑</button>
          <button name="move" value="down" class="grid h-9 w-9 place-items-center rounded-full border border-line hover:bg-cream-2 disabled:opacity-30" <?= $i === count($products) - 1 ? 'disabled' : '' ?> aria-label="Bajar <?= e($p['name']) ?>">↓</button>
        </form>
        <?= link_btn(url('admin/producto.php?id=' . $p['id']), 'Editar') ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
<?php card_close(); ?>
<?php admin_foot();
