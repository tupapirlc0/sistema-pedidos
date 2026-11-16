<?php
// Diseño común del panel: menú lateral según el rol, avisos y pequeños componentes.
declare(strict_types=1);

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/data.php';

const ADMIN_NAV = [
    ['index.php', 'Resumen', 'home', null],
    ['ventas.php', 'Ventas y cuentas', 'star', 'sales'],
    ['stock.php', 'Stock de huevos', 'egg', 'stock'],
    ['pedidos.php', 'Pedidos', 'cart', 'orders'],
    ['precios.php', 'Precios del día', 'clock', 'prices'],
    ['productos.php', 'Productos', 'egg', 'products'],
    ['inventario.php', 'Disponibilidad manual', 'check', 'inventory'],
    ['clientes.php', 'Clientes', 'map', 'customers'],
    ['contenido.php', 'Contenido', 'leaf', 'content'],
    ['analitica.php', 'Analítica', 'star', 'analytics'],
    ['usuarios.php', 'Usuarios', 'shield', 'users'],
    ['configuracion.php', 'Configuración', 'sun', 'settings'],
];
const ROLE_LABELS = ['admin' => 'Administrador', 'socio' => 'Vendedor', 'ventas' => 'Ventas', 'operador' => 'Operador'];

function admin_head(string $title, array $user, string $active): void
{
    security_headers(true);
    header('Content-Type: text/html; charset=utf-8');
    $flashes = take_flashes();
    if (isset($_GET['sinpermiso'])) $flashes[] = ['error', 'Tu rol no tiene acceso a esa sección.'];
    ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Panel · Distribuidora Demo</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="<?= e(url('assets/img/favicon.png')) ?>" type="image/png">
  <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/extra.css')) ?>">
</head>
<body class="min-h-screen bg-cream-2/60">
<a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded-full focus:bg-yolk focus:px-4 focus:py-2 focus:font-bold focus:text-forest">Ir al contenido</a>
<div class="lg:grid lg:grid-cols-[250px_1fr]">
  <aside class="no-print bg-forest text-cream lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
    <div class="flex items-center justify-between px-4 py-3 lg:block lg:px-5 lg:py-6">
      <a href="<?= e(url('admin/index.php')) ?>"><?= logo(setting('logo_path'), true, true) ?></a>
      <button type="button" id="admin-menu" aria-expanded="false" aria-controls="admin-nav" class="grid h-10 w-10 place-items-center rounded-full border border-cream/20 lg:hidden" aria-label="Abrir menú"><?= icon('menu') ?></button>
    </div>
    <nav id="admin-nav" aria-label="Panel" class="hidden px-3 pb-5 lg:block">
      <ul class="space-y-0.5">
        <?php
        // Aviso de pedidos nuevos en el menú
        $pendingNew = (int) val("SELECT COUNT(*) FROM orders WHERE status = 'nuevo'" . (can('orders_all', $user['role']) ? '' : ' AND assigned_to = ' . (int) $user['id']));
        foreach (ADMIN_NAV as [$href, $label, $ic, $perm]): if ($perm && !can($perm, $user['role'])) continue; $on = $active === $href;
          if ($href === 'pedidos.php' && !can('orders_all', $user['role'])) $label = 'Mis pedidos'; ?>
          <li><a href="<?= e(url('admin/' . $href)) ?>" <?= $on ? 'aria-current="page"' : '' ?>
            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[15px] font-medium transition <?= $on ? 'bg-yolk text-forest' : 'text-cream/85 hover:bg-cream/10' ?>"><?= icon($ic, 18) ?> <?= e($label) ?><?= $href === 'pedidos.php' && $pendingNew ? '<span class="nav-badge">' . $pendingNew . '</span>' : '' ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="mt-6 border-t border-cream/15 pt-4 text-sm">
        <p class="px-3 font-semibold"><?= e($user['name']) ?></p>
        <p class="px-3 text-xs text-cream/70"><?= e(ROLE_LABELS[$user['role']] ?? $user['role']) ?></p>
        <a href="<?= e(url('admin/cuenta.php')) ?>" class="mt-2 block rounded-xl px-3 py-2 text-cream/85 hover:bg-cream/10 <?= $active === 'cuenta.php' ? 'bg-cream/10' : '' ?>">Mi cuenta</a>
        <a href="<?= e(url('')) ?>" target="_blank" rel="noopener" class="block rounded-xl px-3 py-2 text-cream/85 hover:bg-cream/10">Ver la página pública ↗</a>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button class="w-full rounded-xl px-3 py-2 text-left text-cream/85 hover:bg-cream/10">Cerrar sesión</button></form>
      </div>
    </nav>
  </aside>
  <main id="contenido" class="min-w-0 px-4 py-6 md:px-8 md:py-8">
    <div id="toasts" class="no-print fixed right-4 top-4 z-50 flex max-w-sm flex-col gap-2" aria-live="polite">
      <?php foreach ($flashes as [$kind, $msg]): ?>
        <div role="<?= $kind === 'error' ? 'alert' : 'status' ?>" class="toast flex items-start gap-2 rounded-2xl px-4 py-3 text-sm font-medium shadow-lg <?= $kind === 'error' ? 'bg-terracotta-2 text-white' : 'bg-forest text-cream' ?>">
          <?= icon($kind === 'error' ? 'alert' : 'check', 18, 'mt-0.5 shrink-0') ?><span class="flex-1"><?= e($msg) ?></span>
          <button type="button" data-dismiss class="opacity-80 hover:opacity-100" aria-label="Cerrar aviso"><?= icon('x', 16) ?></button>
        </div>
      <?php endforeach; ?>
    </div>
    <h1 class="display text-3xl font-semibold text-forest md:text-4xl"><?= e($title) ?></h1>
<?php
}

