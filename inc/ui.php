<?php
// Íconos y marca compartidos por la página pública y el panel.
declare(strict_types=1);

const ICON_PATHS = [
    'egg' => '<path d="M12 3c3.6 0 6.5 5.4 6.5 10.2A6.5 6.5 0 0 1 12 20a6.5 6.5 0 0 1-6.5-6.8C5.5 8.4 8.4 3 12 3Z"/>',
    'star' => '<path d="m12 3.5 2.6 5.3 5.8.8-4.2 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.6 9.6l5.8-.8L12 3.5Z"/>',
    'leaf' => '<path d="M5 19c0-8 5-13 15-14-.5 9.5-6 14-13 14"/><path d="M5 19c3-4 6-6.5 9.5-8.5"/>',
    'home' => '<path d="M4 11 12 4l8 7"/><path d="M6 9.5V20h12V9.5"/><path d="M10 20v-5h4v5"/>',
    'shield' => '<path d="M12 3 5 6v5.5c0 4.3 3 7.8 7 9.5 4-1.7 7-5.2 7-9.5V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>',
    'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2.5M12 19v2.5M2.5 12H5M19 12h2.5M5.3 5.3 7 7M17 17l1.7 1.7M5.3 18.7 7 17M17 7l1.7-1.7"/>',
    'cart' => '<path d="M5 8h14l-1.4 10.2a2 2 0 0 1-2 1.8H8.4a2 2 0 0 1-2-1.8L5 8Z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'minus' => '<path d="M5 12h14"/>',
    'trash' => '<path d="M4 7h16M9 7V4h6v3M6.5 7l1 13h9l1-13"/>',
    'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
    'menu' => '<path d="M4 8h16M4 16h16"/>',
    'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'chevron' => '<path d="m6 9 6 6 6-6"/>',
    'alert' => '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4M12 17h.01"/>',
    'map' => '<path d="M12 21s7-6.1 7-11.4A7 7 0 0 0 5 9.6C5 14.9 12 21 12 21Z"/><circle cx="12" cy="9.6" r="2.4"/>',
    'print' => '<path d="M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2"/><path d="M7 14h10v6H7z"/>',
    'download' => '<path d="M12 4v11m-4.5-4.5L12 15l4.5-4.5M5 20h14"/>',
    'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".6" fill="currentColor"/>',
    'tiktok' => '<path d="M14 3.5v11.2a3.8 3.8 0 1 1-3.8-3.8"/><path d="M14 3.5c.4 2.6 2.2 4.4 5 4.6"/>',
    'facebook' => '<path d="M14.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.6-1.5h1.5V4.4a19 19 0 0 0-2.3-.1c-2.3 0-3.8 1.4-3.8 3.9v2.3H8.9v3h2.6V21"/>',
];

function icon(string $name, int $size = 20, string $class = ''): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"'
        . ($class ? ' class="' . e($class) . '"' : '') . '>' . (ICON_PATHS[$name] ?? ICON_PATHS['egg']) . '</svg>';
}

function icon_whatsapp(int $size = 20, string $class = ''): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"' . ($class ? ' class="' . e($class) . '"' : '') . '><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.6-.3Z"/></svg>';
}

// Marca provisoria: huevo con amanecer y hoja (se reemplaza al subir el logo oficial)
function logo_mark(int $size = 44, string $class = ''): string
{
    static $n = 0;
    $id = 'lm' . (++$n);
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" aria-hidden="true"' . ($class ? ' class="' . e($class) . '"' : '') . '>'
        . '<defs><clipPath id="' . $id . 'c"><path d="M32 4c11.6 0 21 17.4 21 32.8C53 49.6 43.6 60 32 60S11 49.6 11 36.8C11 21.4 20.4 4 32 4Z"/></clipPath>'
        . '<linearGradient id="' . $id . 's" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FBF4E2"/><stop offset="1" stop-color="#F8EBC8"/></linearGradient></defs>'
        . '<path d="M32 4c11.6 0 21 17.4 21 32.8C53 49.6 43.6 60 32 60S11 49.6 11 36.8C11 21.4 20.4 4 32 4Z" fill="url(#' . $id . 's)"/>'
        . '<g clip-path="url(#' . $id . 'c)"><g stroke="#D9A23C" stroke-width="2.2" stroke-linecap="round"><path d="M32 22v-6M22 26l-4-4M42 26l4-4M17 35h-6M47 35h6"/></g>'
        . '<circle cx="32" cy="39" r="10" fill="#D9A23C"/><path d="M6 44c9-5 17-6.5 26-4.5S50 44 60 41v23H6Z" fill="#4A6522"/><path d="M4 50c11-4 20-4.6 29-2s19 3 28-1v17H4Z" fill="#20361A"/></g>'
        . '<path d="M32 4c11.6 0 21 17.4 21 32.8C53 49.6 43.6 60 32 60S11 49.6 11 36.8C11 21.4 20.4 4 32 4Z" fill="none" stroke="#5F2A0E" stroke-width="2.4"/>'
        . '<path d="M46 10c-5 .8-8 4.4-8.4 9.6 5.2-.4 8.5-3.8 8.4-9.6Z" fill="#4A6522" stroke="#20361A" stroke-width="1.2"/></svg>';
}

function logo(?string $logoPath, bool $light = false, bool $compact = false): string
{
    // Logo oficial del cliente; si en Configuración suben otro, se usa ese.
    $size = $compact ? 46 : 58;
    $mark = '<img src="' . e(url($logoPath ?: 'assets/img/logo.webp')) . '" alt="" width="' . $size . '" height="' . $size . '" class="brand-logo" style="width:' . $size . 'px;height:' . $size . 'px">';
    return '<span class="flex items-center gap-2.5">' . $mark . '<span class="leading-none ' . ($light ? 'text-cream' : 'text-forest') . '">'
        . '<span class="block text-[10px] font-semibold uppercase tracking-[0.28em] opacity-80">Distribuidora</span>'
        . '<span class="display block whitespace-nowrap font-semibold ' . ($compact ? 'text-base sm:text-lg' : 'text-[1.35rem]') . '">Demo</span></span></span>';
}
