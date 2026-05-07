<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
auth_logout();
header('Location: ' . ADMIN_URL . '/index.php');
exit;
