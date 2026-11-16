<?php
// Textos de la página (borrador → vista previa → publicar), contactos de WhatsApp y zonas.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

const BENEFIT_ICONS = ['egg' => 'Huevo', 'star' => 'Estrella', 'leaf' => 'Hoja', 'home' => 'Casa', 'shield' => 'Escudo', 'sun' => 'Sol'];

function clean_text(string $key, int $max): string { return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max); }
function clean_list(string $key, array $fields, int $maxItems): array
{
    $out = [];
    foreach (array_slice((array) ($_POST[$key] ?? []), 0, $maxItems) as $row) {
        $item = [];
        foreach ($fields as $f => $max) $item[$f] = mb_substr(trim((string) ($row[$f] ?? '')), 0, $max);
        if (implode('', array_diff_key($item, ['icon' => 1])) !== '') $out[] = $item;
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    handle_post(['admin'], function (array $u) use ($action) {
        if ($action === 'content') {
            $benefits = clean_list('benefits', ['icon' => 10, 'title' => 60, 'text' => 200], 6);
            foreach ($benefits as &$b) if (!isset(BENEFIT_ICONS[$b['icon']])) $b['icon'] = 'egg';
            unset($b);
            $draft = [
                'hero' => ['eyebrow' => clean_text('hero_eyebrow', 80), 'title' => clean_text('hero_title', 90), 'subtitle' => clean_text('hero_subtitle', 260),
                    'primary_cta' => clean_text('hero_primary', 30) ?: 'Ver precios de hoy', 'secondary_cta' => clean_text('hero_secondary', 30) ?: 'Hacer pedido'],
                'contacts' => ['title' => clean_text('contacts_title', 80), 'text' => clean_text('contacts_text', 300)],
                'benefits' => ['title' => clean_text('benefits_title', 80), 'items' => $benefits],
                'brand' => ['title' => clean_text('brand_title', 80), 'text' => clean_text('brand_text', 1500)],
                'faq' => ['title' => clean_text('faq_title', 80), 'items' => clean_list('faq', ['q' => 160, 'a' => 600], 12)],
                'order' => ['notice' => clean_text('order_notice', 300)],
            ];
            if (mb_strlen($draft['hero']['title']) < 3) throw new UserError('El título de la portada no puede quedar vacío.');
            foreach ($draft as $k => $v) q('UPDATE content_sections SET draft = ?, updated_by = ? WHERE `key` = ?', [json_encode($v, JSON_UNESCAPED_UNICODE), $u['id'], $k]);
            if (post('then') === 'publish') {
                q('UPDATE content_sections SET published = draft, published_at = UTC_TIMESTAMP(), updated_by = ?', [$u['id']]);
                audit('publish', 'content', null, null, $draft, $u['id']);
                return 'Contenido publicado. Ya se ve en la página.';
            }
            audit('update', 'content', null, null, $draft, $u['id']);
            return 'Borrador guardado. Usa “Vista previa” para revisarlo antes de publicar.';
        }
        if ($action === 'discard') {
            q('UPDATE content_sections SET draft = published, updated_by = ?', [$u['id']]);
            return 'Se descartaron los cambios sin publicar.';
        }
        if ($action === 'contacts') {
            $primary = (int) post('primary');
            $rows = (array) ($_POST['c'] ?? []);
            foreach ($rows as $id => $r) {
                $id = (int) $id;
                $name = mb_substr(trim((string) ($r['name'] ?? '')), 0, 60);
                $phone = normalize_phone((string) ($r['phone'] ?? ''));
                $isNew = $id === 0;
                if ($isNew && $name === '' && $phone === '') continue;
                if (mb_strlen($name) < 2) throw new UserError('Cada contacto necesita un nombre.');
                if (!preg_match('/^\d{7,15}$/', $phone)) throw new UserError("El teléfono de $name no es válido.");
                $zone = (int) ($r['zone_id'] ?? 0) ?: null;
                $role = mb_substr(trim((string) ($r['role_label'] ?? '')), 0, 60);
                $active = !empty($r['active']) ? 1 : 0;
                $sort = (int) ($r['sort'] ?? 0);
                if ($isNew) {
                    q('INSERT INTO business_contacts (name, phone, zone_id, role_label, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)', [$name, $phone, $zone, $role, $active, $sort]);
                } elseif (!empty($r['delete'])) {
                    if ($id === $primary) throw new UserError('No puedes eliminar el contacto principal. Elige otro como principal primero.');
                    q('UPDATE business_contacts SET deleted_at = UTC_TIMESTAMP(), is_active = 0, is_primary = 0 WHERE id = ?', [$id]);
                } else {
                    q('UPDATE business_contacts SET name = ?, phone = ?, zone_id = ?, role_label = ?, is_active = ?, sort_order = ? WHERE id = ? AND deleted_at IS NULL', [$name, $phone, $zone, $role, $active, $sort, $id]);
                }
            }
            if (!val('SELECT 1 FROM business_contacts WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [$primary])) throw new UserError('El contacto principal debe estar activo: los pedidos llegan a ese número.');
            q('UPDATE business_contacts SET is_primary = (id = ?)', [$primary]);
            audit('update', 'contacts', null, null, null, $u['id']);
            return 'Contactos guardados. Los cambios ya se ven en la página.';
        }
        if ($action === 'zones') {
            foreach ((array) ($_POST['z'] ?? []) as $id => $r) {
                $name = mb_substr(trim((string) ($r['name'] ?? '')), 0, 60);
                if ((int) $id === 0) {
                    if ($name === '') continue;
                    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(strtr($name, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']))) ?? '', '-');
                    q('INSERT INTO zones (slug, name, sort_order) VALUES (?, ?, ?)', [$slug . '-' . substr(bin2hex(random_bytes(2)), 0, 4), $name, (int) val('SELECT COALESCE(MAX(sort_order),0)+1 FROM zones')]);
                } else {
                    if (mb_strlen($name) < 2) throw new UserError('Cada zona necesita un nombre.');
                    q('UPDATE zones SET name = ?, is_active = ? WHERE id = ?', [$name, !empty($r['active']) ? 1 : 0, (int) $id]);
                }
            }
            return 'Zonas guardadas.';
        }
        throw new UserError('Acción no válida.');
    }, url('admin/contenido.php'));
}

$u = require_user(['admin']);
$c = content_sections('draft');
$pending = (int) val('SELECT COUNT(*) FROM content_sections WHERE draft <> published');
$contacts = rows('SELECT * FROM business_contacts WHERE deleted_at IS NULL ORDER BY is_primary DESC, sort_order');
$zones = rows('SELECT * FROM zones WHERE deleted_at IS NULL ORDER BY sort_order');
$zoneOpts = array_column($zones, 'name', 'id');
$ta = fn($id, $name, $val, $max, $rows = 2) => '<textarea id="' . $id . '" name="' . $name . '" rows="' . $rows . '" maxlength="' . $max . '" class="field">' . e($val) . '</textarea>';
$in = fn($id, $name, $val, $max) => '<input id="' . $id . '" name="' . $name . '" maxlength="' . $max . '" value="' . e($val) . '" class="field">';
admin_head('Contenido', $u, 'contenido.php');
?>
<p class="mt-1 text-ink-2">Los textos se guardan como borrador: revisa la vista previa y luego publica. Los contactos y zonas se aplican al guardar.</p>
<?php if ($pending): ?><p role="status" class="mt-4 rounded-2xl bg-yolk-soft p-4 text-sm text-wood">Hay cambios de texto sin publicar.</p><?php endif; ?>

<form method="post" id="content-form">
  <?= csrf_field() ?><input type="hidden" name="action" value="content">
  <?php card_open('Portada'); ?>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div><?= label('hero_eyebrow', 'Frase superior') ?><?= $in('hero_eyebrow', 'hero_eyebrow', $c['hero']['eyebrow'] ?? '', 80) ?></div>
      <div><?= label('hero_title', 'Título (la parte después de la coma va resaltada)', true) ?><?= $in('hero_title', 'hero_title', $c['hero']['title'] ?? '', 90) ?></div>
      <div class="md:col-span-2"><?= label('hero_subtitle', 'Subtítulo') ?><?= $ta('hero_subtitle', 'hero_subtitle', $c['hero']['subtitle'] ?? '', 260) ?></div>
      <div><?= label('hero_primary', 'Botón principal') ?><?= $in('hero_primary', 'hero_primary', $c['hero']['primary_cta'] ?? '', 30) ?></div>
      <div><?= label('hero_secondary', 'Botón secundario') ?><?= $in('hero_secondary', 'hero_secondary', $c['hero']['secondary_cta'] ?? '', 30) ?></div>
    </div>
  <?php card_close(); ?>

  <?php card_open('Sección de contactos'); ?>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
      <div><?= label('contacts_title', 'Título') ?><?= $in('contacts_title', 'contacts_title', $c['contacts']['title'] ?? '', 80) ?></div>
      <div><?= label('contacts_text', 'Texto') ?><?= $ta('contacts_text', 'contacts_text', $c['contacts']['text'] ?? '', 300) ?></div>
    </div>
  <?php card_close(); ?>

  <?php card_open('Beneficios'); ?>
    <p class="mt-1 text-sm text-ink-2">Solo afirmaciones confirmadas por la empresa (sin certificaciones ni datos no verificados).</p>
    <div class="mt-4"><?= label('benefits_title', 'Título') ?><?= $in('benefits_title', 'benefits_title', $c['benefits']['title'] ?? '', 80) ?></div>
    <div>
      <ul class="mt-4 space-y-3" data-list="tpl-benefit" data-max="6">
        <?php foreach ($c['benefits']['items'] ?? [] as $b): ?>
          <li data-row class="grid gap-2 rounded-2xl border border-line p-3 md:grid-cols-[140px_1fr_2fr_auto] md:items-start">
            <select data-name="benefits[#][icon]" class="field" aria-label="Ícono"><?= select_options(BENEFIT_ICONS, $b['icon'] ?? 'egg') ?></select>
            <input data-name="benefits[#][title]" value="<?= e($b['title'] ?? '') ?>" maxlength="60" class="field" aria-label="Título del beneficio" placeholder="Título">
            <textarea data-name="benefits[#][text]" rows="2" maxlength="200" class="field" aria-label="Texto del beneficio" placeholder="Texto"><?= e($b['text'] ?? '') ?></textarea>
            <div class="flex gap-1"><button type="button" data-up class="grid h-9 w-9 place-items-center rounded-full border border-line" aria-label="Subir">↑</button><button type="button" data-down class="grid h-9 w-9 place-items-center rounded-full border border-line" aria-label="Bajar">↓</button><button type="button" data-remove-row class="grid h-9 w-9 place-items-center rounded-full border border-line text-terracotta-2" aria-label="Quitar"><?= icon('trash', 16) ?></button></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <button type="button" data-add-row class="mt-3 text-sm font-semibold text-leaf-2 underline underline-offset-4">+ Agregar beneficio</button>
    </div>
    <template id="tpl-benefit"><li data-row class="grid gap-2 rounded-2xl border border-line p-3 md:grid-cols-[140px_1fr_2fr_auto] md:items-start">
      <select data-name="benefits[#][icon]" class="field" aria-label="Ícono"><?= select_options(BENEFIT_ICONS, 'egg') ?></select>
      <input data-name="benefits[#][title]" maxlength="60" class="field" aria-label="Título del beneficio" placeholder="Título">
      <textarea data-name="benefits[#][text]" rows="2" maxlength="200" class="field" aria-label="Texto del beneficio" placeholder="Texto"></textarea>
      <div class="flex gap-1"><button type="button" data-up class="grid h-9 w-9 place-items-center rounded-full border border-line" aria-label="Subir">↑</button><button type="button" data-down class="grid h-9 w-9 place-items-center rounded-full border border-line" aria-label="Bajar">↓</button><button type="button" data-remove-row class="grid h-9 w-9 place-items-center rounded-full border border-line text-terracotta-2" aria-label="Quitar"><?= icon('trash', 16) ?></button></div></li></template>
  <?php card_close(); ?>

  <?php card_open('La granja'); ?>
    <div class="mt-4 space-y-4">
      <div><?= label('brand_title', 'Título') ?><?= $in('brand_title', 'brand_title', $c['brand']['title'] ?? '', 80) ?></div>
      <div><?= label('brand_text', 'Texto (deja una línea en blanco para separar párrafos)') ?><?= $ta('brand_text', 'brand_text', $c['brand']['text'] ?? '', 1500, 5) ?></div>
    </div>
  <?php card_close(); ?>

  <?php card_open('Preguntas frecuentes'); ?>
    <div class="mt-4"><?= label('faq_title', 'Título') ?><?= $in('faq_title', 'faq_title', $c['faq']['title'] ?? '', 80) ?></div>
    <div>
      <ul class="mt-4 space-y-3" data-list="tpl-faq" data-max="12">
        <?php foreach ($c['faq']['items'] ?? [] as $f): ?>
          <li data-row class="grid gap-2 rounded-2xl border border-line p-3 md:grid-cols-[1fr_2fr_auto] md:items-start">
            <input data-name="faq[#][q]" value="<?= e($f['q'] ?? '') ?>" maxlength="160" class="field" aria-label="Pregunta" placeholder="Pregunta">
            <textarea data-name="faq[#][a]" rows="2" maxlength="600" class="field" aria-label="Respuesta" placeholder="Respuesta"><?= e($f['a'] ?? '') ?></textarea>
            <div class="flex gap-1"><button type="button" data-up class="grid h-9 w-9 place-items-center rounded-full border border-line" aria-label="Subir">↑</button><button type="button" data-down class="grid h-9 w-9 place-items-center rounded-full border border-line" aria-label="Bajar">↓</button><button type="button" data-remove-row class="grid h-9 w-9 place-items-center rounded-full border border-line text-terracotta-2" aria-label="Quitar"><?= icon('trash', 16) ?></button></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <button type="button" data-add-row class="mt-3 text-sm font-semibold text-leaf-2 underline underline-offset-4">+ Agregar pregunta</button>
    </div>
    <template id="tpl-faq"><li data-row class="grid gap-2 rounded-2xl border border-line p-3 md:grid-cols-[1fr_2fr_auto] md:items-start">
      <input data-name="faq[#][q]" maxlength="160" class="field" aria-label="Pregunta" placeholder="Pregunta">
      <textarea data-name="faq[#][a]" rows="2" maxlength="600" class="field" aria-label="Respuesta" placeholder="Respuesta"></textarea>
      <div class="flex gap-1"><button type="button" data-up class="grid h-9 w-9 place-items-center rounded-full border border-line" aria-label="Subir">↑</button><button type="button" data-down class="grid h-9 w-9 place-items-center rounded-full border border-line" aria-label="Bajar">↓</button><button type="button" data-remove-row class="grid h-9 w-9 place-items-center rounded-full border border-line text-terracotta-2" aria-label="Quitar"><?= icon('trash', 16) ?></button></div></li></template>
  <?php card_close(); ?>

  <?php card_open('Aviso del pedido'); ?>
    <div class="mt-4"><?= label('order_notice', 'Texto junto al botón de enviar') ?><?= $ta('order_notice', 'order_notice', $c['order']['notice'] ?? '', 300) ?></div>
  <?php card_close(); ?>

  <div class="sticky bottom-0 z-10 -mx-4 mt-6 flex flex-wrap gap-2 border-t border-line bg-cream/95 px-4 py-3 backdrop-blur md:-mx-8 md:px-8">
    <?= btn('Guardar borrador', 'ghost', 'name="then" value="draft"') ?>
    <?= link_btn(url('index.php?vista_previa=1'), 'Vista previa ↗', 'ghost', 'target="_blank" rel="noopener"') ?>
    <?= btn('Guardar y publicar', 'primary', 'name="then" value="publish" data-confirm="¿Publicar los textos? Se verán en la página de inmediato."') ?>
  </div>
</form>
<?php if ($pending): ?><form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="discard"><?= btn('Descartar cambios sin publicar', 'danger', 'data-confirm="¿Volver a los textos publicados?"') ?></form><?php endif; ?>

<?php card_open('Contactos de WhatsApp'); ?>
  <p class="mt-1 text-sm text-ink-2">Los pedidos llegan al <strong>contacto principal</strong><?= setting('zone_routing_enabled') ? ' o al contacto de la zona del cliente (asignación por zona activa)' : '' ?>.</p>
  <form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="contacts">
    <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left text-sm">
      <thead><tr class="text-ink-2"><th class="py-2 font-medium">Principal</th><th class="py-2 font-medium">Nombre</th><th class="py-2 font-medium">Teléfono</th><th class="py-2 font-medium">Zona</th><th class="py-2 font-medium">Rol</th><th class="py-2 font-medium">Orden</th><th class="py-2 font-medium">Activo</th><th class="py-2 font-medium">Eliminar</th></tr></thead>
      <tbody>
      <?php foreach (array_merge($contacts, [['id' => 0, 'name' => '', 'phone' => '', 'zone_id' => null, 'role_label' => '', 'sort_order' => 9, 'is_active' => 1, 'is_primary' => 0]]) as $ct): $cid = (int) $ct['id']; $n = "c[$cid]"; ?>
        <tr class="border-t border-line">
          <td class="py-2"><?php if ($cid): ?><input type="radio" name="primary" value="<?= $cid ?>" <?= $ct['is_primary'] ? 'checked' : '' ?> class="h-5 w-5 accent-forest" aria-label="Principal: <?= e($ct['name']) ?>"><?php else: ?><span class="text-xs text-ink-2">Nuevo</span><?php endif; ?></td>
          <td class="py-2 pr-2"><input name="<?= $n ?>[name]" value="<?= e($ct['name']) ?>" maxlength="60" class="field" aria-label="Nombre" <?= $cid ? 'required' : 'placeholder="Agregar contacto"' ?>></td>
          <td class="py-2 pr-2"><input name="<?= $n ?>[phone]" value="<?= e($ct['phone']) ?>" inputmode="tel" class="field tabular w-32" aria-label="Teléfono"></td>
          <td class="py-2 pr-2"><select name="<?= $n ?>[zone_id]" class="field" aria-label="Zona"><option value="">Todas</option><?= select_options($zoneOpts, $ct['zone_id'] ?? '') ?></select></td>
          <td class="py-2 pr-2"><input name="<?= $n ?>[role_label]" value="<?= e($ct['role_label']) ?>" maxlength="60" class="field" aria-label="Rol"></td>
          <td class="py-2 pr-2"><input name="<?= $n ?>[sort]" type="number" value="<?= (int) $ct['sort_order'] ?>" class="field w-16" aria-label="Orden"></td>
          <td class="py-2"><input type="checkbox" name="<?= $n ?>[active]" value="1" <?= $ct['is_active'] ? 'checked' : '' ?> class="h-5 w-5 accent-forest" aria-label="Activo"></td>
          <td class="py-2"><?php if ($cid): ?><input type="checkbox" name="<?= $n ?>[delete]" value="1" class="h-5 w-5 accent-terracotta" aria-label="Eliminar <?= e($ct['name']) ?>"><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <div class="mt-4"><?= btn('Guardar contactos', 'primary') ?></div>
  </form>
<?php card_close(); ?>

<?php card_open('Zonas'); ?>
  <form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="zones">
    <ul class="space-y-2">
      <?php foreach (array_merge($zones, [['id' => 0, 'name' => '', 'is_active' => 1]]) as $z): $zid = (int) $z['id']; ?>
        <li class="flex items-center gap-3"><input name="z[<?= $zid ?>][name]" value="<?= e($z['name']) ?>" maxlength="60" class="field max-w-xs" aria-label="Nombre de la zona" <?= $zid ? '' : 'placeholder="Agregar zona"' ?>>
          <?php if ($zid): ?><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="z[<?= $zid ?>][active]" value="1" <?= $z['is_active'] ? 'checked' : '' ?> class="h-5 w-5 accent-forest"> Activa</label><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <div class="mt-4"><?= btn('Guardar zonas', 'primary') ?></div>
  </form>
<?php card_close(); ?>
<?php admin_foot();
