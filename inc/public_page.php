<?php
// Página pública completa. La usa index.php (contenido publicado) y la vista previa del panel (borrador).
declare(strict_types=1);

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/ui.php';

const AVAIL_CLASSES = [
    'disponible' => ['Disponible', 'bg-leaf/12 text-leaf-2', 'bg-leaf'],
    'poco_stock' => ['Poco stock', 'bg-yolk-soft text-wood', 'bg-yolk'],
    'agotado' => ['Agotado', 'bg-terracotta/10 text-terracotta-2', 'bg-terracotta'],
    'por_confirmar' => ['Consultar disponibilidad', 'bg-cream-2 text-wood', 'bg-wood/50'],
];
const GRADE_SIZE = ['jumbo' => 1.14, 'primera' => 1, 'segunda' => 0.92, 'tercera' => 0.84, 'cuarta' => 0.76, 'quinta' => 0.68];

function render_public_page(array $d, bool $preview = false): void
{
    $s = $d['settings']; $c = $d['content']; $prices = $d['prices'];
    $primary = $d['contacts'][0] ?? null;
    $stamp = price_stamp($prices);
    $stale = $prices['status'] === 'desactualizado';
    $maple = [];
    foreach ($d['products'] as $p) foreach ($p['variants'] as $v) if ($v['presentation'] === 'maple' && $v['price'] !== null && $v['availability'] !== 'agotado') $maple[] = $v['price'];
    $fromPrice = $maple ? min($maple) : null;
    $heroImg = !empty($s['hero_image_path']) ? url($s['hero_image_path']) : url('assets/img/gallina-huevo.webp');
    $site = site_url();
    [$first, $second] = array_pad(explode(',', $c['hero']['title'] ?? '', 2), 2, '');
    $second = trim($second);

    $jsonLd = [
        '@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $s['business_name'] ?? '', 'slogan' => $s['slogan'] ?? '',
        'url' => $site . '/', 'image' => $site . '/assets/img/og.jpg',
        'telephone' => $primary ? '+' . wa_number($primary['phone']) : null,
        'address' => !empty($s['city']) ? ['@type' => 'PostalAddress', 'addressLocality' => explode(',', $s['city'])[0], 'addressCountry' => 'BO'] : null,
        'sameAs' => array_values(array_filter($s['social'] ?? [])),
    ];
    if ($prices['status'] === 'vigente') {
        foreach ($d['products'] as $p) foreach ($p['variants'] as $v) if ($v['price'] !== null) {
            $jsonLd['makesOffer'][] = ['@type' => 'Offer', 'name' => "Huevo {$p['name']} · {$v['label']}", 'price' => $v['price'], 'priceCurrency' => 'BOB',
                'availability' => $v['availability'] === 'agotado' ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock'];
        }
    }
    $jsonLd = array_filter($jsonLd, fn($x) => $x !== null);

    // Datos mínimos para el carrito en el navegador
    $cartData = ['products' => array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'variants' => array_map(fn($v) => [
        'id' => $v['id'], 'presentation' => $v['presentation'], 'price' => $v['price'], 'availability' => $v['availability']], $p['variants'])], $d['products']),
        'api' => url('api/'), 'orderPage' => url('pedido.php')];

    $title = 'Distribuidora Demo · Huevos frescos, de nuestra granja a tu mesa';
    $desc = 'Precios del día de huevos por maple y por paquete. Arma tu pedido y confírmalo por WhatsApp. Demostración de Comercio Integral.';
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <?php if ($preview): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <link rel="canonical" href="<?= e($site) ?>/">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="es_BO">
  <meta property="og:site_name" content="Distribuidora Demo">
  <meta property="og:title" content="Distribuidora Demo · Precios del huevo de hoy">
  <meta property="og:description" content="Huevos frescos por maple y por paquete. Haz tu pedido y confírmalo por WhatsApp.">
  <meta property="og:url" content="<?= e($site) ?>/">
  <meta property="og:image" content="<?= e($site) ?>/assets/img/og.jpg">
  <meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="#20361A">
  <link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png">
  <link rel="preload" href="<?= e(url('assets/fonts/fraunces-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/extra.css')) ?>">
  <?php if (!$preview): ?><script type="application/ld+json"><?= json_script($jsonLd) ?></script><?php endif; ?>
</head>
<body>
<a href="#precios" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-full focus:bg-yolk focus:px-4 focus:py-2 focus:font-bold focus:text-forest">Ir a los precios</a>
<?php if ($preview): ?>
  <div class="fixed inset-x-0 bottom-0 z-[60] bg-terracotta px-4 py-2 text-center text-sm font-semibold text-white">Vista previa del contenido en borrador · <a href="<?= e(url('admin/contenido.php')) ?>" class="underline">Volver al panel</a></div>
<?php endif; ?>

