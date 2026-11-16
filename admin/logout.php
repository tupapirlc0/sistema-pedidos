<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin/index.php');
csrf_verify();
logout();
redirect('admin/login.php');
