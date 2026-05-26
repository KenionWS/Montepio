<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/image.php';
auth_require();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido', 405);

// Limpiar tokens viejos
try {
    db()->exec("DELETE FROM upload_temp WHERE created_at < datetime('now', '-" . TEMP_TTL_HOURS . " hours')");
    $tempBase = UPLOADS_PATH . '/temp';
    if (is_dir($tempBase)) {
        foreach (scandir($tempBase) as $d) {
            if ($d === '.' || $d === '..') continue;
            $path = $tempBase . '/' . $d;
            if (is_dir($path) && filemtime($path) < time() - TEMP_TTL_HOURS * 3600) {
                rmdir_recursive($path);
            }
        }
    }
} catch (Exception $e) { /* no bloquear */ }

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    json_err('Error al subir el archivo: ' . ($file['error'] ?? 'desconocido'));
}
if ($file['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
    json_err('El archivo supera ' . MAX_UPLOAD_MB . 'MB');
}

$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, ALLOWED_MIME)) {
    json_err('Tipo de archivo no permitido: ' . $mime);
}

$info = @getimagesize($file['tmp_name']);
if (!$info) json_err('El archivo no es una imagen válida');

// Generar token único
$token   = bin2hex(random_bytes(16));
$tempDir = UPLOADS_PATH . '/temp/' . $token;
mkdir($tempDir, 0755, true);

$origPath = $tempDir . '/original.jpg';
if (!move_uploaded_file($file['tmp_name'], $origPath)) {
    rmdir($tempDir);
    json_err('No se pudo guardar el archivo temporal');
}

// Generar thumb para preview (en directorio temp)
$thumbPath = null;
$thumbUrl  = null;
if (extension_loaded('gd')) {
    $src = image_create_from_path($origPath, (int)$info[2]);
    if ($src) {
        $src = image_fix_orientation($src, $origPath);
        $src = image_flatten_to_white($src);
        $thumb = image_fit_square($src, imagesx($src), imagesy($src), IMG_THUMB_SIZE);
        $thumbPath = $tempDir . '/thumb.jpg';
        imagejpeg($thumb, $thumbPath, IMG_QUALITY);
        imagedestroy($thumb);
        imagedestroy($src);
        $thumbUrl = BASE_URL . '/uploads/temp/' . $token . '/thumb.jpg';
    }
}

// Guardar en BD
$stmt = db()->prepare("INSERT INTO upload_temp (token, session_id, path_orig, path_thumb) VALUES (?,?,?,?)");
$stmt->execute([$token, session_id(), $origPath, $thumbPath]);

// Nombre sugerido desde el archivo original (sin extensión)
$suggested = pathinfo($file['name'], PATHINFO_FILENAME);
$suggested = preg_replace('/[_\-]+/', ' ', $suggested);
$suggested = ucfirst(strtolower(trim(preg_replace('/\s+/', ' ', $suggested))));

json_ok([
    'token'     => $token,
    'thumb_url' => $thumbUrl ?? null,
    'suggested' => $suggested,
    'size'      => format_bytes($file['size']),
]);
