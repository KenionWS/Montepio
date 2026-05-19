<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Procesa una imagen y genera thumb, medium y full.
 * Devuelve array con rutas relativas (desde ROOT_PATH) o false si falla.
 */
function image_process(string $srcPath, int $productId, string $hash)
{
    if (!extension_loaded('gd')) {
        error_log('GD no está habilitado. Habilitar extension=gd en php.ini');
        return false;
    }

    // Detectar tipo y crear recurso
    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $src = image_create_from_path($srcPath, $info[2]);
    if (!$src) return false;

    // Corregir orientación EXIF (fotos de celular)
    $src = image_fix_orientation($src, $srcPath);
    $src = image_flatten_to_white($src);

    $w = imagesx($src);
    $h = imagesy($src);

    $dir = UPLOADS_PATH . '/products/' . $productId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $paths = [];

    // Thumb: 300×300 crop centrado
    $thumbPath = $dir . '/' . $hash . '_thumb.jpg';
    $thumb = image_crop_square($src, $w, $h, IMG_THUMB_SIZE);
    imagejpeg($thumb, $thumbPath, IMG_QUALITY);
    imagedestroy($thumb);
    $paths['thumb'] = 'uploads/products/' . $productId . '/' . $hash . '_thumb.jpg';

    // Medium: máx 800px lado mayor
    $medPath = $dir . '/' . $hash . '_medium.jpg';
    $med = image_resize_max($src, $w, $h, IMG_MEDIUM_SIZE);
    imagejpeg($med, $medPath, IMG_QUALITY);
    imagedestroy($med);
    $paths['medium'] = 'uploads/products/' . $productId . '/' . $hash . '_medium.jpg';

    // Full: máx 1400px lado mayor
    $fullPath = $dir . '/' . $hash . '_full.jpg';
    $full = image_resize_max($src, $w, $h, IMG_FULL_SIZE);
    imagejpeg($full, $fullPath, IMG_FULL_QUALITY);
    imagedestroy($full);
    $paths['full'] = 'uploads/products/' . $productId . '/' . $hash . '_full.jpg';

    imagedestroy($src);
    return $paths;
}

/**
 * Procesa imagen desde temp hacia directorio final de producto.
 * Devuelve paths array o false.
 */
function image_process_temp(string $tempOrigPath, int $productId)
{
    $hash = substr(sha1_file($tempOrigPath), 0, 12);
    return image_process($tempOrigPath, $productId, $hash);
}