<header id="site-header" class="fixed inset-x-0 top-0 z-40 transition-colors duration-300" data-solid="false">
  <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-4 md:h-[72px] md:px-6">
    <a href="#inicio" aria-label="Distribuidora Demo, inicio" class="header-logo"><span class="logo-light"><?= logo($s['logo_path'] ?? null, true, true) ?></span><span class="logo-dark"><?= logo($s['logo_path'] ?? null, false, true) ?></span></a>
    <nav aria-label="Principal" class="hidden items-center gap-7 lg:flex">
      <?php foreach (['#precios' => 'Precios', '#contactos' => 'Contactos', '#granja' => 'La granja', '#preguntas' => 'Preguntas'] as $h => $l): ?>
        <a href="<?= $h ?>" class="nav-link text-[15px] font-medium transition"><?= $l ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="ml-auto flex items-center gap-2">
      <a href="#precios" title="Fecha de actualización de los precios" class="stamp stamp-<?= $stamp[1] ?> hidden items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold sm:flex"><?= icon('clock', 14) ?> <?= e($stamp[0]) ?></a>
      <button type="button" data-open-cart data-track="header" aria-label="Hacer pedido" class="relative flex shrink-0 items-center gap-2 rounded-full bg-yolk px-3 py-2.5 min-[380px]:px-4 text-sm font-bold text-forest shadow-[0_8px_20px_rgba(246,184,0,.35)] transition hover:bg-yolk-2">
        <?= icon('cart', 18) ?><span class="hidden whitespace-nowrap min-[380px]:inline"><span class="sm:hidden">Pedir</span><span class="hidden sm:inline">Hacer pedido</span></span>
        <span data-cart-count class="tabular absolute -right-1.5 -top-1.5 hidden h-5 min-w-5 place-items-center rounded-full bg-terracotta px-1 text-[11px] font-bold text-white"></span>
      </button>
      <button type="button" id="menu-toggle" aria-expanded="false" aria-controls="menu-movil" aria-label="Abrir menú" class="menu-btn grid h-10 w-10 place-items-center rounded-full lg:hidden"><?= icon('menu') ?></button>
    </div>
  </div>
  <nav id="menu-movil" aria-label="Menú" hidden class="border-t border-line bg-cream px-4 pb-5 pt-2 lg:hidden">
    <a href="#precios" class="mb-2 flex items-center gap-2 rounded-xl bg-cream-2 px-3 py-2.5 text-sm font-semibold text-wood"><?= icon('clock', 16) ?> <?= e($stamp[0]) ?></a>
    <?php foreach (['#precios' => 'Precios', '#contactos' => 'Contactos', '#granja' => 'La granja', '#preguntas' => 'Preguntas'] as $h => $l): ?>
      <a href="<?= $h ?>" class="display block border-b border-line py-3 text-xl font-semibold text-forest"><?= $l ?></a>
    <?php endforeach; ?>
  </nav>
</header>

