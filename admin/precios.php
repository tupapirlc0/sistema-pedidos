<?php
// Precios del día: cada lista es un borrador que el administrador revisa y publica con una vigencia.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

const AVAIL_OPTS = ['disponible' => 'Disponible', 'poco_stock' => 'Poco stock', 'agotado' => 'Agotado', 'por_confirmar' => 'Consultar disponibilidad'];

function price_variants(): array
{
    return rows("SELECT v.id, v.presentation, v.product_id, p.name AS product, COALESCE(i.status, 'por_confirmar') AS inv
                 FROM product_variants v JOIN products p ON p.id = v.product_id LEFT JOIN inventory_status i ON i.variant_id = v.id
                 WHERE v.is_active = 1 AND p.status IN ('active','draft') AND p.deleted_at IS NULL
                 ORDER BY v.presentation = 'paquete', p.sort_order, p.name");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    handle_post(['admin'], function (array $u) use ($action) {
        if ($action === 'new') {
            $existing = val("SELECT id FROM price_publications WHERE status = 'draft' LIMIT 1");
            if ($existing) return ['Ya hay un borrador abierto. Continúa con ese.', 'admin/precios.php?id=' . $existing];
            // Se parte de los precios vigentes; si no hay, de la última lista guardada (por ejemplo, la de referencia)
            $base = [];
            foreach (current_prices() as $vid => $p) $base[$vid] = [$p['price'], $p['availability']];
            if (!$base) {
                $last = val("SELECT id FROM price_publications WHERE status <> 'discarded' ORDER BY created_at DESC LIMIT 1")
                    ?: val('SELECT id FROM price_publications ORDER BY created_at DESC LIMIT 1');
                if ($last) foreach (rows('SELECT variant_id, price, availability FROM price_history WHERE publication_id = ?', [$last]) as $r)
                    $base[(int) $r['variant_id']] = [$r['price'] === null ? null : (float) $r['price'], $r['availability']];
            }
            q("INSERT INTO price_publications (status, effective_from, created_by) VALUES ('draft', ?, ?)", [now_utc(), $u['id']]);
            $id = (int) db()->lastInsertId();
            foreach (price_variants() as $v) {
                [$price, $av] = $base[(int) $v['id']] ?? [null, $v['inv']];
                q('INSERT INTO price_history (publication_id, variant_id, price, availability, created_by) VALUES (?, ?, ?, ?, ?)', [$id, $v['id'], $price, $av, $u['id']]);
            }
            audit('create', 'price_publication', $id, null, null, $u['id']);
            return ['Borrador creado con los últimos precios. Revísalos y publícalos.', 'admin/precios.php?id=' . $id];
        }

        $id = (int) post('id');
        $pub = row("SELECT * FROM price_publications WHERE id = ? AND status = 'draft' FOR UPDATE", [$id]);
        if (!$pub) throw new UserError('Ese borrador ya no existe o ya fue publicado.');

        if ($action === 'discard') {
            q("UPDATE price_publications SET status = 'discarded' WHERE id = ?", [$id]);
            audit('discard', 'price_publication', $id, null, null, $u['id']);
            return ['Borrador descartado.', 'admin/precios.php'];
        }

        // Guardar (y publicar si corresponde)
        $effective = local_to_utc(post('effective_from'));
        if (!$effective) throw new UserError('Indica desde cuándo rigen los precios.');
        $note = mb_substr(post('note'), 0, 300);
        $before = rows('SELECT variant_id, price, availability FROM price_history WHERE publication_id = ?', [$id]);
        $after = [];
        foreach (price_variants() as $v) {
            $vid = (int) $v['id'];
            $raw = str_replace(',', '.', trim((string) ($_POST['price'][$vid] ?? '')));
            $av = (string) ($_POST['avail'][$vid] ?? 'por_confirmar');
            if (!isset(AVAIL_OPTS[$av])) $av = 'por_confirmar';
            $price = null;
            if ($raw !== '') {
                if (!is_numeric($raw) || (float) $raw <= 0 || (float) $raw > 99999) throw new UserError("Revisa el precio de {$v['product']} (" . ($v['presentation'] === 'maple' ? 'maple' : 'paquete') . '): debe ser un número mayor a 0.');
                $price = round((float) $raw, 2);
            }
            q('INSERT INTO price_history (publication_id, variant_id, price, availability, created_by) VALUES (?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE price = VALUES(price), availability = VALUES(availability)', [$id, $vid, $price, $av, $u['id']]);
            $after[] = ['variant_id' => $vid, 'price' => $price, 'availability' => $av];
        }
        q('UPDATE price_publications SET effective_from = ?, note = ? WHERE id = ?', [$effective, $note, $id]);

        if ($action !== 'publish') {
            audit('update', 'price_publication', $id, $before, $after, $u['id']);
            return ['Borrador guardado. Todavía no es visible en la página.', 'admin/precios.php?id=' . $id];
        }

        // Si algo impide publicar, el borrador queda guardado igual y se avisa
        $draftBack = 'admin/precios.php?id=' . $id;
        $staleH = (int) setting('price_stale_after_hours', 36);
        $problem = match (true) {
            post('confirm') !== '1' => 'Borrador guardado, pero no publicado: marca la casilla de confirmación para publicar.',
            strtotime($effective . ' UTC') < time() - $staleH * 3600 => "Borrador guardado, pero no publicado: la vigencia es de hace más de $staleH horas y la página la mostraría como desactualizada. Usa la fecha y hora de hoy.",
            strtotime($effective . ' UTC') > time() + 30 * 86400 => 'Borrador guardado, pero no publicado: la vigencia no puede ser de más de 30 días en el futuro.',
            !array_filter($after, fn($r) => $r['price'] !== null) => 'Borrador guardado, pero no publicado: escribe al menos un precio.',
            default => null,
        };
        if ($problem) {
            audit('update', 'price_publication', $id, $before, $after, $u['id']);
            return [$problem, $draftBack, 'error'];
        }

        q("UPDATE price_publications SET status = 'published', published_at = UTC_TIMESTAMP(), published_by = ? WHERE id = ?", [$u['id'], $id]);
        // Con vigencia inmediata, la disponibilidad elegida pasa al inventario
        $now = strtotime($effective . ' UTC') <= time();
        if ($now) foreach ($after as $r) {
            q('INSERT INTO inventory_status (variant_id, status, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), updated_by = VALUES(updated_by)',
                [$r['variant_id'], $r['availability'], $u['id']]);
        }
        audit('publish', 'price_publication', $id, $before, $after, $u['id']);
        return [$now ? 'Precios publicados. Ya se ven en la página.' : 'Precios programados para el ' . fecha_hora($effective) . '.', 'admin/precios.php'];
    }, url('admin/precios.php' . ((int) post('id') ? '?id=' . (int) post('id') : '')));
}

