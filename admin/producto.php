<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';
require dirname(__DIR__) . '/inc/storage.php';

const GRADES = ['primera' => 'Primera', 'segunda' => 'Segunda', 'tercera' => 'Tercera', 'cuarta' => 'Cuarta', 'quinta' => 'Quinta'];
const P_STATUS = ['active' => 'Activo (visible)', 'draft' => 'Borrador (no visible)', 'inactive' => 'Oculto'];
const PRES = ['maple' => ['Maple · 30 huevos', 30], 'paquete' => ['Paquete · 10 maples (300 huevos)', 300]];

function slugify(string $s): string
{
    $s = strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s) ?? '', '-') ?: 'producto';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(['admin'], function (array $u) {
        $id = (int) post('id');
        $before = $id ? row('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
        if ($id && !$before) throw new UserError('El producto no existe.');

        if (post('action') === 'archive') {
            q("UPDATE products SET status = 'archived', deleted_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
            audit('archive', 'product', $id, $before, null, $u['id']);
            return ['Producto archivado. Ya no aparece en la página; los pedidos anteriores se conservan.', 'admin/productos.php'];
        }
        if (post('action') === 'remove_image') {
            q('UPDATE products SET image_path = NULL WHERE id = ?', [$id]);
            return ['Imagen quitada.', 'admin/producto.php?id=' . $id];
        }

        $name = post('name'); $grade = post('grade'); $status = post('status');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) throw new UserError('El nombre debe tener entre 2 y 60 caracteres.');
        if (!isset(GRADES[$grade])) throw new UserError('Elige una categoría válida.');
        if (!isset(P_STATUS[$status])) throw new UserError('Elige un estado válido.');
        $desc = mb_substr(post('description'), 0, 240);
        $badge = mb_substr(post('badge'), 0, 30) ?: null;
        $pres = array_values(array_intersect(array_keys(PRES), (array) ($_POST['pres'] ?? [])));
        if ($status === 'active' && !$pres) throw new UserError('Un producto activo necesita al menos una presentación.');
        $img = save_image($_FILES['image'] ?? [], 'productos', 600);

        if ($id) {
            q('UPDATE products SET name = ?, grade = ?, description = ?, badge = ?, status = ?' . ($img ? ', image_path = ?' : '') . ' WHERE id = ?',
                array_merge([$name, $grade, $desc, $badge, $status], $img ? [$img] : [], [$id]));
        } else {
            $slug = slugify($name); $base = $slug; $n = 2;
            while (val('SELECT 1 FROM products WHERE slug = ?', [$slug])) $slug = $base . '-' . $n++;
            q('INSERT INTO products (slug, name, grade, description, badge, status, image_path, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$slug, $name, $grade, $desc, $badge, $status, $img, (int) val('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM products')]);
            $id = (int) db()->lastInsertId();
        }
        foreach (PRES as $k => [$label, $units]) {
            $on = in_array($k, $pres, true) ? 1 : 0;
            q('INSERT INTO product_variants (product_id, presentation, units, label, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)', [$id, $k, $units, $label, $k === 'maple' ? 1 : 2, $on]);
            $vid = (int) val('SELECT id FROM product_variants WHERE product_id = ? AND presentation = ?', [$id, $k]);
            q("INSERT IGNORE INTO inventory_status (variant_id, status) VALUES (?, 'por_confirmar')", [$vid]);
        }
        audit($before ? 'update' : 'create', 'product', $id, $before, row('SELECT * FROM products WHERE id = ?', [$id]), $u['id']);
        return [$before ? 'Producto guardado.' : 'Producto creado. Recuerda ponerle precio en Precios del día.', 'admin/producto.php?id=' . $id];
    });
}

$u = require_user(['admin']);
$id = (int) ($_GET['id'] ?? 0);
$p = $id ? row('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]) : null;
if ($id && !$p) redirect('admin/productos.php');
$active = $p ? array_column(rows('SELECT presentation FROM product_variants WHERE product_id = ? AND is_active = 1', [$id]), 'presentation') : ['maple', 'paquete'];
admin_head($p ? $p['name'] : 'Nuevo producto', $u, 'productos.php');
?>
<p class="mt-1 text-ink-2"><a href="<?= e(url('admin/productos.php')) ?>" class="underline underline-offset-4">Productos</a> › <?= $p ? 'Editar' : 'Nuevo' ?></p>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
  <?php card_open('Datos del producto'); ?>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div><?= label('name', 'Nombre', true) ?><input id="name" name="name" required maxlength="60" value="<?= e($p['name'] ?? '') ?>" class="field"></div>
      <div><?= label('grade', 'Categoría', true) ?><select id="grade" name="grade" class="field"><?= select_options(GRADES, $p['grade'] ?? 'primera') ?></select></div>
      <div class="md:col-span-2"><?= label('description', 'Descripción corta') ?><input id="description" name="description" maxlength="240" value="<?= e($p['description'] ?? '') ?>" class="field" placeholder="Ej.: Huevo grande, ideal para venta."></div>
      <div><?= label('badge', 'Etiqueta destacada (opcional)') ?><input id="badge" name="badge" maxlength="30" value="<?= e($p['badge'] ?? '') ?>" class="field" placeholder="Ej.: La más pedida"></div>
      <div><?= label('status', 'Estado', true) ?><select id="status" name="status" class="field"><?= select_options(P_STATUS, $p['status'] ?? 'draft') ?></select></div>
    </div>
    <fieldset class="mt-5"><legend class="mb-2 text-sm font-medium">Presentaciones a la venta</legend>
      <div class="flex flex-wrap gap-3">
        <?php foreach (PRES as $k => [$l]): ?>
          <label class="flex items-center gap-2 rounded-xl border border-line px-3 py-2 text-sm"><input type="checkbox" name="pres[]" value="<?= $k ?>" <?= in_array($k, $active, true) ? 'checked' : '' ?> class="h-4 w-4 accent-forest"> <?= e($l) ?></label>
        <?php endforeach; ?>
      </div></fieldset>
  <?php card_close(); ?>
  <?php card_open('Imagen (opcional)'); ?>
    <p class="mt-1 text-sm text-ink-2">JPG, PNG o WebP de hasta 5 MB. Se optimiza automáticamente. Sin imagen se muestra un huevo ilustrado.</p>
    <div class="mt-4 flex flex-wrap items-center gap-4">
      <img id="img-prev" src="<?= $p && $p['image_path'] ? e(url($p['image_path'])) : '' ?>" alt="Vista previa de la imagen" class="egg h-24 w-20 object-cover" <?= $p && $p['image_path'] ? '' : 'hidden' ?>>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="img-prev" class="text-sm" aria-label="Elegir imagen">
    </div>
  <?php card_close(); ?>
  <div class="mt-6 flex flex-wrap gap-2">
    <?= btn($p ? 'Guardar cambios' : 'Crear producto', 'primary') ?>
    <?php if ($p && $p['image_path']): ?><?= btn('Quitar imagen', 'ghost', 'name="action" value="remove_image"') ?><?php endif; ?>
    <?php if ($p): ?><?= btn('Archivar producto', 'danger', 'name="action" value="archive" formnovalidate data-confirm="¿Archivar este producto? Dejará de verse en la página."') ?><?php endif; ?>
  </div>
</form>
<?php admin_foot();