<main>
  <!-- Portada -->
  <section id="inicio" class="grain grain-light relative overflow-hidden bg-forest pb-20 pt-28 text-cream md:pb-28 md:pt-36">
    <div aria-hidden="true" class="pointer-events-none absolute -right-40 -top-40 h-[720px] w-[720px] rounded-full bg-[radial-gradient(circle,rgba(246,184,0,.55)_0%,rgba(199,68,36,.28)_38%,rgba(8,59,42,0)_70%)]"></div>
    <div aria-hidden="true" class="rays pointer-events-none absolute -right-[260px] -top-[260px] h-[900px] w-[900px]">
      <svg viewBox="0 0 400 400" class="h-full w-full"><?php for ($i = 0; $i < 24; $i++): ?><path d="M200 200 L196 0 L204 0 Z" fill="#D9A23C" opacity="<?= $i % 2 ? '0.08' : '0.16' ?>" transform="rotate(<?= $i * 15 ?> 200 200)"/><?php endfor; ?></svg>
    </div>
    <div aria-hidden="true" data-parallax="0.35" class="pointer-events-none absolute -left-10 top-24 w-40 opacity-70 md:w-56"><svg viewBox="0 0 120 80"><path d="M6 70C20 26 56 6 114 6 104 52 70 76 20 74" fill="#4A6522"/><path d="M8 70C38 48 64 34 104 14" stroke="#20361A" stroke-width="2.5" fill="none" stroke-linecap="round"/></svg></div>
    <div aria-hidden="true" data-parallax="-0.25" class="pointer-events-none absolute bottom-6 right-[42%] hidden w-28 opacity-60 md:block"><svg viewBox="0 0 120 80" style="transform:scaleX(-1)"><path d="M6 70C20 26 56 6 114 6 104 52 70 76 20 74" fill="#4A6522"/><path d="M8 70C38 48 64 34 104 14" stroke="#20361A" stroke-width="2.5" fill="none" stroke-linecap="round"/></svg></div>

    <div class="relative z-10 mx-auto grid max-w-6xl items-center gap-12 px-4 md:grid-cols-[1.1fr_.9fr] md:px-6">
      <div>
        <img src="<?= e(url('assets/img/logo.webp')) ?>" alt="Logo de Distribuidora Demo" width="156" height="156" class="hero-logo enter">
        <p class="enter inline-flex flex-wrap items-center gap-x-2 rounded-full border border-cream/20 bg-cream/5 px-3.5 py-1.5 text-[12px] font-semibold uppercase tracking-[0.18em] text-yolk-2"><?= e($c['hero']['eyebrow'] ?? '') ?></p>
        <h1 class="display mt-6 text-[2.75rem] font-semibold leading-[0.98] sm:text-6xl lg:text-[5.25rem]">
          <span class="block overflow-hidden pb-1"><span class="rise block"><?= e(trim($first)) ?><?= $second !== '' ? ',' : '' ?></span></span>
          <?php if ($second !== ''): ?><span class="block overflow-hidden pb-2"><span class="rise rise-2 block italic text-yolk" style="font-variation-settings:'SOFT' 100,'WONK' 1"><?= e($second) ?></span></span><?php endif; ?>
        </h1>
        <p class="enter enter-3 mt-6 max-w-xl text-lg leading-relaxed text-cream/85"><?= e($c['hero']['subtitle'] ?? '') ?></p>
        <div class="enter enter-4 mt-9 flex flex-col gap-3 sm:flex-row">
          <a href="#precios" class="group inline-flex items-center justify-center gap-2 rounded-full bg-yolk px-7 py-4 text-base font-bold text-forest shadow-[0_14px_34px_rgba(246,184,0,.35)] transition hover:bg-yolk-2"><?= e($c['hero']['primary_cta'] ?? 'Ver precios de hoy') ?> <?= icon('arrow', 18, 'transition group-hover:translate-x-1') ?></a>
          <button type="button" data-open-cart data-track="hero" class="inline-flex items-center justify-center gap-2 rounded-full border border-cream/35 px-7 py-4 text-base font-semibold text-cream transition hover:bg-cream/10"><?= icon('cart', 18) ?> <?= e($c['hero']['secondary_cta'] ?? 'Hacer pedido') ?></button>
        </div>
        <ul class="enter enter-5 mt-10 flex flex-wrap gap-x-6 gap-y-2 text-sm text-cream/75" aria-label="Lo que nos distingue">
          <?php foreach (['Por maple y por paquete', 'De Jumbo a quinta', 'Pedido directo por WhatsApp'] as $t): ?><li class="flex items-center gap-2"><span class="egg h-3 w-2.5 bg-yolk" aria-hidden="true"></span><?= $t ?></li><?php endforeach; ?>
        </ul>
      </div>
      <div class="relative mx-auto w-full max-w-[380px] md:max-w-[420px]" data-parallax="0.12">
        <div class="hero-photo egg relative aspect-[4/5] overflow-hidden bg-forest-3 shadow-[0_40px_90px_rgba(0,0,0,.45)] ring-[6px] ring-cream/90">
          <img src="<?= e($heroImg) ?>" alt="Huevos frescos en su maple" class="h-full w-full object-cover" fetchpriority="high">
        </div>
        <div class="enter enter-5 absolute -bottom-6 -left-4 rounded-3xl bg-cream px-5 py-4 text-forest shadow-[0_20px_50px_rgba(0,0,0,.35)] sm:-left-10">
          <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-wood"><?= $prices['status'] === 'vigente' ? 'Precios de hoy' : 'Lista de precios' ?></p>
          <?php if ($fromPrice !== null && $prices['status'] === 'vigente'): ?>
            <p class="display tabular text-3xl font-semibold">desde <span class="text-terracotta"><?= e(bs($fromPrice)) ?></span></p>
          <?php else: ?><p class="display text-xl font-semibold">Consulta y pide</p><?php endif; ?>
          <p class="text-xs text-ink-2">por maple de 30 huevos</p>
        </div>
        <div class="pop absolute -right-3 top-6 grid h-24 w-24 place-items-center rounded-full bg-terracotta text-center text-[11px] font-bold uppercase leading-tight tracking-wider text-cream shadow-xl sm:-right-8">De nuestra<br>granja<br>a tu mesa</div>
      </div>
    </div>
    <svg aria-hidden="true" viewBox="0 0 1440 80" preserveAspectRatio="none" class="absolute inset-x-0 -bottom-px h-10 w-full text-cream md:h-16"><path d="M0 50C220 10 420 76 720 46S1200 6 1440 40V80H0Z" fill="currentColor"/></svg>
  </section>

  <!-- Precios de hoy -->
  <section id="precios" aria-labelledby="precios-titulo" class="grain relative bg-cream py-20 md:py-28">
    <div class="relative z-10 mx-auto max-w-4xl px-4 md:px-6">
      <div class="text-center">
        <p class="text-xs font-bold uppercase tracking-[0.28em] text-terracotta">Lista del día</p>
        <h2 id="precios-titulo" class="display mt-3 text-4xl font-semibold text-forest md:text-6xl">Precios de hoy</h2>
        <div class="mx-auto mt-5 max-w-xl">
          <?php if ($prices['status'] === 'vigente'): ?>
            <p class="inline-flex items-center gap-2 rounded-full bg-leaf/12 px-4 py-2 text-sm font-semibold text-leaf-2"><?= icon('clock', 16) ?> Vigentes desde el <?= e(fecha($prices['effective_from'], false, true)) ?>, <?= e(hora($prices['effective_from'])) ?></p>
          <?php elseif ($prices['status'] === 'desactualizado'): ?>
            <p role="status" class="flex items-start gap-2 rounded-2xl bg-yolk-soft px-4 py-3 text-left text-sm text-wood"><?= icon('alert', 18, 'mt-0.5 shrink-0') ?><span>Estos precios son del <strong><?= e(fecha($prices['effective_from'], false, true)) ?></strong> y pueden haber cambiado. Arma tu pedido y te confirmamos el precio de hoy por WhatsApp.</span></p>
          <?php else: ?>
            <p role="status" class="rounded-2xl bg-cream-2 px-4 py-3 text-sm text-wood">Todavía no publicamos los precios de hoy. Puedes armar tu pedido igual: te confirmamos precio y disponibilidad por WhatsApp.</p>
          <?php endif; ?>
          <?php if (!empty($d['stock']['auto'])): ?><p class="stock-note"><span class="live-dot" aria-hidden="true"></span>Disponibilidad según nuestro stock real<?= $d['stock']['updated_at'] ? ' · actualizado ' . e(fecha($d['stock']['updated_at'], false)) . ', ' . e(hora($d['stock']['updated_at'])) : '' ?></p><?php endif; ?>
          <?php if ($prices['next_effective_from']): ?><p class="mt-2 text-xs text-ink-2">Nuevos precios desde el <?= e(fecha($prices['next_effective_from'], false, true)) ?>, <?= e(hora($prices['next_effective_from'])) ?>.</p><?php endif; ?>
        </div>
      </div>

      <div role="tablist" aria-label="Presentación" class="mx-auto mt-10 grid max-w-xl grid-cols-2 rounded-full border border-line bg-paper p-1.5 shadow-sm">
        <?php foreach (['maple' => ['Por maple', '30 huevos'], 'paquete' => ['Por paquete', '10 maples · 300 huevos']] as $k => [$t, $sub]): ?>
          <button type="button" role="tab" id="tab-<?= $k ?>" aria-selected="<?= $k === 'maple' ? 'true' : 'false' ?>" aria-controls="panel-<?= $k ?>" tabindex="<?= $k === 'maple' ? '0' : '-1' ?>" data-tab="<?= $k ?>" class="price-tab relative rounded-full px-3 py-2.5 text-center">
            <span class="block text-[15px] font-bold"><?= $t ?></span><span class="tab-sub block text-xs"><?= $sub ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <?php foreach (['maple', 'paquete'] as $pres): ?>
        <ul id="panel-<?= $pres ?>" role="tabpanel" aria-labelledby="tab-<?= $pres ?>" class="price-panel mt-8 space-y-3" <?= $pres !== 'maple' ? 'hidden' : '' ?>>
          <?php $n = 0; foreach ($d['products'] as $p) foreach ($p['variants'] as $v) { if ($v['presentation'] !== $pres) continue; $n++; render_product_row($p, $v, $stale); } ?>
          <?php if ($n === 0): ?><li class="rounded-3xl border border-dashed border-line p-10 text-center text-ink-2">No hay productos en esta presentación por ahora.</li><?php endif; ?>
        </ul>
      <?php endforeach; ?>
      <p class="mt-6 text-center text-sm text-ink-2">Precios en bolivianos. La confirmación final de precio, disponibilidad y entrega la hace nuestro equipo por WhatsApp.</p>
    </div>
  </section>

  <!-- Contactos -->
  <section id="contactos" aria-labelledby="contactos-titulo" class="relative bg-cream-2 py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
      <div class="max-w-2xl" data-reveal>
        <p class="text-xs font-bold uppercase tracking-[0.28em] text-terracotta">WhatsApp</p>
        <h2 id="contactos-titulo" class="display mt-3 text-4xl font-semibold text-forest md:text-5xl"><?= e($c['contacts']['title'] ?? '') ?></h2>
        <p class="mt-4 text-lg text-ink-2"><?= e($c['contacts']['text'] ?? '') ?></p>
      </div>
      <ul class="mt-10 grid gap-4 md:grid-cols-3">
        <?php foreach ($d['contacts'] as $i => $ct): $pri = (int) $ct['is_primary'] === 1; ?>
          <li data-reveal style="--d:<?= $i * 80 ?>ms" class="flex h-full flex-col rounded-[28px] p-6 <?= $pri ? 'bg-forest text-cream shadow-[0_24px_60px_rgba(8,59,42,.3)]' : 'border border-line bg-paper' ?>">
            <p class="text-xs font-bold uppercase tracking-[0.2em] <?= $pri ? 'text-yolk' : 'text-wood' ?>"><?= 'Pedidos de esta zona' ?></p>
            <p class="display mt-3 text-2xl font-semibold"><?= e($ct['name']) ?></p>
            <p class="mt-1 flex items-center gap-1.5 text-sm <?= $pri ? 'text-cream/80' : 'text-ink-2' ?>"><?= icon('map', 16) ?> <?= e($ct['zone_name'] ?? 'Todas las zonas') ?><?= $ct['role_label'] ? ' · ' . e($ct['role_label']) : '' ?></p>
            <p class="tabular mt-4 text-lg font-semibold"><?= e(pretty_phone($ct['phone'])) ?></p>
            <a href="<?= e(wa_link($ct['phone'], "Hola {$ct['name']}, vi la lista de precios de Distribuidora Demo y quisiera hacer una consulta.")) ?>" target="_blank" rel="noopener noreferrer"
              data-contact="<?= e($ct['name']) ?>" class="mt-6 inline-flex items-center justify-center gap-2 rounded-full px-5 py-3 font-semibold transition <?= $pri ? 'bg-yolk text-forest hover:bg-yolk-2' : 'bg-wa text-white hover:bg-wa-2' ?>">
              <?= icon_whatsapp(20) ?> Escribir a <?= e(explode(' ', $ct['name'])[0]) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- Beneficios -->
  <section aria-labelledby="beneficios-titulo" class="grain grain-light relative overflow-hidden bg-forest py-20 text-cream md:py-28">
    <div aria-hidden="true" class="pointer-events-none absolute -left-32 bottom-0 h-96 w-96 rounded-full bg-[radial-gradient(circle,rgba(79,125,52,.55),transparent_70%)]"></div>
    <div class="relative z-10 mx-auto max-w-6xl px-4 md:px-6">
      <h2 id="beneficios-titulo" class="display text-4xl font-semibold md:text-5xl" data-reveal><?= e($c['benefits']['title'] ?? '') ?></h2>
      <ul class="mt-12 grid gap-px overflow-hidden rounded-[32px] bg-cream/10 sm:grid-cols-2 lg:grid-cols-5">
        <?php foreach ($c['benefits']['items'] ?? [] as $i => $b): ?>
          <li data-reveal style="--d:<?= $i * 60 ?>ms" class="h-full bg-forest p-6 transition-colors hover:bg-forest-2">
            <span class="egg grid h-14 w-12 place-items-center bg-yolk text-forest"><?= icon($b['icon'] ?? 'egg', 24) ?></span>
            <h3 class="display mt-5 text-xl font-semibold"><?= e($b['title'] ?? '') ?></h3>
            <p class="mt-2 text-sm leading-relaxed text-cream/75"><?= e($b['text'] ?? '') ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- La granja -->
  <section id="granja" aria-labelledby="granja-titulo" class="relative overflow-hidden bg-cream py-20 md:py-28">
    <div class="mx-auto grid max-w-6xl items-center gap-14 px-4 md:grid-cols-2 md:px-6">
      <div class="relative mx-auto h-[420px] w-full max-w-[440px]">
        <div data-reveal class="absolute left-0 top-0 w-[58%]"><div class="egg aspect-[4/5] overflow-hidden shadow-[0_30px_60px_rgba(100,56,32,.25)] ring-4 ring-paper"><img src="<?= e(url('assets/img/galpon.webp')) ?>" alt="Gallinas en el galpón de la granja" loading="lazy" class="h-full w-full object-cover"></div></div>
        <div data-reveal style="--d:120ms" class="absolute right-0 top-10 w-[46%]"><div class="aspect-square overflow-hidden rounded-full shadow-[0_30px_60px_rgba(100,56,32,.25)] ring-4 ring-paper"><img src="<?= e(url('assets/img/maple.webp')) ?>" alt="Maple de huevos" loading="lazy" class="h-full w-full object-cover"></div></div>
        <div data-reveal style="--d:240ms" class="absolute bottom-0 right-[12%] w-[50%]"><div class="aspect-[5/4] overflow-hidden rounded-[40%_60%_45%_55%/55%_45%_55%_45%] shadow-[0_30px_60px_rgba(100,56,32,.25)] ring-4 ring-paper"><img src="<?= e(url('assets/img/gallinas.webp')) ?>" alt="Gallinas de la granja" loading="lazy" class="h-full w-full object-cover"></div></div>
      </div>
      <div data-reveal>
        <p class="text-xs font-bold uppercase tracking-[0.28em] text-terracotta">La granja</p>
        <h2 id="granja-titulo" class="display mt-3 text-4xl font-semibold text-forest md:text-5xl"><?= e($c['brand']['title'] ?? '') ?></h2>
        <?php foreach (preg_split('/\n{2,}/', (string) ($c['brand']['text'] ?? '')) as $para): ?><p class="mt-5 text-lg leading-relaxed text-ink-2"><?= nl2br(e(trim($para))) ?></p><?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Preguntas -->
  <section id="preguntas" aria-labelledby="faq-titulo" class="bg-cream-2 py-20 md:py-28">
    <div class="mx-auto max-w-3xl px-4 md:px-6">
      <h2 id="faq-titulo" class="display text-center text-4xl font-semibold text-forest md:text-5xl" data-reveal><?= e($c['faq']['title'] ?? '') ?></h2>
      <div class="mt-10 space-y-3">
        <?php foreach ($c['faq']['items'] ?? [] as $i => $f): ?>
          <details data-reveal style="--d:<?= $i * 40 ?>ms" class="group rounded-3xl border border-line bg-paper px-5 open:shadow-[0_14px_30px_rgba(100,56,32,.08)]">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-5 text-left text-[17px] font-semibold text-forest [&::-webkit-details-marker]:hidden"><?= e($f['q'] ?? '') ?>
              <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-cream-2 transition group-open:rotate-180"><?= icon('chevron', 18) ?></span></summary>
            <p class="pb-5 leading-relaxed text-ink-2"><?= e($f['a'] ?? '') ?></p>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<footer class="grain grain-light relative bg-charcoal pb-28 pt-16 text-cream md:pb-12">
  <div class="relative z-10 mx-auto grid max-w-6xl gap-10 px-4 md:grid-cols-[1.3fr_1fr_1fr] md:px-6">
    <div>
      <?= logo($s['logo_path'] ?? null, true) ?>
      <p class="mt-4 max-w-sm text-sm text-cream/70"><?= e($s['tagline'] ?? '') ?> · <?= e($s['slogan'] ?? '') ?></p>
      <?php if (!empty($s['city'])): ?><p class="mt-2 flex items-center gap-1.5 text-sm text-cream/70"><?= icon('map', 16) ?> <?= e($s['city']) ?></p><?php endif; ?>
      <ul class="mt-5 flex gap-2" aria-label="Redes sociales">
        <?php foreach (['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook'] as $k => $label): if (empty($s['social'][$k])) continue; ?>
          <li><a href="<?= e($s['social'][$k]) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $label ?>" class="grid h-11 w-11 place-items-center rounded-full border border-cream/20 transition hover:bg-cream/10"><?= icon($k, 20) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-yolk">Contactos</h2>
      <ul class="mt-4 space-y-3 text-sm">
        <?php foreach ($d['contacts'] as $ct): ?>
          <li><a href="<?= e(wa_link($ct['phone'])) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 hover:text-yolk"><?= icon_whatsapp(18, 'text-yolk') ?><span><?= e($ct['name']) ?><?= $ct['zone_name'] ? ' · ' . e($ct['zone_name']) : '' ?> — <span class="tabular"><?= e(pretty_phone($ct['phone'])) ?></span></span></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="text-sm text-cream/70">
      <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-yolk">Información</h2>
      <p class="mt-4"><?= e($s['legal_text'] ?? '') ?></p>
      <p class="mt-3">Última actualización de precios: <strong class="text-cream"><?= $prices['effective_from'] ? e(fecha_hora($prices['effective_from'])) : 'pendiente' ?></strong></p>
      <p class="mt-6 text-xs text-cream/50">© <?= date('Y') ?> <?= e($s['business_name'] ?? '') ?></p>
    </div>
  </div>
