<?php
declare(strict_types=1);
require_once __DIR__ . '/../admin/lib/config.php';
require_once __DIR__ . '/../admin/lib/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

// ─── Parámetros ───────────────────────────────────────────────────────────────
$category = trim($_GET['category'] ?? '');          // slug de categoría
$type     = $_GET['type']     ?? '';                // venta | alquiler
$status   = $_GET['status']   ?? 'activo';          // activo | reservado | vendido | all
$featured = isset($_GET['featured']) ? 1 : null;
$q        = trim($_GET['q']   ?? '');               // búsqueda libre
$page     = max(1, (int)($_GET['page']     ?? 1));
$perPage  = min(48, max(1, (int)($_GET['per_page'] ?? 24)));

// ─── Query builder ────────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($status !== 'all') {
    $where[]  = 'p.status = ?';
    $params[] = in_array($status, ['activo','reservado','vendido']) ? $status : 'activo';
}
if ($type && in_array($type, ['venta','alquiler'])) {
    $where[]  = 'p.type = ?';
    $params[] = $type;
}
if ($category) {
    $where[]  = 'c.slug = ?';
    $params[] = $category;
}
if ($featured !== null) {
    $where[]  = 'p.is_featured = 1';
}
if ($q) {
    $where[]  = '(p.title LIKE ? OR p.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $db = db();

    // Total
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id $whereStr");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    // Productos
    $stmt = $db->prepare("
        SELECT
            p.id, p.sku, p.title, p.slug, p.description,
            p.type, p.price, p.price_visible, p.status, p.is_featured,
            p.style, p.era, p.material, p.origin, p.dimensions, p.condition_val,
            p.created_at,
            c.id   AS cat_id,
            c.name AS cat_name,
            c.slug AS cat_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        $whereStr
        ORDER BY p.is_featured DESC, p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([...$params, $perPage, $offset]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Imágenes de los productos en un solo query
    if ($products) {
        $ids = array_column($products, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $imgStmt = $db->prepare("
            SELECT product_id, path_thumb, path_medium, path_full, is_cover, position
            FROM product_images
            WHERE product_id IN ($placeholders)
            ORDER BY is_cover DESC, position ASC
        ");
        $imgStmt->execute($ids);
        $allImgs = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar imágenes por producto
        $imgsByProduct = [];
        foreach ($allImgs as $img) {
            $imgsByProduct[$img['product_id']][] = $img;
        }

        // Armar respuesta final
        $data = array_map(function($p) use ($imgsByProduct) {
            $imgs  = $imgsByProduct[$p['id']] ?? [];
            $cover = $imgs[0] ?? null; // primer resultado = portada (por ORDER BY)
            return [
                'id'            => (int)$p['id'],
                'sku'           => $p['sku'],
                'title'         => $p['title'],
                'slug'          => $p['slug'],
                'description'   => $p['description'],
                'type'          => $p['type'],
                'price'         => $p['price_visible'] ? (float)$p['price'] : null,
                'price_visible' => (bool)$p['price_visible'],
                'status'        => $p['status'],
                'is_featured'   => (bool)$p['is_featured'],
                'style'         => $p['style'],
                'era'           => $p['era'],
                'material'      => $p['material'],
                'origin'        => $p['origin'],
                'dimensions'    => $p['dimensions'],
                'condition'     => $p['condition_val'],
                'category'      => $p['cat_id'] ? [
                    'id'   => (int)$p['cat_id'],
                    'name' => $p['cat_name'],
                    'slug' => $p['cat_slug'],
                ] : null,
                'cover' => $cover ? [
                    'thumb'  => BASE_URL . '/' . $cover['path_thumb'],
                    'medium' => BASE_URL . '/' . $cover['path_medium'],
                    'full'   => BASE_URL . '/' . $cover['path_full'],
                ] : null,
                'images' => array_map(fn($i) => [
                    'thumb'  => BASE_URL . '/' . $i['path_thumb'],
                    'medium' => BASE_URL . '/' . $i['path_medium'],
                    'full'   => BASE_URL . '/' . $i['path_full'],
                ], $imgs),
                'created_at' => $p['created_at'],
            ];
        }, $products);
    } else {
        $data = [];
    }

    echo json_encode([
        'data' => $data,
        'meta' => [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