function image_process_category_cover(string $srcPath, int $categoryId)
{
    if (!extension_loaded('gd')) {
        error_log('GD no esta habilitado. Habilitar extension=gd en php.ini');
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $src = image_create_from_path($srcPath, $info[2]);
    if (!$src) return false;

    $src = image_fix_orientation($src, $srcPath);
    $src = image_flatten_to_white($src);

    $w = imagesx($src);
    $h = imagesy($src);

    $dir = UPLOADS_PATH . '/categories/' . $categoryId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $hash = substr(sha1_file($srcPath), 0, 12);
    $cover = image_resize_cover($src, $w, $h, 1600, 560);
    $path = $dir . '/' . $hash . '_cover.jpg';
    imagejpeg($cover, $path, IMG_FULL_QUALITY);

    imagedestroy($cover);
    imagedestroy($src);

    return 'uploads/categories/' . $categoryId . '/' . $hash . '_cover.jpg';
}

function image_process_home_hero(string $srcPath)
{
    if (!extension_loaded('gd')) {
        error_log('GD no esta habilitado. Habilitar extension=gd en php.ini');
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $src = image_create_from_path($srcPath, $info[2]);
    if (!$src) return false;

    $src = image_fix_orientation($src, $srcPath);
    $src = image_flatten_to_white($src);

    $w = imagesx($src);
    $h = imagesy($src);

    $dir = UPLOADS_PATH . '/site';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $hash = substr(sha1_file($srcPath), 0, 12);
    $hero = image_resize_cover($src, $w, $h, 1920, 900);
    $path = $dir . '/' . $hash . '_home_hero.jpg';
    imagejpeg($hero, $path, IMG_FULL_QUALITY);

    imagedestroy($hero);
    imagedestroy($src);

    return 'uploads/site/' . $hash . '_home_hero.jpg';
}

function image_process_home_service(string $srcPath)
{
    if (!extension_loaded('gd')) {
        error_log('GD no esta habilitado. Habilitar extension=gd en php.ini');
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $src = image_create_from_path($srcPath, $info[2]);
    if (!$src) return false;

    $src = image_fix_orientation($src, $srcPath);
    $src = image_flatten_to_white($src);

    $w = imagesx($src);
    $h = imagesy($src);

    $dir = UPLOADS_PATH . '/site';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $hash = substr(sha1_file($srcPath), 0, 12);
    $service = image_resize_cover($src, $w, $h, 1200, 800);
    $path = $dir . '/' . $hash . '_home_service.jpg';
    imagejpeg($service, $path, IMG_FULL_QUALITY);

    imagedestroy($service);
    imagedestroy($src);

    return 'uploads/site/' . $hash . '_home_service.jpg';
}

function image_process_site_popup(string $srcPath)
{
    if (!extension_loaded('gd')) {
        error_log('GD no esta habilitado. Habilitar extension=gd en php.ini');
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $src = image_create_from_path($srcPath, $info[2]);
    if (!$src) return false;

    $src = image_fix_orientation($src, $srcPath);
    $src = image_flatten_to_white($src);

    $w = imagesx($src);
    $h = imagesy($src);

    $dir = UPLOADS_PATH . '/site';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $hash = substr(sha1_file($srcPath), 0, 12);
    $popup = image_resize_cover($src, $w, $h, 1100, 760);
    $path = $dir . '/' . $hash . '_site_popup.jpg';
    imagejpeg($popup, $path, IMG_FULL_QUALITY);

    imagedestroy($popup);
    imagedestroy($src);

    return 'uploads/site/' . $hash . '_site_popup.jpg';
}

function image_process_about_cover(string $srcPath)
{
    if (!extension_loaded('gd')) {
        error_log('GD no esta habilitado. Habilitar extension=gd en php.ini');
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $src = image_create_from_path($srcPath, $info[2]);
    if (!$src) return false;

    $src = image_fix_orientation($src, $srcPath);
    $src = image_flatten_to_white($src);

    $w = imagesx($src);
    $h = imagesy($src);

    $dir = UPLOADS_PATH . '/site';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $hash = substr(sha1_file($srcPath), 0, 12);
    $cover = image_resize_cover($src, $w, $h, 1920, 760);
    $path = $dir . '/' . $hash . '_about_cover.jpg';
    imagejpeg($cover, $path, IMG_FULL_QUALITY);

    imagedestroy($cover);
    imagedestroy($src);

    return 'uploads/site/' . $hash . '_about_cover.jpg';
}

function image_process_about_content(string $srcPath)
{
    if (!extension_loaded('gd')) {
        error_log('GD no esta habilitado. Habilitar extension=gd en php.ini');
        return false;
    }

    $info = @getimagesize($srcPath);
    if (!$info) return false;

    $src = image_create_from_path($srcPath, $info[2]);
    if (!$src) return false;

    $src = image_fix_orientation($src, $srcPath);
    $src = image_flatten_to_white($src);

    $w = imagesx($src);
    $h = imagesy($src);

    $dir = UPLOADS_PATH . '/site/about';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $hash = substr(sha1_file($srcPath), 0, 12);
    $content = image_resize_max($src, $w, $h, 1200);
    $path = $dir . '/' . $hash . '_content.jpg';
    imagejpeg($content, $path, IMG_FULL_QUALITY);

    imagedestroy($content);
    imagedestroy($src);

    return 'uploads/site/about/' . $hash . '_content.jpg';
}

function image_validate_upload(string $path, int $sizeBytes, ?string &$error = null): bool
{
    if ($sizeBytes > MAX_UPLOAD_MB * 1024 * 1024) {
        $error = 'supera el limite de ' . MAX_UPLOAD_MB . ' MB';
        return false;
    }

    $info = @getimagesize($path);
    if (!$info) {
        $error = 'no es una imagen valida';
        return false;
    }

    $width = (int)($info[0] ?? 0);
    $height = (int)($info[1] ?? 0);
    if ($width < 1 || $height < 1) {
        $error = 'tiene dimensiones invalidas';
        return false;
    }

    if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
        $error = 'excede la resolucion maxima de ' . MAX_IMAGE_WIDTH . 'x' . MAX_IMAGE_HEIGHT . ' px';
        return false;
    }

    if (($width * $height) > MAX_IMAGE_PIXELS) {
        $error = 'excede el maximo de ' . number_format(MAX_IMAGE_PIXELS, 0, ',', '.') . ' pixeles';
        return false;
    }

    $channels = (int)($info['channels'] ?? 4);
    $memoryEstimate = (int)ceil($width * $height * max(4, $channels) * 1.8);
    $memoryLimit = image_parse_ini_bytes((string)ini_get('memory_limit'));
    if ($memoryLimit > 0 && $memoryEstimate > ($memoryLimit * 0.7)) {
        $error = 'es demasiado pesada para procesarla de forma segura en el servidor';
        return false;
    }

    return true;
}

// ─── Helpers internos ─────────────────────────────────────────────────────────

function image_create_from_path(string $path, int $type)
{
    switch ($type) {
        case IMAGETYPE_JPEG:
            return imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:
            return imagecreatefrompng($path);
        case IMAGETYPE_GIF:
            return imagecreatefromgif($path);
        case IMAGETYPE_WEBP:
            return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false;
        default:
            return false;
    }
}

function image_fix_orientation($img, string $path)
{
    if (!function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($path);
    if (!$exif || empty($exif['Orientation'])) return $img;

    switch ((int)$exif['Orientation']) {
        case 3:
            $rotated = imagerotate($img, 180, 0);
            break;
        case 6:
            $rotated = imagerotate($img, -90, 0);
            break;
        case 8:
            $rotated = imagerotate($img, 90, 0);
            break;
        default:
            $rotated = $img;
            break;
    }
    if ($rotated !== $img) {
        imagedestroy($img);
        return $rotated;
    }
    return $img;
}

function image_flatten_to_white($src)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagealphablending($dst, true);
    imagesavealpha($dst, false);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    imagedestroy($src);
    return $dst;
}

function image_crop_square($src, int $w, int $h, int $size)
{
    $dst = imagecreatetruecolor($size, $size);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));

    if ($w > $h) {
        $srcW = $h; $srcX = (int)(($w - $h) / 2); $srcY = 0;
    } else {
        $srcH = $w; $srcY = (int)(($h - $w) / 2); $srcX = 0;
        $srcW = $w;
    }
    $srcH = $srcW ?? $srcH;
    $srcW = $srcW ?? $w;

    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $size, $size, $srcW, $srcH);
    return $dst;
}

function image_resize_max($src, int $w, int $h, int $maxSide)
{
    if ($w <= $maxSide && $h <= $maxSide) {
        // No necesita reducirse, clonar
        $dst = imagecreatetruecolor($w, $h);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $w, $h);
        return $dst;
    }

    if ($w >= $h) {
        $nw = $maxSide;
        $nh = (int)round($h * ($maxSide / $w));
    } else {
        $nh = $maxSide;
        $nw = (int)round($w * ($maxSide / $h));
    }

    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

function image_resize_cover($src, int $w, int $h, int $targetW, int $targetH)
{
    $scale = max($targetW / $w, $targetH / $h);
    $srcW = (int)round($targetW / $scale);
    $srcH = (int)round($targetH / $scale);
    $srcX = (int)max(0, floor(($w - $srcW) / 2));
    $srcY = (int)max(0, floor(($h - $srcH) / 2));

    $dst = imagecreatetruecolor($targetW, $targetH);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $targetW, $targetH, $srcW, $srcH);
    return $dst;
}

function image_parse_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return -1;
    }

    $suffix = strtolower(substr($value, -1));
    $bytes = (int)$value;

    switch ($suffix) {
        case 'g':
            return $bytes * 1024 * 1024 * 1024;
        case 'm':
            return $bytes * 1024 * 1024;
        case 'k':
            return $bytes * 1024;
        default:
            return (int)$value;
    }
}
