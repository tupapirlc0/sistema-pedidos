<?php
// Creación de pedidos públicos y mensaje de WhatsApp.
declare(strict_types=1);

require_once __DIR__ . '/data.php';

final class OrderError extends RuntimeException
{
    public function __construct(string $message, public int $status = 422, public array $fields = []) { parent::__construct($message); }
}

// Valida todo en el servidor y toma los precios vigentes de la base: el navegador solo envía productos y cantidades.
function create_public_order(array $in, bool $dryRun = false): array
{
    $name = trim((string) ($in['name'] ?? ''));
    $phone = normalize_phone((string) ($in['phone'] ?? ''));
    $address = trim((string) ($in['address'] ?? ''));
    $reference = mb_substr(trim((string) ($in['reference'] ?? '')), 0, 300);
    $notes = mb_substr(trim((string) ($in['notes'] ?? '')), 0, 500);
    $zoneId = (int) ($in['zone_id'] ?? 0);
    $items = $in['items'] ?? null;

    $fields = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $fields['name'] = 'Escribe tu nombre.';
    if (!preg_match('/^\d{7,15}$/', $phone)) $fields['phone'] = 'Escribe un teléfono válido, por ejemplo 71234567.';
    if (mb_strlen($address) < 3 || mb_strlen($address) > 300) $fields['address'] = 'Indica una dirección o punto de entrega.';
    if (!is_array($items) || count($items) < 1 || count($items) > 20) $fields['items'] = 'Agrega al menos un producto.';
    if ($fields) throw new OrderError('Revisa los datos del pedido.', 422, $fields);
    if (!empty($in['website'])) throw new OrderError('Solicitud rechazada.', 400);

    $zoneRaw = $in['zone_id'] ?? '';
    if ($zoneRaw === '' || $zoneRaw === null) throw new OrderError('Elige tu zona.', 422, ['zone_id' => 'Elige tu zona para saber quién te atiende.']);
    $zone = null;
    if ($zoneId) {
        $zone = row('SELECT id, name FROM zones WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [$zoneId]);
        if (!$zone) throw new OrderError('La zona elegida no es válida.', 422, ['zone_id' => 'Zona no válida.']);
    }

    $prices = current_prices();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Cliente: se crea o se actualiza por teléfono
        $cust = row('SELECT id FROM customers WHERE phone = ? FOR UPDATE', [$phone]);
        if ($cust) {
            // Solo se actualiza lo que el cliente escribió: la zona y la referencia vacías no borran lo anterior
            $set = 'name = ?, address = ?, deleted_at = NULL'; $args = [$name, $address];
            if ($zone) { $set .= ', zone_id = ?'; $args[] = $zone['id']; }
            if ($reference !== '') { $set .= ', reference = ?'; $args[] = $reference; }
            $args[] = $cust['id'];
            q("UPDATE customers SET $set WHERE id = ?", $args);
            $customerId = (int) $cust['id'];
        } else {
            q('INSERT INTO customers (name, phone, zone_id, address, reference) VALUES (?, ?, ?, ?, ?)', [$name, $phone, $zone['id'] ?? null, $address, $reference]);
            $customerId = (int) $pdo->lastInsertId();
        }

        // Contacto que recibe el pedido: el principal, salvo que se active la asignación por zona
        $contact = null;
        if (setting('zone_routing_enabled') && $zone) {
            $contact = row('SELECT id, name, phone FROM business_contacts WHERE zone_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY is_primary DESC, sort_order LIMIT 1', [$zone['id']]);
        }
        $contact ??= row('SELECT id, name, phone FROM business_contacts WHERE is_primary = 1 AND is_active = 1 AND deleted_at IS NULL LIMIT 1');

        // El pedido le llega directo al vendedor de la zona; sin zona, al de la zona del contacto principal
        $seller = setting('zone_routing_enabled') ? zone_seller($zone['id'] ?? null) : null;
        if (!$seller && $contact) $seller = zone_seller((int) val('SELECT zone_id FROM business_contacts WHERE id = ?', [$contact['id']]));

        $token = bin2hex(random_bytes(16));
        q('INSERT INTO orders (code, public_token, customer_id, customer_name, customer_phone, zone_id, zone_name, address, reference, notes, contact_id, assigned_to)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['t' . substr($token, 0, 15), $token, $customerId, $name, $phone, $zone['id'] ?? null, $zone['name'] ?? '', $address, $reference, $notes, $contact['id'] ?? null, $seller]);
        $orderId = (int) $pdo->lastInsertId();
        $code = 'ACS-' . local_dt(now_utc())->format('ymd') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
        q('UPDATE orders SET code = ? WHERE id = ?', [$code, $orderId]);

        $subtotal = 0.0; $unpriced = false; $outItems = [];
        foreach ($items as $it) {
            $vid = (int) ($it['variant_id'] ?? 0);
            $qty = (int) ($it['quantity'] ?? 0);
            if ($qty < 1 || $qty > 500) throw new OrderError('Las cantidades deben estar entre 1 y 500.');
            $v = row("SELECT v.id, v.product_id, v.presentation, v.units, v.label, p.name AS product_name, COALESCE(i.status, 'por_confirmar') AS status
                      FROM product_variants v JOIN products p ON p.id = v.product_id LEFT JOIN inventory_status i ON i.variant_id = v.id
                      WHERE v.id = ? AND v.is_active = 1 AND p.status = 'active' AND p.deleted_at IS NULL", [$vid]);
            if (!$v) throw new OrderError('Uno de los productos ya no está disponible. Actualiza la página.');
            // Con stock registrado, lo agotado sale de los maples reales
            if (stock_in_use()) $v['status'] = stock_availability(stock_levels()['total'][(int) $v['product_id']] ?? 0, $v['presentation']);
            if ($v['status'] === 'agotado') throw new OrderError("Este producto se agotó: {$v['product_name']} · {$v['label']}. Quítalo del pedido para continuar.", 409);
            $price = $prices[$vid]['price'] ?? null;
            $line = $price === null ? null : round($price * $qty, 2);
            q('INSERT INTO order_items (order_id, variant_id, product_name, presentation, units, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$orderId, $vid, $v['product_name'], $v['presentation'], $v['units'], $price, $qty, $line]);
            if ($line === null) $unpriced = true; else $subtotal += $line;
            $outItems[] = ['product' => $v['product_name'], 'presentation' => $v['presentation'], 'quantity' => $qty, 'unit_price' => $price, 'line_total' => $line];
        }
        q('UPDATE orders SET subtotal = ?, total = ?, has_unpriced_items = ? WHERE id = ?', [$subtotal, $subtotal, $unpriced ? 1 : 0, $orderId]);
        q("INSERT INTO order_events (order_id, type, to_value) VALUES (?, 'created', 'nuevo')", [$orderId]);
        q("INSERT INTO analytics_events (type, meta) VALUES ('order_created', ?)", [json_encode(['items' => count($outItems)])]);
        if ($dryRun) { $pdo->rollBack(); return ['code' => $code, 'contact' => $contact]; } // prueba: no guarda nada
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $order = [
        'id' => $orderId, 'code' => $code, 'token' => $token, 'total' => $subtotal, 'has_unpriced_items' => $unpriced, 'items' => $outItems,
        'customer' => ['name' => $name, 'phone' => $phone, 'zone' => $zone['name'] ?? '', 'address' => $address, 'reference' => $reference, 'notes' => $notes],
        'contact' => $contact ? ['name' => $contact['name'], 'phone' => $contact['phone']] : null,
    ];
    $order['message'] = order_message($order);
    $order['whatsapp_url'] = $contact ? wa_link($contact['phone'], $order['message']) : null;
    return $order;
}

// Mensaje ordenado que el cliente envía a la empresa
function order_message(array $o): string
{
    $c = $o['customer'];
    $lines = ['Hola' . (!empty($o['contact']['name']) ? ' ' . $o['contact']['name'] : '') . ', quiero hacer este pedido a Distribuidora Demo 🥚', '', "*Pedido {$o['code']}*"];
    foreach ($o['items'] as $i) {
        $pres = $i['presentation'] === 'maple' ? ($i['quantity'] === 1 ? 'maple' : 'maples') : ($i['quantity'] === 1 ? 'paquete' : 'paquetes');
        $lines[] = "• {$i['quantity']} $pres de {$i['product']}" . ($i['unit_price'] !== null ? ' × ' . bs($i['unit_price']) . ' = ' . bs($i['line_total']) : ' (precio por confirmar)');
    }
    $lines[] = '';
    $lines[] = $o['has_unpriced_items']
        ? ($o['total'] > 0 ? '*Total parcial:* ' . bs($o['total']) . ' + productos con precio por confirmar' : '*Total:* por confirmar')
        : '*Total:* ' . bs($o['total']);
    $lines[] = '';
    $lines[] = "*Nombre:* {$c['name']}";
    $lines[] = "*Teléfono:* {$c['phone']}";
    if ($c['zone'] !== '') $lines[] = "*Zona:* {$c['zone']}";
    if ($c['address'] !== '') $lines[] = "*Entrega:* {$c['address']}";
    if ($c['reference'] !== '') $lines[] = "*Referencia:* {$c['reference']}";
    if ($c['notes'] !== '') $lines[] = "*Observaciones:* {$c['notes']}";
    $lines[] = '';
    $lines[] = '¿Me confirman disponibilidad, precio final y entrega? Gracias.';
    return implode("\n", $lines);
}
