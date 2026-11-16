<?php
// Métricas anónimas de la página pública (sin datos personales; la IP no se guarda).
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !same_origin()) json_response(['ok' => false], 403);
if (!rate_limit('ev:' . ip_hash(), 240, 3600)) json_response(['ok' => false], 429);
$in = json_decode((string) file_get_contents('php://input', false, null, 0, 4000), true) ?: [];
$type = (string) ($in['type'] ?? '');
if (!in_array($type, ['page_view', 'click_order', 'product_view', 'product_add', 'contact_click'], true)) json_response(['ok' => false], 400);
$variant = isset($in['variant_id']) ? (int) $in['variant_id'] : null;
if ($variant !== null && !val('SELECT 1 FROM product_variants WHERE id = ?', [$variant])) $variant = null;
$visitor = preg_match('/^[a-z0-9-]{8,64}$/i', (string) ($in['visitor_id'] ?? '')) ? $in['visitor_id'] : null;
$meta = isset($in['source']) ? json_encode(['source' => mb_substr((string) $in['source'], 0, 40)]) : null;
$ref = mb_substr((string) ($in['referrer'] ?? ''), 0, 300);
$ref = $ref !== '' && parse_url($ref, PHP_URL_HOST) !== parse_url(site_url(), PHP_URL_HOST) ? (parse_url($ref, PHP_URL_HOST) ?: null) : null;
q('INSERT INTO analytics_events (type, variant_id, visitor_id, path, referrer, meta) VALUES (?, ?, ?, ?, ?, ?)',
    [$type, $variant, $visitor, mb_substr((string) ($in['path'] ?? '/'), 0, 200), $ref, $meta]);
json_response(['ok' => true]);