</footer>

<?php render_cart_drawer($d['zones'], $c['order']['notice'] ?? '', $d['contacts']); ?>
<script type="application/json" id="acs-data"><?= json_script($cartData) ?></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/extra.js')) ?>" defer></script>
</body>
</html>
<?php
}

function render_product_row(array $p, array $v, bool $stale): void
{
    $soldOut = $v['availability'] === 'agotado';
    [$aLabel, $aCls, $aDot] = AVAIL_CLASSES[$v['availability']];
    $sz = GRADE_SIZE[$p['grade']] ?? 0.8;
    $presWord = $v['presentation'] === 'maple' ? 'maple' : 'paquete';
    ?>
  <li data-reveal data-variant="<?= (int) $v['id'] ?>" class="product-row group relative grid grid-cols-[auto_1fr] gap-x-3 gap-y-3 rounded-[28px] border bg-paper p-4 sm:grid-cols-[auto_1fr_auto] sm:items-center md:p-5 <?= $soldOut ? 'border-line/70 opacity-80' : 'border-line shadow-[0_1px_0_#ead9b9] hover:-translate-y-0.5 hover:shadow-[0_18px_40px_rgba(100,56,32,.12)]' ?>">
    <div class="relative grid h-16 w-14 shrink-0 place-items-center" aria-hidden="true">
      <?php if (!empty($p['image_path'])): ?>
        <img src="<?= e(url($p['image_path'])) ?>" alt="" class="egg h-14 w-12 object-cover">
      <?php else: ?>
        <div class="egg egg-wobble <?= $p['grade'] === 'jumbo' ? 'is-jumbo' : '' ?> bg-[radial-gradient(circle_at_35%_30%,#f7dcc0_0%,#d9a273_55%,#b67a4e_100%)] shadow-inner" style="animation-delay:<?= ((int) $v['id'] % 5) * 0.7 ?>s;width:<?= round(44 * $sz) ?>px;height:<?= round(56 * $sz) ?>px"></div>
      <?php endif; ?>
    </div>
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-2">
        <h3 class="display text-2xl font-semibold text-forest"><?= e($p['name']) ?></h3>
        <?php if ($p['badge']): ?><span class="rounded-full bg-yolk px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-forest"><?= e($p['badge']) ?></span><?php endif; ?>
      </div>
      <?php if ($p['description']): ?><p class="mt-0.5 text-sm text-ink-2"><?= e($p['description']) ?></p><?php endif; ?>
      <p class="mt-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $aCls ?>"><span class="h-2 w-2 rounded-full <?= $aDot ?>" aria-hidden="true"></span><?= $aLabel ?></p>
    </div>
    <div class="col-span-2 flex flex-wrap items-end justify-between gap-3 border-t border-dashed border-line pt-3 sm:col-span-1 sm:flex-col sm:items-end sm:border-0 sm:pt-0">
      <div class="text-right">
        <?php if ($soldOut): ?>
          <p class="display text-lg font-semibold text-ink-2">Sin stock por ahora</p>
        <?php elseif ($v['price'] !== null): ?>
          <p class="tabular leading-none <?= $stale ? 'text-ink-2' : 'text-forest' ?>"><span class="mr-1 align-top text-base font-semibold">Bs</span><span class="display text-[2.6rem] font-semibold" data-count="<?= e((string) $v['price']) ?>"><?= e(number_format($v['price'], floor($v['price']) == $v['price'] ? 0 : 2, ',', '.')) ?></span></p>
          <?php if ($v['presentation'] === 'paquete' && !$stale && ($p['package_saving'] ?? 0) > 0): ?><p><span class="save-chip">Ahorras <?= e(bs($p['package_saving'])) ?></span></p><?php endif; ?>
          <?php if ($stale): ?><p class="text-[11px] font-semibold uppercase tracking-wide text-terracotta-2">Precio anterior · confirmar</p><?php endif; ?>
        <?php else: ?>
          <p class="leading-tight text-wood"><span class="block text-[11px] font-bold uppercase tracking-wider">Precio</span><span class="display whitespace-nowrap text-lg font-semibold">por confirmar</span></p>
        <?php endif; ?>
      </div>
      <?php if ($soldOut): ?>
        <span class="rounded-full bg-cream-2 px-4 py-2.5 text-sm font-semibold text-ink-2">No disponible</span>
      <?php else: ?>
        <div class="ml-auto flex items-center gap-2">
          <div class="flex items-center rounded-full border border-line bg-cream" role="group" aria-label="Cantidad de <?= e($p['name']) ?>">
            <button type="button" data-step="-1" class="grid h-10 w-9 place-items-center rounded-l-full hover:bg-cream-2" aria-label="Menos"><?= icon('minus', 16) ?></button>
            <span class="qty tabular w-7 text-center font-semibold" aria-live="polite">1</span>
            <button type="button" data-step="1" class="grid h-10 w-9 place-items-center rounded-r-full hover:bg-cream-2" aria-label="Más"><?= icon('plus', 16) ?></button>
          </div>
          <button type="button" data-add data-name="<?= e($p['name']) ?>" data-pres="<?= $presWord ?>" aria-label="Agregar 1 <?= $presWord ?> de <?= e($p['name']) ?> al pedido"
            class="add-btn flex h-11 min-w-[96px] sm:min-w-[112px] items-center justify-center gap-1.5 rounded-full bg-forest px-4 text-sm font-bold text-cream transition hover:bg-forest-2 active:scale-95">Agregar</button>
        </div>
      <?php endif; ?>
    </div>
  </li>
<?php
}

