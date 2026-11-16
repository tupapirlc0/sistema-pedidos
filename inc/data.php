<?php
// Datos de la página pública y precios vigentes.
declare(strict_types=1);

// Precio vigente por presentación: última publicación cuya vigencia ya comenzó.
// Los borradores y las publicaciones programadas a futuro no cuentan.
function current_prices(): array
{
    $sql = "SELECT ph.variant_id, ph.price, ph.availability, p.effective_from, p.published_at
            FROM price_history ph JOIN price_publications p ON p.id = ph.publication_id
            WHERE p.status = 'published' AND p.effective_from <= UTC_TIMESTAMP()
              AND NOT EXISTS (
                SELECT 1 FROM price_history ph2 JOIN price_publications p2 ON p2.id = ph2.publication_id
                WHERE ph2.variant_id = ph.variant_id AND p2.status = 'published' AND p2.effective_from <= UTC_TIMESTAMP()
                  AND (p2.effective_from > p.effective_from OR (p2.effective_from = p.effective_from AND p2.published_at > p.published_at)))";
    $out = [];
    foreach (rows($sql) as $r) {
        $out[(int) $r['variant_id']] = [
            'price' => $r['price'] === null ? null : (float) $r['price'],
            'availability' => $r['availability'], 'effective_from' => $r['effective_from'], 'published_at' => $r['published_at'],
        ];
    }
    return $out;
}

function content_sections(string $column = 'published'): array
{
    $col = $column === 'draft' ? 'draft' : 'published';
    $out = [];
    foreach (rows("SELECT `key`, $col AS data FROM content_sections") as $r) $out[$r['key']] = json_decode($r['data'], true) ?: [];
    return $out;
}

function public_contacts(): array
{
    return rows("SELECT c.id, c.name, c.phone, c.zone_id, z.name AS zone_name, c.role_label, c.is_primary
                 FROM business_contacts c LEFT JOIN zones z ON z.id = c.zone_id
                 WHERE c.is_active = 1 AND c.deleted_at IS NULL ORDER BY c.is_primary DESC, c.sort_order");
}

function public_zones(): array
{
    return rows('SELECT id, name, slug FROM zones WHERE is_active = 1 AND deleted_at IS NULL ORDER BY sort_order');
}

// Todo lo que necesita la página pública
function public_data(bool $draft = false): array
{
    $settings = settings();
    $prices = current_prices();
    $inventory = [];
    foreach (rows('SELECT variant_id, status FROM inventory_status') as $r) $inventory[(int) $r['variant_id']] = $r['status'];
    // Con stock registrado, la disponibilidad sale sola de los maples que hay (almacén + socios)
    $useStock = stock_in_use();
    $stock = $useStock ? stock_levels()['total'] : [];

    $products = [];
    foreach (rows("SELECT p.id, p.slug, p.name, p.grade, p.description, p.badge, p.image_path, v.id AS vid, v.presentation, v.units, v.label
                   FROM products p JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
                   WHERE p.status = 'active' AND p.deleted_at IS NULL ORDER BY p.sort_order, p.name, v.sort_order") as $r) {
        $pid = (int) $r['id'];
        $products[$pid] ??= ['id' => $pid, 'slug' => $r['slug'], 'name' => $r['name'], 'grade' => $r['grade'], 'description' => $r['description'],
            'badge' => $r['badge'], 'image_path' => $r['image_path'], 'variants' => []];
        $vid = (int) $r['vid'];
        $cp = $prices[$vid] ?? null;
        $inv = $inventory[$vid] ?? 'por_confirmar';
        if ($useStock) {
            $availability = stock_availability($stock[$pid] ?? 0, $r['presentation']);
        } else {
            // Sin una lista publicada vigente tampoco se afirma la disponibilidad
            $availability = $inv === 'agotado' ? 'agotado' : ($cp === null ? 'por_confirmar' : $inv);
        }
        $products[$pid]['variants'][] = ['id' => $vid, 'presentation' => $r['presentation'], 'units' => (int) $r['units'], 'label' => $r['label'],
            'price' => $cp['price'] ?? null, 'availability' => $availability];
    }

    $effective = null; $published = null;
    foreach ($prices as $p) {
        if ($effective === null || $p['effective_from'] > $effective) $effective = $p['effective_from'];
        if ($published === null || $p['published_at'] > $published) $published = $p['published_at'];
    }
    $next = val("SELECT MIN(effective_from) FROM price_publications WHERE status = 'published' AND effective_from > UTC_TIMESTAMP()");
    $staleSeconds = (int) ($settings['price_stale_after_hours'] ?? 36) * 3600;
    $status = $effective === null ? 'sin_publicar' : (time() - strtotime($effective . ' UTC') > $staleSeconds ? 'desactualizado' : 'vigente');
    if ($status === 'desactualizado' && !$useStock) {
        // Precios viejos: se muestran como referencia, no como disponibilidad actual
        foreach ($products as &$p) foreach ($p['variants'] as &$v) if ($v['availability'] !== 'agotado') $v['availability'] = 'por_confirmar';
        unset($p, $v);
    }
    // Cuánto se ahorra al llevar el paquete en vez de 10 maples sueltos
    foreach ($products as &$p) {
        $byPres = array_column($p['variants'], 'price', 'presentation');
        $p['package_saving'] = isset($byPres['maple'], $byPres['paquete']) ? round($byPres['maple'] * 10 - $byPres['paquete'], 2) : null;
    }
    unset($p);
    $lastMove = $useStock ? val('SELECT MAX(created_at) FROM stock_movements') : null;

    return [
        'settings' => $settings,
        'content' => content_sections($draft ? 'draft' : 'published'),
        'products' => array_values($products),
        'contacts' => public_contacts(),
        'zones' => public_zones(),
        'prices' => ['status' => $status, 'effective_from' => $effective, 'published_at' => $published, 'next_effective_from' => $next],
        'stock' => ['auto' => $useStock, 'updated_at' => $lastMove],
    ];
}

function price_stamp(array $prices): array
{
    if ($prices['status'] === 'sin_publicar' || !$prices['effective_from']) return ['Precios por confirmar', 'none'];
    $d = fecha($prices['effective_from'], false) . ', ' . hora($prices['effective_from']);
    return $prices['status'] === 'desactualizado' ? ["Precios del $d · por confirmar", 'warn'] : ["Precios del $d", 'ok'];
}
