<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';
require dirname(__DIR__) . '/inc/storage.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(['admin'], function (array $u) {
        $before = settings(true);
        $action = post('action');
        if ($action === 'remove_logo') { save_setting('logo_path', null, $u['id']); return 'Logo quitado; se muestra la marca provisoria.'; }
        if ($action === 'remove_hero') { save_setting('hero_image_path', null, $u['id']); return 'Imagen de portada restablecida.'; }

        $name = post('business_name');
        if (mb_strlen($name) < 2) throw new UserError('Escribe el nombre del negocio.');
        $social = [];
        foreach (['instagram', 'tiktok', 'facebook'] as $k) {
            $v = post($k);
            if ($v !== '' && (!filter_var($v, FILTER_VALIDATE_URL) || !str_starts_with($v, 'https://'))) throw new UserError("El enlace de $k debe empezar con https://");
            $social[$k] = $v;
        }
        $stale = (int) post('price_stale_after_hours');
        if ($stale < 12 || $stale > 168) throw new UserError('Las horas para considerar los precios desactualizados deben estar entre 12 y 168.');
        $routing = post('zone_routing_enabled') === '1';
        if ($routing && !$before['zone_routing_enabled'] && post('routing_approved') !== '1') throw new UserError('Para activar la asignación por zona marca la casilla de aprobación.');

        save_setting('business_name', mb_substr($name, 0, 80), $u['id']);
        save_setting('tagline', mb_substr(post('tagline'), 0, 120), $u['id']);
        save_setting('slogan', mb_substr(post('slogan'), 0, 120), $u['id']);
        save_setting('city', mb_substr(post('city'), 0, 80), $u['id']);
        save_setting('legal_text', mb_substr(post('legal_text'), 0, 500), $u['id']);
        save_setting('social', $social, $u['id']);
        save_setting('price_stale_after_hours', $stale, $u['id']);
        save_setting('zone_routing_enabled', $routing, $u['id']);
        save_setting('inventory_quantities_enabled', post('inventory_quantities_enabled') === '1', $u['id']);
        if ($logo = save_image($_FILES['logo'] ?? [], 'marca', 400)) save_setting('logo_path', $logo, $u['id']);
        if ($hero = save_image($_FILES['hero'] ?? [], 'portada', 1400)) save_setting('hero_image_path', $hero, $u['id']);
        audit('update', 'settings', null, $before, settings(true), $u['id']);
        return 'Configuración guardada.';
    }, url('admin/configuracion.php'));
}

