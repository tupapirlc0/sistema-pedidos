<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

function admins_left(int $exceptId): int
{
    return (int) val("SELECT COUNT(*) FROM profiles WHERE role_id = 'admin' AND is_active = 1 AND deleted_at IS NULL AND id <> ?", [$exceptId]);
}

// Valida usuario y celular para ingresar (al menos uno, o un correo ya guardado) y que no los use otra persona
function login_ids(string $rawUser, string $rawPhone, ?int $exceptId, ?string $email = null): array
{
    $username = normalize_username($rawUser) ?: null;
    $phone = normalize_phone($rawPhone) ?: null;
    if ($username !== null && !preg_match('/^[a-z0-9._]{3,40}$/', $username)) throw new UserError('El usuario debe tener entre 3 y 40 letras o números, sin espacios (ej. rocio).');
    if ($phone !== null && !preg_match('/^\d{8}$/', $phone)) throw new UserError('El celular debe tener 8 números (ej. 70000001).');
    if ($username === null && $phone === null && !$email && !post('email')) throw new UserError('Escribe al menos el usuario o el celular para ingresar.');
    $taken = fn($col, $v) => $v !== null && val("SELECT id FROM profiles WHERE $col = ? AND deleted_at IS NULL AND id <> ?", [$v, $exceptId ?? 0]);
    if ($taken('username', $username)) throw new UserError("El usuario «{$username}» ya lo usa otra persona.");
    if ($taken('phone', $phone)) throw new UserError('Ese celular ya lo usa otra persona.');
    return [$username, $phone];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(['admin'], function (array $u) {
        $action = post('action');
        $max = (int) setting('max_users', 3);
        if ($action === 'create') {
            if ((int) val('SELECT COUNT(*) FROM profiles WHERE deleted_at IS NULL') >= $max) throw new UserError("Se alcanzó el máximo de $max usuarios. Elimina uno antes de crear otro.");
            $email = mb_strtolower(post('email')); $name = post('full_name'); $role = post('role'); $pass = (string) ($_POST['password'] ?? '');
            $zone = (int) post('zone_id') ?: null;
            [$username, $phone] = login_ids(post('username'), post('phone'), null);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UserError('El correo no es válido (es opcional: puedes dejarlo vacío).');
            if ($email === '') $email = null;
            if (mb_strlen($name) < 2) throw new UserError('Escribe el nombre.');
            if (!in_array($role, ROLES, true)) throw new UserError('Elige un rol.');
            if ($err = password_rule_error($pass)) throw new UserError($err);
            $old = $email ? row('SELECT id FROM profiles WHERE email = ?', [$email]) : null;
            if ($old) {
                // Un correo eliminado antes se puede volver a usar
                if (val('SELECT deleted_at FROM profiles WHERE id = ?', [$old['id']]) === null) throw new UserError('Ya existe un usuario con ese correo.');
                q('UPDATE profiles SET full_name = ?, username = ?, phone = ?, role_id = ?, zone_id = ?, password_hash = ?, is_active = 1, deleted_at = NULL, session_version = session_version + 1 WHERE id = ?', [$name, $username, $phone, $role, $zone, hash_password($pass), $old['id']]);
                $id = (int) $old['id'];
            } else {
                q('INSERT INTO profiles (email, username, phone, full_name, role_id, zone_id, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)', [$email, $username, $phone, $name, $role, $zone, hash_password($pass)]);
                $id = (int) db()->lastInsertId();
            }
            audit('create', 'profile', $id, null, ['username' => $username, 'phone' => $phone, 'email' => $email, 'role' => $role], $u['id']);
            return "Usuario creado. $name ingresa con «" . ($username ?: $phone) . "». Comparte la contraseña por un medio seguro; podrá cambiarla en Mi cuenta.";
        }

        $id = (int) post('id');
        $p = row('SELECT * FROM profiles WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$p) throw new UserError('El usuario no existe.');
        $self = $id === $u['id'];
        if ($action === 'update') {
            $role = post('role'); $active = post('is_active') === '1' ? 1 : 0; $name = post('full_name'); $zone = (int) post('zone_id') ?: null;
            [$username, $phone] = login_ids(post('username'), post('phone'), $id, $p['email']);
            if (!in_array($role, ROLES, true)) throw new UserError('Rol no válido.');
            if (mb_strlen($name) < 2) throw new UserError('Escribe el nombre.');
            if ($self && (!$active || $role !== 'admin')) throw new UserError('No puedes quitarte el rol de administrador ni desactivarte a ti mismo.');
            if ($p['role_id'] === 'admin' && ($role !== 'admin' || !$active) && admins_left($id) === 0) throw new UserError('Debe quedar al menos un administrador activo.');
            $bump = ($role !== $p['role_id'] || $active !== (int) $p['is_active']) ? ', session_version = session_version + 1' : '';
            q("UPDATE profiles SET full_name = ?, username = ?, phone = ?, role_id = ?, zone_id = ?, is_active = ?$bump WHERE id = ?", [$name, $username, $phone, $role, $zone, $active, $id]);
            audit('update', 'profile', $id, ['role' => $p['role_id'], 'active' => (int) $p['is_active']], ['role' => $role, 'active' => $active], $u['id']);
            return 'Usuario actualizado.';
        }
        if ($action === 'reset') {
            $pass = (string) ($_POST['password'] ?? '');
            if ($err = password_rule_error($pass)) throw new UserError($err);
            q('UPDATE profiles SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [hash_password($pass), $id]);
            if ($self) $_SESSION['sv'] = (int) val('SELECT session_version FROM profiles WHERE id = ?', [$id]);
            audit('password_reset', 'profile', $id, null, null, $u['id']);
            return 'Contraseña restablecida. Sus sesiones abiertas se cerraron.';
        }
        if ($action === 'delete') {
            if ($self) throw new UserError('No puedes eliminar tu propio usuario.');
            if ($p['role_id'] === 'admin' && admins_left($id) === 0) throw new UserError('Debe quedar al menos un administrador activo.');
            q('UPDATE profiles SET is_active = 0, deleted_at = UTC_TIMESTAMP(), session_version = session_version + 1 WHERE id = ?', [$id]);
            q('UPDATE orders SET assigned_to = NULL WHERE assigned_to = ?', [$id]);
            audit('delete', 'profile', $id, ['email' => $p['email']], null, $u['id']);
            return 'Usuario eliminado.';
        }
        throw new UserError('Acción no válida.');
    }, url('admin/usuarios.php'));
}

$u = require_user(['admin']);
$users = rows('SELECT * FROM profiles WHERE deleted_at IS NULL ORDER BY created_at');
$max = (int) setting('max_users', 3);
$zoneOpts = ['' => 'Sin zona'] + array_column(public_zones(), 'name', 'id');
admin_head('Usuarios', $u, 'usuarios.php');
?>
<p class="mt-1 text-ink-2"><?= count($users) ?> de <?= $max ?> usuarios. <strong>Administrador</strong>: todo. <strong>Vendedor</strong>: atiende solo los pedidos de su zona y registra su stock y sus ventas. <strong>Ventas</strong>: pedidos, clientes, analítica y sus propias ventas. <strong>Operador</strong>: estados de pedidos e inventario.</p>
<?php foreach ($users as $p): $self = (int) $p['id'] === $u['id']; ?>
  <?php card_open(); ?>
    <div class="flex flex-wrap items-center gap-2">
      <h2 class="display text-xl font-semibold text-forest"><?= e($p['full_name']) ?></h2>
      <?= pill(ROLE_LABELS[$p['role_id']], $p['role_id'] === 'admin' ? 'forest' : 'leaf') ?><?= $p['is_active'] ? '' : pill('Desactivado', 'terracotta') ?><?= $self ? pill('Tú', 'yolk') : '' ?>
    </div>
    <p class="text-sm text-ink-2">Ingresa con: <strong><?= e(implode(' o ', array_filter([$p['username'], $p['phone'] ? pretty_phone($p['phone']) : null, $p['email']]))) ?></strong> · Último ingreso: <?= e($p['last_login_at'] ? fecha_hora($p['last_login_at']) : 'nunca') ?></p>
    <div class="mt-4 grid gap-5 lg:grid-cols-2">
      <form method="post" class="grid gap-3 sm:grid-cols-2 sm:items-end"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <div><?= label('n' . $p['id'], 'Nombre') ?><input id="n<?= $p['id'] ?>" name="full_name" value="<?= e($p['full_name']) ?>" maxlength="80" class="field"></div>
        <div><?= label('r' . $p['id'], 'Rol') ?><select id="r<?= $p['id'] ?>" name="role" class="field" <?= $self ? 'disabled' : '' ?>><?= select_options(ROLE_LABELS, $p['role_id']) ?></select><?= $self ? '<input type="hidden" name="role" value="admin">' : '' ?></div>
        <div><?= label('a' . $p['id'], 'Acceso') ?><select id="a<?= $p['id'] ?>" name="is_active" class="field" <?= $self ? 'disabled' : '' ?>><?= select_options(['1' => 'Activo', '0' => 'Desactivado'], $p['is_active']) ?></select><?= $self ? '<input type="hidden" name="is_active" value="1">' : '' ?></div>
        <div><?= label('un' . $p['id'], 'Usuario') ?><input id="un<?= $p['id'] ?>" name="username" value="<?= e($p['username']) ?>" maxlength="40" autocapitalize="none" class="field" placeholder="ej. rocio"></div>
        <div><?= label('ph' . $p['id'], 'Celular') ?><input id="ph<?= $p['id'] ?>" name="phone" value="<?= e($p['phone']) ?>" inputmode="tel" maxlength="12" class="field tabular" placeholder="70000001"></div>
        <div><?= label('z' . $p['id'], 'Zona') ?><select id="z<?= $p['id'] ?>" name="zone_id" class="field"><?= select_options($zoneOpts, $p['zone_id'] ?? '') ?></select></div>
        <div class="sm:col-span-2"><?= btn('Guardar', 'primary') ?></div>
      </form>
      <div class="space-y-3">
        <form method="post" class="flex flex-col gap-2 sm:flex-row sm:items-end"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <div class="flex-1"><?= label('pw' . $p['id'], 'Nueva contraseña') ?><input id="pw<?= $p['id'] ?>" name="password" type="password" minlength="10" required autocomplete="new-password" class="field"></div>
          <?= btn('Restablecer', 'ghost', 'data-confirm="¿Restablecer la contraseña? Se cerrarán sus sesiones."') ?></form>
        <?php if (!$self): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><?= btn('Eliminar usuario', 'danger', 'data-confirm="¿Eliminar a ' . e($p['full_name']) . '? Perderá el acceso."') ?></form><?php endif; ?>
      </div>
    </div>
  <?php card_close(); ?>
<?php endforeach; ?>

<?php if (count($users) < $max): ?>
  <?php card_open('Nuevo usuario'); ?>
    <form method="post" class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4 lg:items-end"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div><?= label('c-name', 'Nombre', true) ?><input id="c-name" name="full_name" required maxlength="80" class="field"></div>
      <div><?= label('c-user', 'Usuario', true) ?><input id="c-user" name="username" required maxlength="40" autocapitalize="none" class="field" placeholder="ej. rocio" autocomplete="off"></div>
      <div><?= label('c-phone', 'Celular (también sirve para ingresar)') ?><input id="c-phone" name="phone" inputmode="tel" maxlength="12" class="field tabular" placeholder="70000001"></div>
      <div><?= label('c-email', 'Correo (opcional)') ?><input id="c-email" name="email" type="email" class="field" autocomplete="off"></div>
      <div><?= label('c-role', 'Rol', true) ?><select id="c-role" name="role" class="field"><?= select_options(ROLE_LABELS, 'socio') ?></select></div>
      <div><?= label('c-zone', 'Zona') ?><select id="c-zone" name="zone_id" class="field"><?= select_options($zoneOpts, '') ?></select></div>
      <div><?= label('c-pass', 'Contraseña inicial', true) ?><input id="c-pass" name="password" type="password" required minlength="10" autocomplete="new-password" class="field"></div>
      <div class="md:col-span-2 lg:col-span-4"><?= btn('Crear usuario', 'primary') ?></div>
    </form>
  <?php card_close(); ?>
<?php else: ?>
  <p class="mt-6 rounded-2xl bg-cream-2 p-4 text-sm text-wood">Se alcanzó el máximo de <?= $max ?> usuarios.</p>
<?php endif; ?>
<?php admin_foot();
