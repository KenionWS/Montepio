<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/image.php';
auth_require();
csrf_verify();

function upload_error_text(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'supera el tamano maximo permitido';
        case UPLOAD_ERR_PARTIAL:
            return 'no se subio completa';
        case UPLOAD_ERR_NO_FILE:
            return 'no se selecciono ningun archivo';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'falta la carpeta temporal del servidor';
        case UPLOAD_ERR_CANT_WRITE:
            return 'el servidor no pudo guardar el archivo';
        case UPLOAD_ERR_EXTENSION:
            return 'una extension del servidor bloqueo la subida';
        default:
            return 'fallo la subida';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_URL . '/dashboard.php'); exit;
}

// ─── Eliminar imagen individual ───────────────────────────────────────────────
if (($_POST['action'] ?? '') === 'delete_image') {
    $imgId = (int)($_POST['image_id'] ?? 0);
    $prodId = (int)($_POST['product_id'] ?? 0);
    $db = db();
    $img = $db->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?")->execute([$imgId, $prodId]) ? null : null;
    $s = $db->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
    $s->execute([$imgId, $prodId]);
    $img = $s->fetch();
    if ($img) {
        foreach (['path_thumb','path_medium','path_full'] as $k) {
            if ($img[$k]) @unlink(ROOT_PATH . '/' . $img[$k]);
        }
        $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imgId]);
        // Si era portada, hacer portada la primera imagen restante
        if ($img['is_cover']) {
            $first = $db->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY position LIMIT 1");
            $first->execute([$prodId]);
            $firstRow = $first->fetch();
            if ($firstRow) {
                $db->prepare("UPDATE product_images SET is_cover = 1 WHERE id = ?")->execute([$firstRow['id']]);
            }
        }
    }
    flash_set('ok', 'Imagen eliminada.');
    header('Location: ' . ADMIN_URL . '/producto.php?id=' . $prodId); exit;
}

// ─── Guardar producto ─────────────────────────────────────────────────────────
$db  = db();
$id  = (int)($_POST['id'] ?? 0);
$imageWarnings = [];

$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$history     = trim($_POST['history']     ?? '');
$categoryIds = array_filter(array_map('intval', (array)($_POST['category_ids'] ?? [])));
$categoryId  = $categoryIds[0] ?? null; // mantener category_id principal para compatibilidad con API
$type        = in_array($_POST['type'] ?? '', ['venta','alquiler']) ? $_POST['type'] : 'venta';
$rentalOnly  = isset($_POST['rental_only']) ? 1 : 0;
$price       = strlen($_POST['price'] ?? '') ? (float)$_POST['price'] : null;
$priceVis    = isset($_POST['price_visible']) ? 1 : 0;
$status      = in_array($_POST['status'] ?? '', ['activo','reservado','vendido']) ? $_POST['status'] : 'activo';
$isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
$style       = trim($_POST['style']       ?? '');
$era         = trim($_POST['era']         ?? '');
$material    = trim($_POST['material']    ?? '');
$origin      = trim($_POST['origin']      ?? '');
$dimensions  = trim($_POST['dimensions']  ?? '');
$condition   = trim($_POST['condition_val'] ?? '');
$pickupAvailable = isset($_POST['pickup_available']) ? 1 : 0;
$shippingTransport = isset($_POST['shipping_transport']) ? 1 : 0;
$shippingFlete = isset($_POST['shipping_flete']) ? 1 : 0;
$shippingEncomienda = isset($_POST['shipping_encomienda']) ? 1 : 0;

if ($rentalOnly) {
    $type = 'alquiler';
    $price = null;
    $priceVis = 0;
} else {
    $type = 'venta';
}

if (!$title) {
    flash_set('err', 'El nombre es obligatorio.');
    header('Location: ' . ADMIN_URL . '/producto.php' . ($id ? "?id=$id" : '')); exit;
}

$slug = unique_slug($title, $id);

$now = date('Y-m-d H:i:s');

