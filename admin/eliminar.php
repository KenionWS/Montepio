<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
auth_require();
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_URL . '/dashboard.php'); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: ' . ADMIN_URL . '/dashboard.php'); exit; }

$db = db();

// Obtener imágenes para borrar archivos
$imgStmt = $db->prepare("SELECT path_thumb, path_medium, path_full FROM product_images WHERE product_id = ?");
$imgStmt->execute([$id]);
$imgs = $imgStmt->fetchAll();

foreach ($imgs as $img) {
    foreach (['path_thumb','path_medium','path_full'] as $k) {
        if ($img[$k]) @unlink(ROOT_PATH . '/' . $img[$k]);
    }
}

// Borrar directorio del producto
$dir = UPLOADS_PATH . '/products/' . $id;
if (is_dir($dir)) rmdir_recursive($dir);

// Borrar de BD (CASCADE borra product_images)
$db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

flash_set('ok', 'Producto eliminado.');
header('Location: ' . ADMIN_URL . '/dashboard.php');
exit;