$u = require_user(['admin']);
$id = (int) ($_GET['id'] ?? 0);
$draft = $id ? row("SELECT * FROM price_publications WHERE id = ? AND status = 'draft'", [$id]) : null;
$openDraft = row("SELECT id, created_at, is_reference FROM price_publications WHERE status = 'draft' ORDER BY created_at DESC LIMIT 1");
$pd = public_data();

admin_head('Precios del día', $u, 'precios.php');

if ($draft):
    $vals = [];
    foreach (rows('SELECT variant_id, price, availability FROM price_history WHERE publication_id = ?', [$draft['id']]) as $r) $vals[(int) $r['variant_id']] = $r;
    $variants = price_variants();
    $effLocal = local_input($draft['effective_from']);
    $staleH = (int) setting('price_stale_after_hours', 36);
    $oldEff = strtotime($draft['effective_from'] . ' UTC') < time() - $staleH * 3600;
    ?>
  <p class="mt-1 text-ink-2"><a href="<?= e(url('admin/precios.php')) ?>" class="underline underline-offset-4">Precios del día</a> › Borrador</p>
  <?php if ($draft['is_reference']): ?>
    <div role="note" class="mt-6 flex gap-3 rounded-[22px] border border-yolk bg-yolk-soft p-5 text-wood"><?= icon('alert', 22, 'mt-0.5 shrink-0') ?>
      <p><strong>Precios de referencia del afiche del 20/10/2026.</strong> No son precios actuales: confírmalos o corrígelos, pon la vigencia de hoy y recién entonces publícalos.</p></div>
  <?php endif; ?>
  <form method="post" class="mt-6" data-package-discount="<?= e((string) (float) setting('package_discount', 20)) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $draft['id'] ?>">
    <p class="mt-1 text-sm text-ink-2">Al escribir el precio del maple, el del paquete se completa solo: 10 maples − Bs <?= e((string) (float) setting('package_discount', 20)) ?>. Puedes cambiarlo a mano.<?= stock_in_use() ? ' La disponibilidad de la página sale del <a class="underline" href="' . e(url('admin/stock.php')) . '">stock real</a>.' : '' ?></p>
    <?php foreach (['maple' => 'Por maple (30 huevos)', 'paquete' => 'Por paquete (10 maples)'] as $pres => $title): ?>
      <?php card_open($title); ?>
        <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm">
          <thead><tr class="text-ink-2"><th class="py-2 font-medium">Categoría</th><th class="py-2 font-medium">Precio (Bs)</th><th class="py-2 font-medium">Disponibilidad</th></tr></thead>
          <tbody>
          <?php foreach ($variants as $v): if ($v['presentation'] !== $pres) continue; $r = $vals[(int) $v['id']] ?? null; $pid = 'p' . $v['id']; ?>
            <tr class="border-t border-line">
              <th scope="row" class="py-2.5 pr-3 font-semibold text-forest"><label for="<?= $pid ?>"><?= e($v['product']) ?></label></th>
              <td class="py-2.5 pr-3"><input id="<?= $pid ?>" name="price[<?= (int) $v['id'] ?>]" <?= $pres === 'maple' ? 'data-maple-of' : 'data-pack-of' ?>="<?= (int) $v['product_id'] ?>" inputmode="decimal" placeholder="Por confirmar" value="<?= $r && $r['price'] !== null ? e(rtrim(rtrim($r['price'], '0'), '.')) : '' ?>" class="field tabular max-w-[150px]"></td>
              <td class="py-2.5"><select name="avail[<?= (int) $v['id'] ?>]" class="field max-w-[230px]" aria-label="Disponibilidad de <?= e($v['product']) ?>"><?= select_options(AVAIL_OPTS, $r['availability'] ?? $v['inv']) ?></select></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
      <?php card_close(); ?>
    <?php endforeach; ?>
    <p class="mt-3 text-sm text-ink-2">Deja el precio vacío para mostrar “precio por confirmar”. Los descuentos por volumen no se calculan automáticamente.</p>

    <?php card_open('Vigencia y publicación'); ?>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div><?= label('effective_from', 'Rigen desde (hora de Bolivia)', true) ?>
          <input id="effective_from" name="effective_from" type="datetime-local" required value="<?= e($effLocal) ?>" class="field" <?= $oldEff ? 'aria-describedby="eff-help"' : '' ?>>
          <?php if ($oldEff): ?><p id="eff-help" class="mt-1 text-sm text-terracotta-2">Esta fecha es antigua. Para publicar, cámbiala a hoy.</p><?php endif; ?></div>
        <div><?= label('note', 'Nota interna (opcional)') ?><input id="note" name="note" maxlength="300" value="<?= e($draft['note']) ?>" class="field"></div>
      </div>
      <label class="mt-5 flex items-start gap-3 rounded-2xl bg-cream-2 p-4 text-sm">
        <input type="checkbox" name="confirm" value="1" class="mt-0.5 h-5 w-5 accent-forest">
        <span><strong>Confirmo que estos son los precios actuales</strong> y que pueden mostrarse al público desde la fecha indicada.</span>
      </label>
      <p class="mt-3 text-xs text-ink-2">Si la vigencia es inmediata, la disponibilidad elegida también actualiza el inventario.</p>
      <div class="mt-5 flex flex-wrap gap-2">
        <?= btn('Publicar precios', 'primary', 'name="action" value="publish"') ?>
        <?= btn('Guardar borrador', 'ghost', 'name="action" value="save"') ?>
        <?= btn('Descartar borrador', 'danger', 'name="action" value="discard" data-confirm="¿Descartar este borrador? No se puede deshacer."') ?>
      </div>
    <?php card_close(); ?>
  </form>
