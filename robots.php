<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$b = base_path();
echo "User-agent: *\nAllow: $b/\nDisallow: $b/admin/\nDisallow: $b/api/\nDisallow: $b/pedido.php\nDisallow: $b/instalar.php\n\nSitemap: " . site_url() . "/sitemap.xml\n";
