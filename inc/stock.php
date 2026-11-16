<?php
// Stock en maples, ventas de los socios y rendición de cuentas.
// El stock no se guarda como un número: es la suma de movimientos (ingresos, retiros, ventas, ajustes),
// así cada cambio queda registrado con quién y cuándo.
declare(strict_types=1);

const MOVE_TYPES = [
    'ingreso' => 'Ingreso de la granja',
    'retiro' => 'Retiro para vender',
    'devolucion' => 'Devolución al almacén',
    'venta' => 'Venta',
    'ajuste' => 'Ajuste',
];
const MAPLES_PER = ['maple' => 1, 'paquete' => 10];

// Categorías activas (Jumbo, Primera…) en orden
function stock_products(): array
{
    return rows("SELECT id, name, grade FROM products WHERE status = 'active' AND deleted_at IS NULL ORDER BY sort_order, name");
}

// Personas que venden: socios, ventas y administradores activos
function sellers(): array
{
    return rows("SELECT p.id, p.full_name AS name, p.role_id, z.name AS zone_name FROM profiles p LEFT JOIN zones z ON z.id = p.zone_id
                 WHERE p.is_active = 1 AND p.deleted_at IS NULL ORDER BY FIELD(p.role_id, 'socio', 'ventas', 'admin', 'operador'), p.full_name");
}

// Maples por categoría y por quién los tiene: ['central' => [pid => n], 'holders' => [uid => [pid => n]], 'total' => [pid => n]]
function stock_levels(): array
{
    $out = ['central' => [], 'holders' => [], 'total' => []];
    foreach (rows('SELECT product_id, holder_id, SUM(maples) AS n FROM stock_movements GROUP BY product_id, holder_id') as $r) {
        $pid = (int) $r['product_id']; $n = (int) $r['n'];
        if ($r['holder_id'] === null) $out['central'][$pid] = $n;
        else $out['holders'][(int) $r['holder_id']][$pid] = $n;
        $out['total'][$pid] = ($out['total'][$pid] ?? 0) + $n;
    }
    return $out;
}

function stock_in_use(): bool
{
    static $used = null;
    return $used ??= (bool) setting('stock_auto', false) && (bool) val('SELECT 1 FROM stock_movements LIMIT 1');
}

// Disponibilidad para la página según los maples que hay (en el almacén y en manos de los socios)
function stock_availability(int $maples, string $presentation): string
{
    $need = MAPLES_PER[$presentation] ?? 1;
    if ($maples < $need) return 'agotado';
    $low = (int) setting('stock_low_maples', 30);
    return $maples < $low + ($need - 1) ? 'poco_stock' : 'disponible';
}

function new_batch(): string { return bin2hex(random_bytes(8)); }

function add_move(int $productId, ?int $holderId, int $maples, string $type, int $by, string $note = '', ?string $batch = null, ?int $saleId = null): void
{
    q('INSERT INTO stock_movements (product_id, holder_id, maples, type, batch, sale_id, note, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$productId, $holderId, $maples, $type, $batch, $saleId, mb_substr($note, 0, 200), $by]);
}

// Precio vigente de una categoría y presentación (o null si no hay lista publicada)
function current_price_for(int $productId, string $presentation): ?float
{
    $vid = val('SELECT id FROM product_variants WHERE product_id = ? AND presentation = ?', [$productId, $presentation]);
    if (!$vid) return null;
    return current_prices()[(int) $vid]['price'] ?? null;
}

// Resumen de dinero por vendedor en un rango (UTC): vendido, cobrado, fiado, rendido y saldo por rendir (histórico)
function seller_balances(?string $fromUtc = null): array
{
    $out = [];
    foreach (sellers() as $s) $out[(int) $s['id']] = ['name' => $s['name'], 'zone' => $s['zone_name'], 'role' => $s['role_id'],
        'sold' => 0.0, 'maples' => 0, 'paid' => 0.0, 'credit' => 0.0, 'settled' => 0.0, 'balance' => 0.0];
    $range = $fromUtc ? ' AND created_at >= ?' : '';
    $args = $fromUtc ? [$fromUtc] : [];
    foreach (rows("SELECT seller_id, SUM(total) AS sold, SUM(maples) AS maples, SUM(CASE WHEN paid = 1 THEN total ELSE 0 END) AS paid
                   FROM partner_sales WHERE deleted_at IS NULL$range GROUP BY seller_id", $args) as $r) {
        $id = (int) $r['seller_id']; if (!isset($out[$id])) continue;
        $out[$id]['sold'] = (float) $r['sold']; $out[$id]['maples'] = (int) $r['maples'];
        $out[$id]['paid'] = (float) $r['paid']; $out[$id]['credit'] = (float) $r['sold'] - (float) $r['paid'];
    }
    foreach (rows("SELECT seller_id, SUM(amount) AS s FROM settlements WHERE deleted_at IS NULL$range GROUP BY seller_id", $args) as $r) {
        if (isset($out[(int) $r['seller_id']])) $out[(int) $r['seller_id']]['settled'] = (float) $r['s'];
    }
    // El saldo por rendir siempre es histórico: todo lo cobrado menos todo lo rendido
    $paidAll = []; foreach (rows('SELECT seller_id, SUM(total) AS s FROM partner_sales WHERE deleted_at IS NULL AND paid = 1 GROUP BY seller_id') as $r) $paidAll[(int) $r['seller_id']] = (float) $r['s'];
    $setAll = []; foreach (rows('SELECT seller_id, SUM(amount) AS s FROM settlements WHERE deleted_at IS NULL GROUP BY seller_id') as $r) $setAll[(int) $r['seller_id']] = (float) $r['s'];
    foreach ($out as $id => &$b) $b['balance'] = round(($paidAll[$id] ?? 0) - ($setAll[$id] ?? 0), 2);
    unset($b);
    return $out;
}

// Quién atiende una zona: primero el vendedor de esa zona, luego cualquier usuario con esa zona
function zone_seller(?int $zoneId): ?int
{
    if (!$zoneId) return null;
    $id = val("SELECT id FROM profiles WHERE zone_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY FIELD(role_id, 'socio', 'ventas', 'admin', 'operador'), id LIMIT 1", [$zoneId]);
    return $id ? (int) $id : null;
}

// Pedido entregado: cada producto pasa a las ventas del vendedor y se descuenta del stock
// (primero lo que él tiene en mano y el resto del almacén). Devuelve lo que no alcanzó.
function order_to_sales(array $o, bool $paid, int $by): array
{
    $seller = (int) ($o['assigned_to'] ?: $by);
    $useStock = stock_in_use();
    $levels = $useStock ? stock_levels() : [];
    $short = [];
    foreach (rows('SELECT i.*, v.product_id FROM order_items i JOIN product_variants v ON v.id = i.variant_id WHERE i.order_id = ?', [$o['id']]) as $i) {
        $pid = (int) $i['product_id'];
        $maples = (int) $i['quantity'] * (MAPLES_PER[$i['presentation']] ?? 1);
        $hand = $useStock ? min($maples, max(0, $levels['holders'][$seller][$pid] ?? 0)) : 0;
        $central = $useStock ? min($maples - $hand, max(0, $levels['central'][$pid] ?? 0)) : 0;
        q('INSERT INTO partner_sales (seller_id, product_id, presentation, quantity, maples, unit_price, total, paid, from_central, customer_name, note, created_by, order_id)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$seller, $pid, $i['presentation'], $i['quantity'], $maples, $i['unit_price'], $i['line_total'], $paid ? 1 : 0, $hand ? 0 : 1, mb_substr($o['customer_name'], 0, 80), 'Pedido ' . $o['code'], $by, $o['id']]);
        $sid = (int) db()->lastInsertId();
        if ($hand) { add_move($pid, $seller, -$hand, 'venta', $by, 'Pedido ' . $o['code'], null, $sid); $levels['holders'][$seller][$pid] -= $hand; }
        if ($central) { add_move($pid, null, -$central, 'venta', $by, 'Pedido ' . $o['code'], null, $sid); $levels['central'][$pid] -= $central; }
        if ($useStock && $hand + $central < $maples) $short[] = ($maples - $hand - $central) . ' maples de ' . $i['product_name'];
    }
    return $short;
}

// Se deshizo una entrega: se anulan sus ventas y los maples vuelven a donde salieron
function order_void_sales(int $orderId, int $by): void
{
    foreach (rows('SELECT id FROM partner_sales WHERE order_id = ? AND deleted_at IS NULL', [$orderId]) as $s) {
        $moves = rows("SELECT product_id, holder_id, SUM(maples) AS n FROM stock_movements WHERE sale_id = ? AND type = 'venta' GROUP BY product_id, holder_id", [$s['id']]);
        foreach ($moves as $m) add_move((int) $m['product_id'], $m['holder_id'] === null ? null : (int) $m['holder_id'], -(int) $m['n'], 'ajuste', $by, 'Entrega deshecha', null, (int) $s['id']);
        q('UPDATE partner_sales SET deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$s['id']]);
    }
}

function today_start_utc(): string
{
    return (string) local_date_to_utc((new DateTimeImmutable('today', new DateTimeZone(ACS_TZ)))->format('Y-m-d'));
}
function days_ago_utc(int $days): string { return gmdate('Y-m-d H:i:s', strtotime(today_start_utc() . ' UTC') - $days * 86400); }

// Ingresos (Bs) por día local de La Paz, últimos $days días
function sales_by_day(int $days): array
{
    $by = [];
    foreach (rows("SELECT DATE(CONVERT_TZ(created_at, '+00:00', '-04:00')) AS d, SUM(total) AS t FROM partner_sales
                   WHERE deleted_at IS NULL AND created_at >= ? GROUP BY d", [days_ago_utc($days - 1)]) as $r) $by[$r['d']] = (int) round((float) $r['t']);
    return daily_series($by, $days);
}

// Medidor visual de stock: una barra con el máximo de referencia
function stock_meter(int $maples, int $max, string $presentation = 'maple'): string
{
    $state = stock_availability($maples, $presentation);
    $pct = $max > 0 ? max(0, min(100, round($maples / $max * 100))) : 0;
    return '<span class="meter meter-' . $state . '" role="img" aria-label="' . $maples . ' maples"><span style="--w:' . $pct . '%"></span></span>';
}
