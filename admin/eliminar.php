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

$db = db();

function delete_product(PDO $db, int $id): bool
{
    $exists = $db->prepare("SELECT id FROM products WHERE id = ?");
    $exists->execute([$id]);
    if (!$exists->fetchColumn()) {
        return false;
    }

    // Obtener imagenes para borrar archivos
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
    return true;
}

$ids = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
} else {
    $ids = [(int)($_POST['id'] ?? 0)];
}

$ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
if (!$ids) { header('Location: ' . ADMIN_URL . '/dashboard.php'); exit; }

$deleted = 0;
foreach ($ids as $id) {
    if (delete_product($db, $id)) {
        $deleted++;
    }
}

if ($deleted === 1) {
    flash_set('ok', 'Producto eliminado.');
} elseif ($deleted > 1) {
    flash_set('ok', $deleted . ' productos eliminados.');
} else {
    flash_set('err', 'No se encontro ningun producto para eliminar.');
}

$redirect = (string)($_POST['redirect'] ?? '');
if ($redirect === '' || !str_starts_with($redirect, ADMIN_URL . '/')) {
    $redirect = ADMIN_URL . '/dashboard.php';
}
header('Location: ' . $redirect);
exit;
