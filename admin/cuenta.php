<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post(['admin', 'socio', 'ventas', 'operador'], function (array $u) {
        $p = row('SELECT password_hash FROM profiles WHERE id = ?', [$u['id']]);
        if (post('action') === 'name') {
            $name = post('full_name');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 80) throw new UserError('Escribe tu nombre.');
            q('UPDATE profiles SET full_name = ? WHERE id = ?', [$name, $u['id']]);
            return 'Nombre actualizado.';
        }
        if (!password_verify((string) ($_POST['current'] ?? ''), $p['password_hash'])) throw new UserError('La contraseña actual no es correcta.');
        $new = (string) ($_POST['new'] ?? '');
        if ($err = password_rule_error($new)) throw new UserError($err);
        if ($new !== ($_POST['new2'] ?? '')) throw new UserError('Las contraseñas nuevas no coinciden.');
        q('UPDATE profiles SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [hash_password($new), $u['id']]);
        // Cierra las otras sesiones y mantiene esta
        $_SESSION['sv'] = (int) val('SELECT session_version FROM profiles WHERE id = ?', [$u['id']]);
        session_regenerate_id(true);
        audit('password_change', 'profile', $u['id'], null, null, $u['id']);
        return 'Contraseña cambiada. Se cerraron tus otras sesiones.';
    }, url('admin/cuenta.php'));
}
$u = require_user();
admin_head('Mi cuenta', $u, 'cuenta.php');
?>
<p class="mt-1 text-ink-2">Usuario: <?= e($u['login']) ?><?= $u['phone'] ? ' · Celular: ' . e(pretty_phone($u['phone'])) : '' ?> · <?= e(ROLE_LABELS[$u['role']]) ?></p>
<div class="grid gap-6 lg:grid-cols-2">
  <?php card_open('Nombre'); ?>
    <form method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="name">
      <div><?= label('full_name', 'Nombre', true) ?><input id="full_name" name="full_name" maxlength="80" required value="<?= e($u['name']) ?>" class="field" autocomplete="name"></div>
      <?= btn('Guardar', 'primary') ?></form>
  <?php card_close(); ?>
  <?php card_open('Cambiar contraseña'); ?>
    <form method="post" class="mt-4 space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <input type="text" name="username" value="<?= e($u['login']) ?>" autocomplete="username" hidden>
      <div><?= label('current', 'Contraseña actual', true) ?><input id="current" name="current" type="password" required autocomplete="current-password" class="field"></div>
      <div><?= label('new', 'Nueva contraseña (10+ caracteres, letras y números)', true) ?><input id="new" name="new" type="password" required minlength="10" autocomplete="new-password" class="field"></div>
      <div><?= label('new2', 'Repite la nueva contraseña', true) ?><input id="new2" name="new2" type="password" required minlength="10" autocomplete="new-password" class="field"></div>
      <?= btn('Cambiar contraseña', 'primary') ?></form>
  <?php card_close(); ?>
</div>
<?php admin_foot();