function admin_foot(): void
{
    ?>
  </main>
</div>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/extra.js')) ?>" defer></script>
</body>
</html>
<?php
}

// Etiqueta de color para estados
function pill(string $label, string $tone): string
{
    $cls = [
        'yolk' => 'bg-yolk-soft text-wood', 'wood' => 'bg-cream-2 text-wood', 'leaf' => 'bg-leaf/12 text-leaf-2',
        'forest' => 'bg-forest text-cream', 'terracotta' => 'bg-terracotta/10 text-terracotta-2', 'gray' => 'bg-charcoal/8 text-ink-2',
    ][$tone] ?? 'bg-cream-2 text-wood';
    return '<span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ' . $cls . '">' . e($label) . '</span>';
}
function status_pill(string $s): string { [$l, $t] = ORDER_STATUS[$s] ?? [$s, 'gray']; return pill($l, $t); }

function card_open(string $title = '', string $extra = ''): void
{
    echo '<section class="mt-6 rounded-[24px] border border-line bg-paper p-5 md:p-6 ' . e($extra) . '">';
    if ($title !== '') echo '<h2 class="display text-xl font-semibold text-forest">' . e($title) . '</h2>';
}
function card_close(): void { echo '</section>'; }

function btn(string $label, string $kind = 'primary', string $attrs = ''): string
{
    $cls = [
        'primary' => 'bg-forest text-cream hover:bg-forest-2',
        'accent' => 'bg-yolk text-forest hover:bg-yolk-2',
        'ghost' => 'border border-line bg-paper text-forest hover:bg-cream-2',
        'danger' => 'border border-terracotta/40 bg-paper text-terracotta-2 hover:bg-terracotta/10',
        'wa' => 'bg-wa text-white hover:bg-wa-2',
    ][$kind];
    return '<button ' . $attrs . ' class="inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-full px-5 py-2.5 text-sm font-semibold transition disabled:opacity-50 ' . $cls . '">' . $label . '</button>';
}
function link_btn(string $href, string $label, string $kind = 'ghost', string $attrs = ''): string
{
    $cls = ['primary' => 'bg-forest text-cream hover:bg-forest-2', 'ghost' => 'border border-line bg-paper text-forest hover:bg-cream-2', 'wa' => 'bg-wa text-white hover:bg-wa-2', 'accent' => 'bg-yolk text-forest hover:bg-yolk-2'][$kind];
    return '<a href="' . e($href) . '" ' . $attrs . ' class="inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold transition ' . $cls . '">' . $label . '</a>';
}

function label(string $for, string $text, bool $required = false): string
{
    return '<label for="' . e($for) . '" class="mb-1 block text-sm font-medium text-charcoal">' . e($text) . ($required ? ' <span class="text-terracotta" aria-hidden="true">*</span>' : '') . '</label>';
}

function select_options(array $opts, mixed $selected): string
{
    $h = '';
    foreach ($opts as $v => $l) $h .= '<option value="' . e($v) . '"' . ((string) $v === (string) $selected ? ' selected' : '') . '>' . e($l) . '</option>';
    return $h;
}

// Fecha/hora local de La Paz (input datetime-local) ⇄ UTC para la base
function local_input(?string $utc): string { $d = local_dt($utc); return $d ? $d->format('Y-m-d\TH:i') : ''; }
function local_to_utc(string $local): ?string
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $local, new DateTimeZone(ACS_TZ));
    return $d ? $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
}
function local_date_to_utc(string $ymd, bool $endOfDay = false): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return null;
    $d = new DateTimeImmutable($ymd . ($endOfDay ? ' 23:59:59' : ' 00:00:00'), new DateTimeZone(ACS_TZ));
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function pager(int $page, int $total, int $per, array $query): string
{
    $pages = max(1, (int) ceil($total / $per));
    if ($pages <= 1) return '';
    $link = fn($p) => '?' . http_build_query(['p' => $p] + $query);
    $h = '<nav aria-label="Páginas" class="mt-5 flex items-center justify-between text-sm"><span class="text-ink-2">Página ' . $page . ' de ' . $pages . ' · ' . $total . ' en total</span><span class="flex gap-2">';
    if ($page > 1) $h .= link_btn($link($page - 1), '← Anterior');
    if ($page < $pages) $h .= link_btn($link($page + 1), 'Siguiente →');
    return $h . '</span></nav>';
}