if ($id) {
    // UPDATE
    $stmt = $db->prepare("
        UPDATE products SET
          title=?,slug=?,description=?,history=?,category_id=?,
          type=?,price=?,price_visible=?,status=?,is_featured=?,
          rental_only=?,style=?,era=?,material=?,origin=?,dimensions=?,condition_val=?,
          pickup_available=?,shipping_transport=?,shipping_flete=?,shipping_encomienda=?,
          updated_at=?
        WHERE id=?
    ");
    $stmt->execute([$title,$slug,$description,$history,$categoryId,
                    $type,$price,$priceVis,$status,$isFeatured,
                    $rentalOnly,$style,$era,$material,$origin,$dimensions,$condition,
                    $pickupAvailable,$shippingTransport,$shippingFlete,$shippingEncomienda,
                    $now, $id]);
} else {
    // INSERT
    $stmt = $db->prepare("
        INSERT INTO products
          (title,slug,description,history,category_id,
           type,price,price_visible,status,is_featured,
           rental_only,style,era,material,origin,dimensions,condition_val,
           pickup_available,shipping_transport,shipping_flete,shipping_encomienda,
           created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([$title,$slug,$description,$history,$categoryId,
                    $type,$price,$priceVis,$status,$isFeatured,
                    $rentalOnly,$style,$era,$material,$origin,$dimensions,$condition,
                    $pickupAvailable,$shippingTransport,$shippingFlete,$shippingEncomienda,
                    $now,$now]);
    $id = (int)$db->lastInsertId();
}

// ─── Sincronizar categorías (pivot) ──────────────────────────────────────────
$db->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$id]);
if ($categoryIds) {
    $ins = $db->prepare("INSERT OR IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)");
    foreach ($categoryIds as $cid) {
        $ins->execute([$id, $cid]);
    }
}

// ─── Procesar imágenes subidas ────────────────────────────────────────────────
$files = $_FILES['images'] ?? null;
if (
    $files &&
    is_array($files['tmp_name']) &&
    (
        array_filter($files['tmp_name'], static function ($tmp): bool {
            return is_string($tmp) && $tmp !== '';
        })
        || array_filter((array)($files['error'] ?? []), static function ($error): bool {
            return (int)$error !== UPLOAD_ERR_NO_FILE;
        })
    )
) {
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
    $cntStmt->execute([$id]);
    $existingCount = (int)$cntStmt->fetchColumn();
    $addedCount = 0;

    foreach ($files['tmp_name'] as $i => $tmp) {
        $name = trim((string)($files['name'][$i] ?? 'imagen'));
        $errorCode = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) continue;
        if ($errorCode !== UPLOAD_ERR_OK) {
            $imageWarnings[] = $name . ': ' . upload_error_text($errorCode) . '.';
            continue;
        }

        $mime = mime_content_type($tmp);
        if (!in_array($mime, ALLOWED_MIME, true)) {
            $imageWarnings[] = $name . ': formato no permitido.';
            continue;
        }

        $validationError = null;
        if (!image_validate_upload($tmp, (int)$files['size'][$i], $validationError)) {
            $imageWarnings[] = $name . ': ' . $validationError . '.';
            continue;
        }

        $hash  = substr(sha1_file($tmp), 0, 12);
        $paths = image_process($tmp, $id, $hash);
        if (!$paths) {
            $imageWarnings[] = $name . ': no se pudo optimizar la imagen.';
            continue;
        }

        $isCover = ($existingCount === 0 && $addedCount === 0) ? 1 : 0;
        $pos     = $existingCount + $addedCount;

        $ins = $db->prepare("
            INSERT INTO product_images (product_id,hash,path_thumb,path_medium,path_full,position,is_cover)
            VALUES (?,?,?,?,?,?,?)
        ");
        $ins->execute([$id, $hash, $paths['thumb'], $paths['medium'], $paths['full'], $pos, $isCover]);
        $addedCount++;
    }
}

$successMessage = $id ? 'Producto guardado correctamente.' : 'Producto creado correctamente.';
if ($imageWarnings) {
    $warningSummary = implode(' ', array_slice($imageWarnings, 0, 4));
    if (count($imageWarnings) > 4) {
        $warningSummary .= ' Se omitieron ' . (count($imageWarnings) - 4) . ' imagen(es) mas.';
    }
    flash_set('err', $successMessage . ' Pero algunas imagenes no se procesaron: ' . $warningSummary);
} else {
    flash_set('ok', $successMessage);
}
header('Location: ' . ADMIN_URL . '/producto.php?id=' . $id);
exit;
