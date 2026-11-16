<?php
// Exporta los pedidos filtrados a CSV (separador ;, con BOM para que Excel respete los acentos).
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';
require dirname(__DIR__) . '/inc/orders_admin.php';

$u = require_user(['admin', 'ventas']);
[$where, $params] = order_filters($_GET, $u);
$rows = rows("SELECT o.id, o.code, o.created_at, o.status, o.customer_name, o.customer_phone, o.zone_name, o.address, o.reference, o.notes, o.total, o.has_unpriced_items, p.full_name AS assigned,
               (SELECT GROUP_CONCAT(CONCAT(i.quantity, ' ', i.presentation, ' ', i.product_name) SEPARATOR ' | ') FROM order_items i WHERE i.order_id = o.id) AS items
              FROM orders o LEFT JOIN profiles p ON p.id = o.assigned_to WHERE $where ORDER BY o.created_at DESC LIMIT 10000", $params);
audit('export', 'orders', null, null, ['rows' => count($rows), 'filters' => $_GET], $u['id']);

// Evita que una celda se interprete como fórmula al abrirla en Excel
$cell = function (mixed $v): string {
    $s = (string) $v;
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) $s = "'" . $s;
    return $s;
};
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="pedidos-' . local_dt(now_utc())->format('Y-m-d') . '.csv"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Código', 'Fecha', 'Estado', 'Cliente', 'Teléfono', 'Zona', 'Dirección', 'Referencia', 'Observaciones', 'Productos', 'Total (Bs)', 'Precios por confirmar', 'Asignado a'], ';', '"', '');
foreach ($rows as $r) {
    fputcsv($out, array_map($cell, [$r['code'], local_dt($r['created_at'])->format('Y-m-d H:i'), ORDER_STATUS[$r['status']][0], $r['customer_name'], $r['customer_phone'], $r['zone_name'],
        $r['address'], $r['reference'], $r['notes'], $r['items'], number_format((float) $r['total'], 2, ',', ''), $r['has_unpriced_items'] ? 'Sí' : 'No', $r['assigned'] ?? '']), ';', '"', '');
}
fclose($out);
