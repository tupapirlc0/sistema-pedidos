<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/ui.php';

start_session();
if (current_user()) redirect('admin/index.php');
$error = null; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = post('email');
    $error = login($email, (string) ($_POST['password'] ?? ''));
    if (!$error) redirect('admin/index.php');
}
security_headers(true);
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingresar · Panel · Distribuidora Demo</title><meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png"><link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('assets/css/extra.css')) ?>"><script src="<?= e(asset('assets/js/extra.js')) ?>" defer></script></head>
<body class="grain grain-light grid min-h-screen place-items-center bg-forest px-4 py-10">
<main class="relative z-10 w-full max-w-sm">
  <div class="flex justify-center"><?= logo(setting('logo_path'), true) ?></div>
  <form method="post" class="mt-8 rounded-[28px] bg-cream p-6 shadow-[0_30px_80px_rgba(0,0,0,.35)]" novalidate>
    <?= csrf_field() ?>
    <h1 class="display text-2xl font-semibold text-forest">Panel administrativo</h1>
    <p class="mt-1 text-sm text-ink-2">Ingresa con tu usuario o tu celular, y tu contraseña.</p>
    <?php if ($error): ?><p role="alert" class="mt-4 rounded-xl bg-terracotta/10 p-3 text-sm text-terracotta-2"><?= e($error) ?></p><?php endif; ?>
    <label for="email" class="mt-5 mb-1 block text-sm font-medium">Usuario o celular</label>
    <input id="email" name="email" type="text" autocapitalize="none" autocorrect="off" spellcheck="false" placeholder="ej. rocio o 70000001" autocomplete="username" required value="<?= e($email) ?>" class="field" <?= $error ? 'aria-invalid="true"' : '' ?>>
    <label for="password" class="mt-4 mb-1 block text-sm font-medium">Contraseña</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required class="field">
    <button class="mt-6 w-full rounded-full bg-forest py-3 font-semibold text-cream hover:bg-forest-2">Ingresar</button>
    <p class="mt-4 text-center text-xs text-ink-2">¿Olvidaste tu contraseña? Pide al administrador que la restablezca.</p>
  </form>
  <p class="mt-6 text-center text-sm"><a href="<?= e(url('')) ?>" class="text-cream/80 underline underline-offset-4">Volver a la página</a></p>
</main></body></html>