$u = require_user(['admin']);
$s = settings();
$audits = rows('SELECT a.*, p.full_name FROM audit_logs a LEFT JOIN profiles p ON p.id = a.actor_id ORDER BY a.created_at DESC LIMIT 25');
admin_head('Configuración', $u, 'configuracion.php');
$in = fn($id, $val, $max, $type = 'text') => '<input id="' . $id . '" name="' . $id . '" type="' . $type . '" maxlength="' . $max . '" value="' . e($val) . '" class="field">';
?>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php card_open('Negocio'); ?>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div><?= label('business_name', 'Nombre', true) ?><?= $in('business_name', $s['business_name'] ?? '', 80) ?></div>
      <div><?= label('city', 'Ciudad') ?><?= $in('city', $s['city'] ?? '', 80) ?></div>
      <div><?= label('tagline', 'Frase de marca') ?><?= $in('tagline', $s['tagline'] ?? '', 120) ?></div>
      <div><?= label('slogan', 'Eslogan') ?><?= $in('slogan', $s['slogan'] ?? '', 120) ?></div>
      <div class="md:col-span-2"><?= label('legal_text', 'Texto informativo del pie de página') ?><textarea id="legal_text" name="legal_text" rows="2" maxlength="500" class="field"><?= e($s['legal_text'] ?? '') ?></textarea></div>
    </div>
  <?php card_close(); ?>
  <?php card_open('Redes sociales'); ?>
    <div class="mt-4 grid gap-4 md:grid-cols-3">
      <?php foreach (['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'] as $k => $l): ?>
        <div><?= label($k, $l) ?><?= $in($k, $s['social'][$k] ?? '', 200, 'url') ?></div>
      <?php endforeach; ?>
    </div>
  <?php card_close(); ?>
  <?php card_open('Imágenes'); ?>
    <div class="mt-4 grid gap-6 md:grid-cols-2">
      <div><p class="text-sm font-medium">Logo oficial</p><p class="text-xs text-ink-2">Cuadrado, JPG/PNG/WebP. Mientras no subas uno se usa la marca provisoria.</p>
        <div class="mt-3 flex items-center gap-4"><span class="grid h-16 w-16 place-items-center rounded-full bg-forest"><?= !empty($s['logo_path']) ? '<img id="logo-prev" src="' . e(url($s['logo_path'])) . '" alt="Logo actual" class="h-14 w-14 rounded-full object-cover">' : '<img id="logo-prev" alt="Vista previa del logo" class="h-14 w-14 rounded-full object-cover" hidden>' . logo_mark(44) ?></span>
          <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-preview="logo-prev" class="text-sm" aria-label="Subir logo"></div></div>
      <div><p class="text-sm font-medium">Foto de portada</p><p class="text-xs text-ink-2">Vertical (4:5) de preferencia.</p>
        <div class="mt-3 flex items-center gap-4"><img id="hero-prev" src="<?= e(url($s['hero_image_path'] ?? 'assets/img/gallina-huevo.webp')) ?>" alt="Portada actual" class="egg h-20 w-16 object-cover">
          <input type="file" name="hero" accept="image/jpeg,image/png,image/webp" data-preview="hero-prev" class="text-sm" aria-label="Subir foto de portada"></div></div>
    </div>
  <?php card_close(); ?>
  <?php card_open('Precios, pedidos e inventario'); ?>
    <div class="mt-4 space-y-5">
      <div class="max-w-xs"><?= label('price_stale_after_hours', 'Mostrar los precios como desactualizados después de (horas)') ?><input id="price_stale_after_hours" name="price_stale_after_hours" type="number" min="12" max="168" value="<?= (int) ($s['price_stale_after_hours'] ?? 36) ?>" class="field"></div>
      <fieldset class="rounded-2xl border border-line p-4"><legend class="px-1 text-sm font-semibold">Asignación de pedidos por zona</legend>
        <p class="text-sm text-ink-2">Desactivada: todos los pedidos van al contacto principal. Activada: van al contacto de la zona que elige el cliente (si no hay, al principal).</p>
        <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="zone_routing_enabled" value="1" <?= !empty($s['zone_routing_enabled']) ? 'checked' : '' ?> class="h-5 w-5 accent-forest"> Activar asignación por zona</label>
        <?php if (empty($s['zone_routing_enabled'])): ?><label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="routing_approved" value="1" class="h-5 w-5 accent-forest"> La empresa aprobó esta regla</label><?php endif; ?>
      </fieldset>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="inventory_quantities_enabled" value="1" <?= !empty($s['inventory_quantities_enabled']) ? 'checked' : '' ?> class="h-5 w-5 accent-forest"> Registrar cantidades en el inventario (además del estado)</label>
    </div>
  <?php card_close(); ?>
  <div class="mt-6 flex flex-wrap gap-2">
    <?= btn('Guardar configuración', 'primary') ?>
    <?php if (!empty($s['logo_path'])): ?><?= btn('Quitar logo', 'ghost', 'name="action" value="remove_logo" formnovalidate') ?><?php endif; ?>
    <?php if (!empty($s['hero_image_path'])): ?><?= btn('Restablecer portada', 'ghost', 'name="action" value="remove_hero" formnovalidate') ?><?php endif; ?>
  </div>
</form>

<?php card_open('Registro de cambios recientes'); ?>
  <?php if (!$audits): ?><?= empty_state('Sin cambios registrados.') ?><?php else: ?>
  <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm">
    <thead><tr class="text-ink-2"><th class="py-2 font-medium">Fecha</th><th class="py-2 font-medium">Usuario</th><th class="py-2 font-medium">Acción</th><th class="py-2 font-medium">Sobre</th></tr></thead>
    <tbody><?php foreach ($audits as $a): ?><tr class="border-t border-line"><td class="py-2 text-ink-2"><?= e(fecha_hora($a['created_at'])) ?></td><td class="py-2"><?= e($a['full_name'] ?? '—') ?></td><td class="py-2"><?= e($a['action']) ?></td><td class="py-2"><?= e($a['entity']) ?><?= $a['entity_id'] ? ' #' . e($a['entity_id']) : '' ?></td></tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
<?php card_close(); ?>
<?php admin_foot();
