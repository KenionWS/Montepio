<?php
declare(strict_types=1);

// ─── Rutas ───────────────────────────────────────────────────────────────────
define('ROOT_PATH',    realpath(__DIR__ . '/../../'));
define('DATA_PATH',    ROOT_PATH . '/data');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ADMIN_URL',    '/New/admin');
define('UPLOADS_URL',  '/New/uploads');
define('BASE_URL',     '/New');

// ─── Base de datos ────────────────────────────────────────────────────────────
define('DB_PATH',      DATA_PATH . '/montepio.sqlite');

// ─── Imágenes ─────────────────────────────────────────────────────────────────
define('IMG_THUMB_SIZE',  300);   // px, cuadrado
define('IMG_MEDIUM_SIZE', 800);   // px, lado mayor
define('IMG_FULL_SIZE',  1400);   // px, lado mayor
define('IMG_QUALITY',     85);    // JPEG quality
define('IMG_FULL_QUALITY', 78);   // JPEG quality para la imagen principal

// ─── Contraseña admin ─────────────────────────────────────────────────────────
// Ejecutar setup.php una vez para generar el hash
$passFile = DATA_PATH . '/pass.php';
define('PASS_FILE', $passFile);
if (file_exists($passFile)) {
    $passData = include $passFile;
    define('ADMIN_PASS_HASH', $passData['hash'] ?? '');
} else {
    define('ADMIN_PASS_HASH', '');
}

// ─── Upload ───────────────────────────────────────────────────────────────────
define('MAX_UPLOAD_MB',   20);
define('MAX_IMAGE_WIDTH', 9000);
define('MAX_IMAGE_HEIGHT', 9000);
define('MAX_IMAGE_PIXELS', 30000000);
define('TEMP_TTL_HOURS',   2);
define('ALLOWED_MIME', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