function empty_state(string $text): string
{
    return '<div class="mt-4 rounded-2xl border border-dashed border-line p-8 text-center text-ink-2">' . e($text) . '</div>';
}

function variant_name(string $product, string $presentation): string
{
    return $product . ' · ' . ($presentation === 'maple' ? 'Maple (30)' : 'Paquete (10 maples)');
}

// Gráfico de barras de una sola serie (SVG del servidor). Cada barra tiene su valor al pasar el cursor
// y debajo hay una tabla con los mismos datos.
function bar_chart(array $data, string $caption, string $unit = ''): string
{
    $w = 640; $h = 200; $pl = 34; $pb = 26; $pt = 12;
    $n = max(1, count($data));
    $max = max(1, ...array_values($data ?: [0]));
    $step = $max <= 5 ? 1 : (int) ceil($max / 4);
    $top = $step * (int) ceil($max / $step);
    $slot = ($w - $pl) / $n; $bw = max(4, min(28, $slot * 0.62));
    $y = fn($v) => $pt + ($h - $pt - $pb) * (1 - $v / $top);
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="h-auto w-full" role="img" aria-label="' . e($caption) . '">';
    for ($g = 0; $g <= $top; $g += $step) {
        $gy = round($y($g), 1);
        $svg .= '<line x1="' . $pl . '" x2="' . $w . '" y1="' . $gy . '" y2="' . $gy . '" stroke="#E5D6B4" stroke-width="1"' . ($g ? ' stroke-dasharray="3 4"' : '') . '/>'
            . '<text x="' . ($pl - 6) . '" y="' . ($gy + 4) . '" text-anchor="end" font-size="11" fill="#4C5243">' . $g . '</text>';
    }
    $i = 0; $every = (int) ceil($n / 8);
    foreach ($data as $label => $v) {
        $cx = $pl + $slot * ($i + 0.5); $x = round($cx - $bw / 2, 1); $by = round($y($v), 1); $bh = round($h - $pb - $by, 1);
        $tip = e($label . ': ' . $v . ($unit ? " $unit" : ''));
        $svg .= '<g><title>' . $tip . '</title><rect x="' . round($cx - $slot / 2, 1) . '" y="' . $pt . '" width="' . round($slot, 1) . '" height="' . ($h - $pt - $pb) . '" fill="transparent"/>';
        if ($v > 0) {
            $r = min(4, $bw / 2, $bh);
            $svg .= '<path d="M' . $x . ' ' . ($h - $pb) . 'V' . ($by + $r) . 'Q' . $x . ' ' . $by . ' ' . ($x + $r) . ' ' . $by . 'H' . ($x + $bw - $r) . 'Q' . ($x + $bw) . ' ' . $by . ' ' . ($x + $bw) . ' ' . ($by + $r) . 'V' . ($h - $pb) . 'Z" fill="#4A6522"/>';
        }
        $svg .= '</g>';
        if ($i % $every === 0 || $i === $n - 1) $svg .= '<text x="' . round($cx, 1) . '" y="' . ($h - 8) . '" text-anchor="middle" font-size="11" fill="#4C5243">' . e($label) . '</text>';
        $i++;
    }
    $svg .= '</svg>';
    $table = '<details class="mt-2 text-sm"><summary class="cursor-pointer text-ink-2">Ver como tabla</summary><table class="mt-2 w-full text-left"><thead><tr class="text-ink-2"><th class="py-1 font-medium">Día</th><th class="py-1 text-right font-medium">' . e($unit ?: 'Valor') . '</th></tr></thead><tbody>';
    foreach ($data as $label => $v) $table .= '<tr class="border-t border-line"><td class="py-1">' . e($label) . '</td><td class="tabular py-1 text-right">' . $v . '</td></tr>';
    return '<figure>' . $svg . '<figcaption class="sr-only">' . e($caption) . '</figcaption>' . $table . '</tbody></table></details></figure>';
}

// Serie diaria (fechas locales de La Paz) a partir de filas [dia => n]
function daily_series(array $byDay, int $days): array
{
    $out = [];
    $today = new DateTimeImmutable('today', new DateTimeZone(ACS_TZ));
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = $today->modify("-$i day");
        $out[$d->format('j') . ' ' . MESES[(int) $d->format('n') - 1]] = (int) ($byDay[$d->format('Y-m-d')] ?? 0);
    }
    return $out;
}

function kpi(string $label, string $value, string $hint = '', string $href = ''): string
{
    $inner = '<p class="text-sm text-ink-2">' . e($label) . '</p><p class="tabular display mt-1 text-3xl font-semibold text-forest">' . e($value) . '</p>'
        . ($hint !== '' ? '<p class="mt-1 text-xs text-ink-2">' . e($hint) . '</p>' : '');
    $cls = 'block rounded-[22px] border border-line bg-paper p-5';
    return $href ? '<a href="' . e($href) . '" class="' . $cls . ' transition hover:border-leaf">' . $inner . '</a>' : '<div class="' . $cls . '">' . $inner . '</div>';
}
