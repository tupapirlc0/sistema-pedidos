<?php
// Sesión del panel, contraseñas, CSRF, avisos y permisos por rol.
declare(strict_types=1);

const SESSION_HOURS = 12;

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('acs_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() ?: '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function hash_password(string $p): string
{
    return password_hash($p, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}

function password_rule_error(string $p): ?string
{
    if (mb_strlen($p) < 10) return 'La contraseña debe tener al menos 10 caracteres.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'La contraseña debe combinar letras y números.';
    if (mb_strlen($p) > 200) return 'La contraseña es demasiado larga.';
    return null;
}

// Usuarios: solo letras minúsculas, números, punto y guion bajo (ej. anacintia, ana.cintia)
function normalize_username(string $raw): string
{
    $u = mb_strtolower(trim($raw));
    $u = strtr($u, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', ' ' => '']);
    return preg_replace('/[^a-z0-9._]/', '', $u) ?? '';
}

// Se puede ingresar con el usuario, el celular o el correo
function find_login_profile(string $id): ?array
{
    $cols = 'id, password_hash, is_active, session_version';
    if (str_contains($id, '@')) return row("SELECT $cols FROM profiles WHERE email = ? AND deleted_at IS NULL", [mb_strtolower($id)]);
    $phone = normalize_phone($id);
    if (preg_match('/^\d{7,15}$/', $phone)) return row("SELECT $cols FROM profiles WHERE phone = ? AND deleted_at IS NULL", [$phone]);
    return row("SELECT $cols FROM profiles WHERE username = ? AND deleted_at IS NULL", [normalize_username($id)]);
}

function login(string $identifier, string $password): ?string
{
    $key = mb_strtolower(trim($identifier));
    if (!rate_limit('login-ip:' . ip_hash(), 20, 900) || !rate_limit('login-email:' . $key, 6, 900)) {
        return 'Demasiados intentos. Espera 15 minutos e intenta de nuevo.';
    }
    $u = $key === '' ? null : find_login_profile($key);
    // Si el correo no existe se verifica igual contra un hash de relleno, para no revelar qué correos existen
    $hash = $u['password_hash'] ?? '$2y$10$afbJUWcpQzAsgjM36gseIuGh.K2YKD0Vxl6wU5RH0J2E/CgEIVptS';
    $ok = password_verify($password, $hash);
    if (!$u || !$ok || !(int) $u['is_active']) return 'Usuario o contraseña incorrectos.';

    if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        q('UPDATE profiles SET password_hash = ? WHERE id = ?', [hash_password($password), $u['id']]);
    }
    start_session();
    session_regenerate_id(true);
    $_SESSION = ['uid' => (int) $u['id'], 'sv' => (int) $u['session_version'], 'seen' => time()];
    q('UPDATE profiles SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$u['id']]);
    return null;
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

// Usuario de la sesión actual, validado contra la base en cada solicitud
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!isset($_COOKIE['acs_sess'])) return null;
        start_session();
    }
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) return null;
    if (time() - (int) ($_SESSION['seen'] ?? 0) > SESSION_HOURS * 3600) { $_SESSION = []; return null; }
    $u = row('SELECT id, email, username, phone, full_name, role_id, session_version FROM profiles WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [$uid]);
    // Si cambió la contraseña o se desactivó el usuario, la sesión deja de valer
    if (!$u || (int) $u['session_version'] !== (int) ($_SESSION['sv'] ?? 0)) { $_SESSION = []; return null; }
    $_SESSION['seen'] = time();
    return $user = ['id' => (int) $u['id'], 'email' => $u['email'], 'username' => $u['username'], 'phone' => $u['phone'], 'name' => $u['full_name'], 'role' => $u['role_id'],
        'login' => $u['username'] ?: ($u['phone'] ?: (string) $u['email'])];
}

// Exige sesión y, si se indica, uno de los roles. Va al inicio de cada página y acción del panel.
function require_user(?array $roles = null): array
{
    start_session();
    $u = current_user();
    if (!$u) redirect('admin/login.php');
    if ($roles && !in_array($u['role'], $roles, true)) redirect('admin/index.php?sinpermiso=1');
    return $u;
}

// Permisos por rol: un único lugar para todo el panel
function can(string $perm, ?string $role = null): bool
{
    $role ??= current_user()['role'] ?? '';
    $map = [
        'prices' => ['admin'], 'products' => ['admin'], 'content' => ['admin'], 'users' => ['admin'], 'settings' => ['admin'],
        'inventory' => ['admin', 'operador'],
        'orders' => ['admin', 'socio', 'ventas', 'operador'], 'order_status' => ['admin', 'socio', 'ventas', 'operador'], 'order_notes' => ['admin', 'socio', 'ventas', 'operador'],
        'orders_all' => ['admin', 'ventas', 'operador'], // el vendedor (socio) ve solo los pedidos de su zona
        'order_assign' => ['admin', 'ventas'], 'export' => ['admin', 'ventas'],
        'customers' => ['admin', 'ventas'], 'analytics' => ['admin', 'ventas'], 'audit' => ['admin'],
        // Stock y ventas: todos registran lo suyo; ajustar stock, vender por otro y recibir rendiciones es del administrador
        'stock' => ['admin', 'socio', 'ventas', 'operador'], 'sales' => ['admin', 'socio', 'ventas', 'operador'],
        'stock_adjust' => ['admin'], 'sales_others' => ['admin'], 'settle' => ['admin'],
    ];
    return in_array($role, $map[$perm] ?? [], true);
}

// ─── CSRF ───────────────────────────────────────────────────────────────────
function csrf_token(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void
{
    start_session();
    $ok = isset($_SESSION['csrf'], $_POST['_csrf']) && is_string($_POST['_csrf']) && hash_equals($_SESSION['csrf'], $_POST['_csrf']);
    if (!$ok || !same_origin()) {
        http_response_code(400);
        exit('La sesión del formulario venció. Vuelve atrás, recarga la página e intenta de nuevo.');
    }
}

// ─── Avisos tras guardar (patrón POST → redirección → GET) ─────────────────
function flash(string $kind, string $msg): void { start_session(); $_SESSION['flash'][] = [$kind, $msg]; }
function take_flashes(): array { start_session(); $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// Procesa un POST del panel: verifica CSRF y rol, ejecuta en una transacción y redirige con un aviso
function handle_post(array $roles, callable $fn, ?string $back = null): never
{
    $user = require_user($roles);
    csrf_verify();
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $back ??= $ref !== '' && same_host($ref) ? $ref : url('admin/index.php');
    try {
        db()->beginTransaction();
        $msg = $fn($user);
        db()->commit();
        $kind = 'ok';
        // La acción puede devolver [mensaje, url] o [mensaje, url, 'error'] (se guardó, pero con un aviso)
        if (is_array($msg)) { $kind = $msg[2] ?? 'ok'; [$msg, $back] = $msg; }
        flash($kind, $msg ?: 'Cambios guardados.');
    } catch (UserError $err) {
        if (db()->inTransaction()) db()->rollBack();
        flash('error', $err->getMessage());
    } catch (Throwable $err) {
        if (db()->inTransaction()) db()->rollBack();
        error_log('Panel: ' . $err->getMessage());
        flash('error', str_contains($err->getMessage(), 'Duplicate') ? 'Ya existe un registro con esos datos.' : 'Ocurrió un error al guardar. Intenta de nuevo.');
    }
    redirect($back);
}

final class UserError extends RuntimeException {}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}
