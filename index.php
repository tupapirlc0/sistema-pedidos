<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/public_page.php';

// Vista previa del borrador: solo para usuarios del panel con permiso de contenido
$preview = isset($_GET['vista_previa']);
if ($preview) {
    start_session();
    $u = current_user();
    if (!$u || !can('content', $u['role'])) redirect('admin/login.php');
}
security_headers($preview);
header('Content-Type: text/html; charset=utf-8');
if (!$preview) header('Cache-Control: no-cache');
render_public_page(public_data($preview), $preview);
