<?php
// Filtros de la lista de pedidos (los usa la lista y la exportación CSV).
declare(strict_types=1);

// Quién ve qué: el administrador todo; cada vendedor solo los pedidos que le llegaron
function order_scope(array $u): array
{
    return can('orders_all', $u['role']) ? ['1 = 1', []] : ['o.assigned_to = ?', [$u['id']]];
}
function order_visible(array $o, array $u): bool
{
    return can('orders_all', $u['role']) || (int) ($o['assigned_to'] ?? 0) === $u['id'];
}

function order_filters(array $g, array $u): array
{
    [$scope, $params] = order_scope($u);
    $where = [$scope]; $keep = [];
    $estado = (string) ($g['estado'] ?? '');
    if ($estado === 'abiertos') { $where[] = "o.status IN ('nuevo','confirmado')"; $keep['estado'] = $estado; }
    elseif ($estado === 'todos') { $keep['estado'] = $estado; }
    elseif (isset(ORDER_STATUS[$estado])) { $where[] = 'o.status = ?'; $params[] = $estado; $keep['estado'] = $estado; }
    $q = trim((string) ($g['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(o.code LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)';
        $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like, $like); $keep['q'] = $q;
    }
    $zona = (int) ($g['zona'] ?? 0);
    if ($zona) { $where[] = 'o.zone_id = ?'; $params[] = $zona; $keep['zona'] = $zona; }
    if ($from = local_date_to_utc((string) ($g['desde'] ?? ''))) { $where[] = 'o.created_at >= ?'; $params[] = $from; $keep['desde'] = $g['desde']; }
    if ($to = local_date_to_utc((string) ($g['hasta'] ?? ''), true)) { $where[] = 'o.created_at <= ?'; $params[] = $to; $keep['hasta'] = $g['hasta']; }
    $asig = (string) ($g['asignado'] ?? '');
    if ($asig === 'nadie') { $where[] = 'o.assigned_to IS NULL'; $keep['asignado'] = $asig; }
    elseif (ctype_digit($asig)) { $where[] = 'o.assigned_to = ?'; $params[] = (int) $asig; $keep['asignado'] = $asig; }
    return [implode(' AND ', $where), $params, $keep];
}