<?php else:
    $published = rows("SELECT p.*, pr.full_name AS publisher, (SELECT COUNT(*) FROM price_history h WHERE h.publication_id = p.id AND h.price IS NOT NULL) AS priced
                       FROM price_publications p LEFT JOIN profiles pr ON pr.id = p.published_by WHERE p.status = 'published' ORDER BY p.effective_from DESC LIMIT 30");
    $current = current_prices();
    ?>
  <p class="mt-1 text-ink-2">Los precios se publican como una lista con fecha de vigencia. Mientras no publiques, la página muestra “precio por confirmar”.</p>
  <div class="mt-6 grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
    <div class="rounded-[22px] border border-line bg-paper p-5">
      <p class="text-sm text-ink-2">Estado en la página</p>
      <p class="mt-1 text-lg font-semibold text-forest">
        <?= $pd['prices']['status'] === 'vigente' ? 'Vigentes desde el ' . e(fecha_hora($pd['prices']['effective_from']))
          : ($pd['prices']['status'] === 'desactualizado' ? 'Desactualizados (del ' . e(fecha_hora($pd['prices']['effective_from'])) . ')' : 'Sin precios publicados') ?></p>
      <?php if ($pd['prices']['next_effective_from']): ?><p class="text-sm text-ink-2">Próxima lista programada: <?= e(fecha_hora($pd['prices']['next_effective_from'])) ?></p><?php endif; ?>
    </div>
    <?php if ($openDraft): ?>
      <?= link_btn(url('admin/precios.php?id=' . $openDraft['id']), 'Continuar borrador' . ($openDraft['is_reference'] ? ' de referencia' : ''), 'primary') ?>
    <?php else: ?>
      <form method="post"><?= csrf_field() ?><?= btn('Nueva lista de precios', 'primary', 'name="action" value="new"') ?></form>
    <?php endif; ?>
  </div>

  <?php card_open('Precios vigentes'); ?>
    <?php if (!$current): ?><?= empty_state('Todavía no hay precios publicados.') ?>
    <?php else: ?>
      <div class="mt-3 grid gap-6 md:grid-cols-2">
        <?php foreach (['maple' => 'Por maple', 'paquete' => 'Por paquete'] as $pres => $t): ?>
          <div><h3 class="text-sm font-semibold text-wood"><?= $t ?></h3>
            <ul class="mt-2 divide-y divide-line text-sm">
              <?php foreach ($pd['products'] as $p) foreach ($p['variants'] as $v): if ($v['presentation'] !== $pres) continue; ?>
                <li class="flex justify-between py-2"><span><?= e($p['name']) ?></span><span class="tabular font-semibold"><?= $v['availability'] === 'agotado' ? 'Agotado' : ($v['price'] === null ? 'Por confirmar' : e(bs($v['price']))) ?></span></li>
              <?php endforeach; ?>
            </ul></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php card_close(); ?>

  <?php card_open('Historial de publicaciones'); ?>
    <?php if (!$published): ?><?= empty_state('Aún no se publicó ninguna lista.') ?>
    <?php else: ?>
    <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[560px] text-left text-sm">
      <thead><tr class="text-ink-2"><th class="py-2 font-medium">Vigencia</th><th class="py-2 font-medium">Publicada</th><th class="py-2 font-medium">Por</th><th class="py-2 font-medium">Precios</th><th class="py-2 font-medium">Detalle</th></tr></thead>
      <tbody>
      <?php foreach ($published as $p): $items = rows('SELECT h.price, h.availability, v.presentation, pr.name FROM price_history h JOIN product_variants v ON v.id = h.variant_id JOIN products pr ON pr.id = v.product_id WHERE h.publication_id = ? ORDER BY v.presentation, pr.sort_order', [$p['id']]); ?>
        <tr class="border-t border-line align-top">
          <td class="py-2.5"><?= e(fecha_hora($p['effective_from'])) ?><?= strtotime($p['effective_from'] . ' UTC') > time() ? ' ' . pill('Programada', 'yolk') : '' ?></td>
          <td class="py-2.5 text-ink-2"><?= e(fecha_hora($p['published_at'])) ?></td>
          <td class="py-2.5"><?= e($p['publisher'] ?? '—') ?></td>
          <td class="tabular py-2.5"><?= (int) $p['priced'] ?></td>
          <td class="py-2.5"><details><summary class="cursor-pointer text-leaf-2">Ver</summary>
            <ul class="mt-2 space-y-0.5 text-xs"><?php foreach ($items as $it): ?><li><?= e($it['name']) ?> · <?= $it['presentation'] ?>: <strong><?= $it['price'] === null ? 'por confirmar' : e(bs((float) $it['price'])) ?></strong> (<?= e(AVAIL_OPTS[$it['availability']]) ?>)</li><?php endforeach; ?></ul>
            <?php if ($p['note']): ?><p class="mt-1 text-xs text-ink-2">Nota: <?= e($p['note']) ?></p><?php endif; ?></details></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  <?php card_close(); ?>
<?php endif;
admin_foot();
