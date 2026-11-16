<?php
// Arranque común: configuración, base de datos, seguridad y utilidades.
declare(strict_types=1);

const ACS_TZ = 'America/La_Paz';
const ROLES = ['admin', 'socio', 'ventas', 'operador'];

$__configFile = dirname(__DIR__) . '/config.php';
if (!is_file($__configFile)) {
    http_response_code(503);
    $inst = (str_ends_with(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/admin') || str_ends_with(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/api')) ? '../instalar.php' : 'instalar.php';
    exit('<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;padding:2rem">Falta configurar el sitio. Abre <a href="' . $inst . '">instalar.php</a>.</p>');
}
$GLOBALS['ACS_CONFIG'] = require $__configFile;

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['ACS_CONFIG'][$key] ?? $default;
}

error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

// ─── Base de datos ──────────────────────────────────────────────────────────
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', config('db_host'), config('db_name'));
        $pdo = new PDO($dsn, (string) config('db_user'), (string) config('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function rows(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $params = []): mixed { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }

function now_utc(): string { return gmdate('Y-m-d H:i:s'); }

// ─── Rutas ──────────────────────────────────────────────────────────────────
// La carpeta real se detecta sola (comparando con la raíz del dominio). Así la página funciona
// aunque site_url en config.php tenga otra carpeta; del config se usan el dominio y el https.
function base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $app = realpath(dirname(__DIR__));
    if ($root && $app && str_starts_with($app . '/', rtrim($root, '/') . '/')) {
        return $base = rtrim(str_replace('\\', '/', substr($app, strlen(rtrim($root, '/')))), '/');
    }
    return $base = rtrim((string) (parse_url((string) config('site_url'), PHP_URL_PATH) ?? ''), '/');
}
function site_url(): string
{
    $u = parse_url((string) config('site_url'));
    $host = $u['host'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($u['scheme'] ?? 'https') . '://' . $host . (isset($u['port']) ? ':' . $u['port'] : '') . base_path();
}
function url(string $path = ''): string { return base_path() . '/' . ltrim($path, '/'); }
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return url($path) . '?v=' . $v;
}
function upload_url(?string $path): ?string { return $path ? url($path) : null; }
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}
function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') || str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

// ─── Salida segura ──────────────────────────────────────────────────────────
function e(mixed $v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function json_attr(mixed $v): string
{
    return e(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
}
function json_script(mixed $v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function security_headers(bool $private = false): void
{
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    if ($private) {
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
    }
}

// ─── Configuración y contenido guardados en la base (JSON) ─────────────────
function settings(bool $fresh = false): array
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = [];
        foreach (rows('SELECT `key`, value FROM site_settings') as $r) $cache[$r['key']] = json_decode($r['value'], true);
    }
    return $cache;
}
function setting(string $key, mixed $default = null): mixed { return settings()[$key] ?? $default; }
function save_setting(string $key, mixed $value, ?int $by): void
{
    q('INSERT INTO site_settings (`key`, value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
        [$key, json_encode($value, JSON_UNESCAPED_UNICODE), $by]);
}

// ─── Formato ────────────────────────────────────────────────────────────────
function bs(?float $n): string
{
    if ($n === null) return '—';
    $dec = floor($n) == $n ? 0 : 2;
    return 'Bs ' . number_format($n, $dec, ',', '.');
}
function local_dt(?string $utc): ?DateTimeImmutable
{
    if (!$utc) return null;
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(ACS_TZ));
}
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sept', 'oct', 'nov', 'dic'];
const MESES_LARGOS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
function fecha(?string $utc, bool $year = true, bool $long = false): string
{
    $d = local_dt($utc);
    if (!$d) return '—';
    $m = ($long ? MESES_LARGOS : MESES)[(int) $d->format('n') - 1];
    return $d->format('j') . ' ' . $m . ($year ? ' ' . $d->format('Y') : '');
}
function hora(?string $utc): string { $d = local_dt($utc); return $d ? $d->format('H:i') : '—'; }
function fecha_hora(?string $utc): string { return $utc ? fecha($utc) . ', ' . hora($utc) : '—'; }

