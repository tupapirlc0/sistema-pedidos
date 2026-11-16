<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$last = val("SELECT MAX(published_at) FROM price_publications WHERE status = 'published'") ?: now_utc();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . e(site_url()) . '/</loc><lastmod>'
    . gmdate('c', strtotime($last . ' UTC')) . '</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>';
