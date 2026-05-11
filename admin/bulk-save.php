<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/image.php';
auth_require();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método no permitido', 405);
csrf_verify();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['products']) || !is_array($body['products'])) {
    json_err('Payload inválido');
}

$items   = $body['products'];
$sessId  = session_id();
$db      = db();
$saved   = 0;
$errors  = [];
$now     = date('Y-m-d H:i:s');

$db->beginTransaction();

try {
    foreach ($items as $i => $item) {
        $token = trim($item['token'] ?? '');
        $title = trim($item['title'] ?? '');

        if (!$token || !$title) {
            $errors[] = ['index' => $i, 'reason' => 'Token o nombre vacío'];
            continue;
        }

        // Verificar token pertenece a esta sesión
        $tempStmt = $db->prepare("SELECT * FROM upload_temp WHERE token = ? AND session_id = ?");
        $tempStmt->execute([$token, $sessId]);
        $temp = $tempStmt->fetch();
        if (!$temp) {
            $errors[] = ['index' => $i, 'token' => $token, 'reason' => 'Token no encontrado o expirado'];
            continue;
        }

        if (!file_exists($temp['path_orig'])) {
            $errors[] = ['index' => $i, 'token' => $token, 'reason' => 'Archivo temporal no encontrado'];
            $db->prepare("DELETE FROM upload_temp WHERE token = ?")->execute([$token]);
            continue;
        }

        // Sanitizar campos del producto
        $rentalOnly = !empty($item['rental_only']) ? 1 : 0;
        $type       = $rentalOnly ? 'alquiler' : 'venta';
        $status     = 'activo';
        $categoryIds = [];
        foreach ((array)($item['category_ids'] ?? []) as $categoryIdRaw) {
            $categoryId = (int)$categoryIdRaw;
            if ($categoryId > 0) {
                $categoryIds[] = $categoryId;
            }
        }
        if (!$categoryIds) {
            $fallbackCategoryId = (int)($item['category_id'] ?? 0);
            if ($fallbackCategoryId > 0) {
                $categoryIds[] = $fallbackCategoryId;
            }
        }
        $categoryIds = array_values(array_unique($categoryIds));
        $categoryId = $categoryIds[0] ?? null;
        $price      = $rentalOnly ? null : (strlen((string)($item['price'] ?? '')) ? (float)$item['price'] : null);
        $priceVis   = !$rentalOnly && $price !== null ? 1 : 0;
        $slug       = unique_slug($title);

        // Insertar producto
        $ins = $db->prepare("
            INSERT INTO products
              (title, slug, category_id, type, price, price_visible, status, rental_only, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$title, $slug, $categoryId, $type, $price, $priceVis, $status, $rentalOnly, $now, $now]);
        $productId = (int)$db->lastInsertId();

        if ($categoryIds) {
            $pivot = $db->prepare("INSERT OR IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)");
            foreach ($categoryIds as $cid) {
                $pivot->execute([$productId, $cid]);
            }
        }

        // Mover imagen de temp → uploads/products/{id}/
        $destDir = UPLOADS_PATH . '/products/' . $productId;
        mkdir($destDir, 0755, true);

        $hash   = substr(sha1_file($temp['path_orig']), 0, 12);
        $paths  = image_process($temp['path_orig'], $productId, $hash);

        if (!$paths) {
            // Rollback parcial: borrar el producto recién insertado
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);
            $errors[] = ['index' => $i, 'token' => $token, 'reason' => 'Error al procesar imagen (GD)'];
            continue;
        }

        // Insertar imagen como portada
        $insImg = $db->prepare("
            INSERT INTO product_images (product_id, hash, path_thumb, path_medium, path_full, position, is_cover)
            VALUES (?, ?, ?, ?, ?, 0, 1)
        ");
        $insImg->execute([$productId, $hash, $paths['thumb'], $paths['medium'], $paths['full']]);

        // Limpiar temp
        $db->prepare("DELETE FROM upload_temp WHERE token = ?")->execute([$token]);
        rmdir_recursive(UPLOADS_PATH . '/temp/' . $token);

        $saved++;
    }

    $db->commit();

} catch (Exception $e) {
    $db->rollBack();
    json_err('Error interno: ' . $e->getMessage(), 500);
}

json_ok([
    'saved'  => $saved,
    'errors' => $errors,
    'total'  => count($items),
]);
