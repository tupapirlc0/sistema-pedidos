<?php
declare(strict_types=1);
if (!function_exists('e')) { require __DIR__ . '/inc/bootstrap.php'; http_response_code(404); }
if (!function_exists('logo')) require __DIR__ . '/inc/ui.php';
security_headers(true);
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Página no encontrada · Distribuidora Demo</title>
<meta name="robots" content="noindex"><link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>"></head>
<body class="grain grain-light grid min-h-screen place-items-center bg-forest px-6 text-center text-cream">
<main class="relative z-10">
  <div class="egg mx-auto grid h-28 w-24 place-items-center bg-yolk text-forest"><?= icon('egg', 44) ?></div>
  <h1 class="display mt-6 text-4xl font-semibold">Esta página no existe</h1>
  <p class="mt-3 text-cream/80">Puede que el enlace esté incompleto o que el pedido ya no esté disponible.</p>
  <a href="<?= e(url('')) ?>" class="mt-8 inline-block rounded-full bg-yolk px-7 py-3.5 font-bold text-forest">Ver precios de hoy</a>
</main></body></html>
