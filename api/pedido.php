<?php
// Crea un pedido desde la página pública. Recibe JSON con datos del cliente, productos y cantidades.
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/orders.php';

header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Método no permitido.'], 405);
if (!same_origin()) json_response(['error' => 'Solicitud no permitida.'], 403);
if (!rate_limit('order:' . ip_hash(), 8, 3600)) json_response(['error' => 'Hiciste muchos pedidos seguidos. Escríbenos directamente por WhatsApp.'], 429);

$raw = file_get_contents('php://input', false, null, 0, 20000);
$in = json_decode((string) $raw, true);
if (!is_array($in)) json_response(['error' => 'Datos no válidos.'], 400);

try {
    $o = create_public_order($in);
    json_response(['ok' => true, 'order' => [
        'id' => $o['id'], 'code' => $o['code'], 'token' => $o['token'], 'total' => $o['total'], 'has_unpriced_items' => $o['has_unpriced_items'],
        'items' => $o['items'], 'contact' => $o['contact'], 'whatsapp_url' => $o['whatsapp_url'],
    ]], 201);
} catch (OrderError $e) {
    json_response(['error' => $e->getMessage(), 'fields' => (object) $e->fields], $e->status);
} catch (Throwable $e) {
    error_log('pedido: ' . $e->getMessage());
    json_response(['error' => 'No pudimos guardar tu pedido. Intenta de nuevo o escríbenos por WhatsApp.'], 500);
}
