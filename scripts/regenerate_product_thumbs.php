<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin/lib/config.php';
require_once __DIR__ . '/../admin/lib/product_thumbs.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script se ejecuta por CLI.\n");
    exit(1);
}

$productIdFilter = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--product=(\d+)$/', $arg, $matches)) {
        $productIdFilter = (int)$matches[1];
    }
}

$result = regenerate_product_thumbs($productIdFilter);
if ($result['total'] === 0) {
    fwrite(STDOUT, "No hay imagenes para regenerar.\n");
    exit(0);
}

foreach ($result['logs'] as $log) {
    fwrite(STDOUT, ($log['type'] === 'ok' ? '[OK] ' : '[ERROR] ') . $log['message'] . "\n");
}

fwrite(STDOUT, "\nResumen: {$result['processed']} regeneradas, {$result['failed']} con error.\n");