function render_cart_drawer(array $zones, string $notice, array $contacts = []): void
{
    // Quién atiende cada zona, para que el cliente vea el nombre antes de pedir
    $primary = $contacts[0] ?? null;
    $routing = (bool) setting('zone_routing_enabled');
    $who = function (int $zoneId) use ($contacts, $primary, $routing) {
        if ($routing) foreach ($contacts as $ct) if ((int) $ct['zone_id'] === $zoneId) return $ct['name'];
        return $primary['name'] ?? '';
    };
    $field = 'w-full rounded-xl border border-line bg-paper px-3.5 py-3 text-[15px] text-charcoal placeholder:text-ink-2/60 outline-none transition focus:border-leaf focus:ring-2 focus:ring-yolk/50 aria-[invalid=true]:border-terracotta';
    ?>
<div id="cart" class="cart" hidden>
  <div class="cart-overlay fixed inset-0 z-50 bg-charcoal/50 backdrop-blur-[2px]" data-close-cart></div>
  <div role="dialog" aria-modal="true" aria-labelledby="cart-title" class="cart-sheet fixed inset-y-0 right-0 z-50 flex w-full max-w-[460px] flex-col bg-cream shadow-2xl outline-none" tabindex="-1">
    <div class="flex items-center justify-between border-b border-line px-5 py-4">
      <h2 id="cart-title" class="display text-2xl font-semibold text-forest">Tu pedido</h2>
      <button type="button" data-close-cart class="grid h-10 w-10 place-items-center rounded-full border border-line bg-paper text-forest hover:bg-cream-2" aria-label="Cerrar"><?= icon('x') ?></button>
    </div>

    <div data-view="empty" class="flex flex-1 flex-col items-center justify-center px-8 text-center">
      <div class="egg grid h-28 w-24 place-items-center bg-cream-2 text-wood/60"><?= icon('egg', 44) ?></div>
      <p class="display mt-5 text-xl font-semibold text-forest">Tu pedido está vacío</p>
      <p class="mt-2 text-sm text-ink-2">Elige una categoría y una presentación en la lista de precios.</p>
      <a href="#precios" data-close-cart class="mt-6 rounded-full bg-forest px-6 py-3 font-semibold text-cream hover:bg-forest-2">Ver precios de hoy</a>
    </div>

    <form data-view="form" novalidate class="flex min-h-0 flex-1 flex-col" hidden>
      <div class="flex-1 overflow-y-auto px-5 pb-6">
        <ul class="mt-4 space-y-2.5" aria-label="Productos del pedido" data-items></ul>
        <div class="mt-4 rounded-2xl bg-forest p-4 text-cream">
          <div class="flex items-baseline justify-between"><span class="text-sm opacity-80" data-total-label>Total</span><span class="tabular text-2xl font-bold text-yolk" data-total>Bs 0</span></div>
          <p class="mt-1 hidden text-xs text-cream/80" data-unpriced>Algunos productos tienen precio por confirmar. Te lo confirmamos por WhatsApp.</p>
        </div>
        <fieldset class="mt-6 space-y-3.5">
          <legend class="display mb-1 text-lg font-semibold text-forest">Tus datos de entrega</legend>
          <div><label for="f-name" class="mb-1 block text-sm font-medium">Nombre <span aria-hidden="true" class="text-terracotta">*</span></label>
            <input id="f-name" name="name" autocomplete="name" maxlength="80" class="<?= $field ?>"><p class="field-error mt-1 hidden text-sm text-terracotta-2" id="e-name"></p></div>
          <div><label for="f-phone" class="mb-1 block text-sm font-medium">Teléfono / WhatsApp <span aria-hidden="true" class="text-terracotta">*</span></label>
              <input id="f-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="7XXXXXXX" class="<?= $field ?>"><p class="field-error mt-1 hidden text-sm text-terracotta-2" id="e-phone"></p></div>
          <fieldset class="zone-pick">
            <legend class="mb-1 block text-sm font-medium">¿En qué zona estás? <span aria-hidden="true" class="text-terracotta">*</span></legend>
            <div class="zone-grid">
              <?php foreach ($zones as $z): $n = $who((int) $z['id']); ?>
                <label class="zone-opt"><input type="radio" name="zone_id" value="<?= (int) $z['id'] ?>"><span><strong><?= e($z['name']) ?></strong><?php if ($n !== ''): ?><small>Te atiende <?= e($n) ?></small><?php endif; ?></span></label>
              <?php endforeach; ?>
              <label class="zone-opt"><input type="radio" name="zone_id" value="0"><span><strong>Otra zona o no sé</strong><?php if (!empty($primary['name'])): ?><small>Te atiende <?= e($primary['name']) ?></small><?php endif; ?></span></label>
            </div>
            <p class="field-error mt-1 hidden text-sm text-terracotta-2" id="e-zone_id"></p>
          </fieldset>
          <div><label for="f-address" class="mb-1 block text-sm font-medium">Dirección o punto de entrega <span aria-hidden="true" class="text-terracotta">*</span></label>
            <input id="f-address" name="address" autocomplete="street-address" maxlength="300" class="<?= $field ?>"><p class="field-error mt-1 hidden text-sm text-terracotta-2" id="e-address"></p></div>
          <div><label for="f-reference" class="mb-1 block text-sm font-medium">Referencia</label>
            <input id="f-reference" name="reference" maxlength="300" placeholder="Ej.: portón verde, frente a la plaza" class="<?= $field ?>"></div>
          <div><label for="f-notes" class="mb-1 block text-sm font-medium">Observaciones</label>
            <textarea id="f-notes" name="notes" rows="2" maxlength="500" placeholder="Horario preferido, forma de entrega…" class="<?= $field ?>"></textarea></div>
          <input name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] h-0 w-0 opacity-0">
        </fieldset>
      </div>
      <div class="border-t border-line bg-paper px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4">
        <p role="alert" class="mb-3 hidden gap-2 rounded-xl bg-terracotta/10 p-3 text-sm text-terracotta-2" data-server-error></p>
        <p class="mb-3 text-xs leading-relaxed text-ink-2"><?= e($notice) ?></p>
        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-full bg-wa px-6 py-4 text-base font-semibold text-white shadow-lg transition hover:bg-wa-2 disabled:opacity-60"><?= icon_whatsapp(22) ?> <span data-submit-label>Guardar y enviar por WhatsApp</span></button>
      </div>
    </form>

    <div data-view="done" class="flex-1 overflow-y-auto px-5 py-6" hidden>
      <div class="egg mx-auto grid h-24 w-20 place-items-center bg-yolk text-forest done-pop"><?= icon('check', 40) ?></div>
      <p class="mt-5 text-center text-sm text-ink-2">Número de pedido</p>
      <p class="tabular text-center text-3xl font-bold text-forest" data-testid="order-code" data-done-code></p>
      <ul class="mt-6 divide-y divide-line rounded-2xl border border-line bg-paper" data-done-items></ul>
      <a data-done-wa href="#" target="_blank" rel="noopener noreferrer" class="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-wa px-6 py-4 text-base font-semibold text-white shadow-lg transition hover:bg-wa-2"><?= icon_whatsapp(22) ?> Enviar pedido por WhatsApp</a>
      <p class="mt-4 rounded-xl bg-yolk-soft p-4 text-sm text-wood" data-done-note></p>
      <p class="mt-3 text-center text-xs text-ink-2">La confirmación final de precio, disponibilidad y entrega la realiza nuestro equipo.</p>
      <a data-done-link href="#" class="mt-5 block text-center text-sm font-semibold text-leaf-2 underline underline-offset-4">Ver el resumen de mi pedido</a>
    </div>
  </div>
</div>

<div id="cart-bar" class="fixed inset-x-3 bottom-3 z-40 md:hidden" hidden>
  <button type="button" data-open-cart class="flex w-full items-center justify-between rounded-full bg-forest py-2.5 pl-5 pr-2.5 text-cream shadow-[0_18px_40px_rgba(8,59,42,.45)]">
    <span class="flex items-center gap-2 text-sm font-medium"><?= icon('cart', 20) ?><span data-bar-count></span></span>
    <span class="tabular rounded-full bg-yolk px-4 py-2 text-sm font-bold text-forest" data-bar-total></span>
  </button>
</div>
<?php
}
