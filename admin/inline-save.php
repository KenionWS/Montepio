<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
auth_require();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Metodo no permitido', 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$id = (int)($body['id'] ?? 0);
$field = $body['field'] ?? '';
$value = $body['value'] ?? '';
$categoryId = (int)($body['category_id'] ?? 0);
$productIds = array_values(array_filter(array_map('intval', (array)($body['product_ids'] ?? []))));

if ($field !== 'category_reorder' && !$id) {
    json_err('ID invalido');
}
if (!in_array($field, ['title', 'price', 'status', 'category_position', 'category_reorder'], true)) {
    json_err('Campo no permitido');
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
    json_err('CSRF invalido', 403);
}

$db = db();

if ($field !== 'category_reorder') {
    $check = $db->prepare("SELECT id FROM products WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        json_err('Producto no encontrado', 404);
    }
}

$now = date('Y-m-d H:i:s');

switch ($field) {
    case 'title':
        $value = trim((string)$value);
        if ($value === '') {
            json_err('El nombre no puede estar vacio');
        }
        $db->prepare("UPDATE products SET title = ?, updated_at = ? WHERE id = ?")->execute([$value, $now, $id]);
        break;

    case 'price':
        $price = strlen(trim((string)$value)) ? (float)$value : null;
        $db->prepare("UPDATE products SET price = ?, price_visible = ?, updated_at = ? WHERE id = ?")
            ->execute([$price, $price !== null ? 1 : 0, $now, $id]);
        break;

    case 'status':
        if (!in_array($value, ['activo', 'reservado', 'vendido'], true)) {
            json_err('Estado invalido');
        }
        $db->prepare("UPDATE products SET status = ?, updated_at = ? WHERE id = ?")->execute([$value, $now, $id]);
        break;

    case 'category_position':
        if ($categoryId <= 0) {
            json_err('Categoria invalida');
        }
        $position = max(0, (int)$value);
        $stmt = $db->prepare("UPDATE product_categories SET position = ? WHERE product_id = ? AND category_id = ?");
        $stmt->execute([$position, $id, $categoryId]);
        if ($stmt->rowCount() === 0) {
            json_err('Relacion producto-categoria no encontrada', 404);
        }
        $value = $position;
        break;

    case 'category_reorder':
        if ($categoryId <= 0) {
            json_err('Categoria invalida');
        }
        if ($productIds === []) {
            json_err('No llegaron productos para reordenar');
        }

        $db->beginTransaction();
        try {
            $relationStmt = $db->prepare("SELECT COUNT(*) FROM product_categories WHERE product_id = ? AND category_id = ?");
            $updateStmt = $db->prepare("UPDATE product_categories SET position = ? WHERE product_id = ? AND category_id = ?");

            foreach ($productIds as $index => $productId) {
                $relationStmt->execute([$productId, $categoryId]);
                if ((int)$relationStmt->fetchColumn() === 0) {
                    throw new RuntimeException('Uno de los productos ya no pertenece a la categoria.');
                }
                $updateStmt->execute([$index, $productId, $categoryId]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            json_err($e->getMessage(), 422);
        }

        $value = $productIds;
        break;
}

json_ok(['id' => $id, 'field' => $field, 'value' => $value]);
