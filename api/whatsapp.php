<?php
// Marca que el cliente abrió WhatsApp para enviar su pedido (solo con el token público del pedido).
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !same_origin()) json_response(['error' => 'Solicitud no permitida.'], 403);
if (!rate_limit('wa:' . ip_hash(), 60, 3600)) json_response(['error' => 'Demasiadas solicitudes.'], 429);
$in = json_decode((string) file_get_contents('php://input', false, null, 0, 2000), true) ?: [];
$token = (string) ($in['token'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) json_response(['error' => 'Pedido no válido.'], 400);

$o = row('SELECT id, status, whatsapp_opened_at FROM orders WHERE public_token = ?', [$token]);
if (!$o) json_response(['error' => 'Pedido no encontrado.'], 404);
if ($o['whatsapp_opened_at'] === null) {
    $pdo = db();
    $pdo->beginTransaction();
    q('UPDATE orders SET whatsapp_opened_at = UTC_TIMESTAMP() WHERE id = ?', [$o['id']]);
    q("INSERT INTO order_events (order_id, type, from_value, to_value, note) VALUES (?, 'whatsapp', ?, ?, 'El cliente abrió WhatsApp para enviar el pedido')",
        [$o['id'], $o['status'], $o['status']]);
    q("INSERT INTO analytics_events (type) VALUES ('whatsapp_open')");
    $pdo->commit();
}
json_response(['ok' => true]);