function normalize_phone(string $raw): string
{
    $d = preg_replace('/\D/', '', $raw) ?? '';
    if (preg_match('/^591\d{8}$/', $d)) $d = substr($d, 3);
    return $d;
}
function wa_number(string $phone): string { $d = preg_replace('/\D/', '', $phone) ?? ''; return strlen($d) === 8 ? '591' . $d : $d; }
function pretty_phone(string $phone): string { $d = preg_replace('/\D/', '', $phone) ?? ''; return strlen($d) === 8 ? substr($d, 0, 4) . ' ' . substr($d, 4) : $d; }
function wa_link(string $phone, ?string $text = null): string
{
    return 'https://wa.me/' . wa_number($phone) . ($text !== null ? '?text=' . rawurlencode($text) : '');
}

// Solo cuatro estados: fáciles de entender para quien atiende los pedidos
const ORDER_STATUS = [
    'nuevo' => ['Nuevo', 'yolk'],
    'confirmado' => ['Confirmado', 'leaf'],
    'entregado' => ['Entregado', 'forest'],
    'cancelado' => ['Cancelado', 'terracotta'],
];
// Lo que falta hacer con cada pedido (se muestra en la lista)
const ORDER_NEXT = ['nuevo' => ['Confirmar'], 'confirmado' => ['Falta entregar']];
// Nombres de los estados antiguos, solo para leer el historial
const ORDER_STATUS_OLD = ['pendiente_whatsapp' => 'Pendiente de WhatsApp', 'preparando' => 'Preparando', 'listo' => 'Listo'];
function order_status_name(string $s): string { return ORDER_STATUS[$s][0] ?? ORDER_STATUS_OLD[$s] ?? $s; }
const AVAILABILITY = [
    'disponible' => ['Disponible', 'leaf'],
    'poco_stock' => ['Poco stock', 'yolk'],
    'agotado' => ['Agotado', 'terracotta'],
    'por_confirmar' => ['Consultar disponibilidad', 'wood'],
];

// ─── Privacidad y límites ───────────────────────────────────────────────────
function client_ip(): string
{
    // En Hostinger (LiteSpeed) REMOTE_ADDR ya es la IP real del visitante
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function ip_hash(): string { return substr(hash('sha256', config('secret') . '|' . client_ip()), 0, 32); }

// Devuelve true si la solicitud está permitida
function rate_limit(string $key, int $max, int $windowSeconds): bool
{
    $key = substr($key, 0, 120);
    q('INSERT INTO rate_limits (rkey, window_start, hits) VALUES (?, UTC_TIMESTAMP(), 1)
       ON DUPLICATE KEY UPDATE
         hits = IF(window_start < UTC_TIMESTAMP() - INTERVAL ? SECOND, 1, hits + 1),
         window_start = IF(window_start < UTC_TIMESTAMP() - INTERVAL ? SECOND, UTC_TIMESTAMP(), window_start)',
        [$key, $windowSeconds, $windowSeconds]);
    if (random_int(1, 100) === 1) q('DELETE FROM rate_limits WHERE window_start < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    return (int) val('SELECT hits FROM rate_limits WHERE rkey = ?', [$key]) <= $max;
}

// La solicitud viene de este mismo sitio (protección CSRF para las API públicas)
function same_origin(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') return ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin';
    return same_host($origin);
}
// ¿La URL apunta a este mismo sitio (host y puerto)?
function same_host(string $u): bool
{
    $p = parse_url($u);
    $host = ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    return $host !== '' && strcasecmp($host, (string) ($_SERVER['HTTP_HOST'] ?? '')) === 0;
}

function audit(string $action, string $entity, mixed $entityId, mixed $before = null, mixed $after = null, ?int $actor = null): void
{
    $enc = fn($v) => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE);
    q('INSERT INTO audit_logs (actor_id, action, entity, entity_id, before_data, after_data) VALUES (?, ?, ?, ?, ?, ?)',
        [$actor ?? (current_user()['id'] ?? null), $action, $entity, $entityId === null ? null : (string) $entityId, $enc($before), $enc($after)]);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/auth.php';
require __DIR__ . '/stock.php';
require __DIR__ . '/migrate.php';

// Aplica las actualizaciones de la base al subir una versión nueva. Si la base todavía no existe
// (instalación a medias) no se interrumpe la página: lo resuelve el instalador.
try { ensure_schema(); } catch (Throwable $err) { error_log('Actualización de la base: ' . $err->getMessage()); }
