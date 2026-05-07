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
    $src = match($info[2]) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($origPath),
        IMAGETYPE_PNG  => imagecreatefrompng($origPath),
        IMAGETYPE_GIF  => imagecreatefromgif($origPath),
        IMAGETYPE_WEBP => imagecreatefromwebp($origPath),
        default => false,
    };
    if ($src) {
        // Fix EXIF orientation
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($origPath);
            if ($exif && !empty($exif['Orientation'])) {
                $rotated = match((int)$exif['Orientation']) {
                    3 => imagerotate($src, 180, 0),
                    6 => imagerotate($src, -90, 0),
                    8 => imagerotate($src, 90, 0),
                    default => $src,
                };
                if ($rotated !== $src) { imagedestroy($src); $src = $rotated; }
            }
        }
        $w = imagesx($src); $h = imagesy($src);
        $size = 300;
        if ($w > $h) { $sW=$h; $sX=(int)(($w-$h)/2); $sY=0; }
        else         { $sW=$w; $sX=0; $sY=(int)(($h-$w)/2); }
        $thumb = imagecreatetruecolor($size, $size);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255,255,255));
        imagecopyresampled($thumb, $src, 0, 0, $sX, $sY, $size, $size, $sW, $sW);
        $thumbPath = $tempDir . '/thumb.jpg';
        imagejpeg($thumb, $thumbPath, 85);
        imagedestroy($thumb);
        imagedestroy($src);
        $thumbUrl = '/Montepio/uploads/temp/' . $token . '/thumb.jpg';
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
