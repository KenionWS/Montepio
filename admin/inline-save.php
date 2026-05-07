<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
auth_require();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('MÃ©todo no permitido', 405);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$id    = (int)($body['id']    ?? 0);
$field = $body['field'] ?? '';
$value = $body['value'] ?? '';

if (!$id) json_err('ID invÃ¡lido');
if (!in_array($field, ['title', 'price', 'status'], true)) json_err('Campo no permitido');

// Verificar CSRF
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) json_err('CSRF invÃ¡lido', 403);

$db = db();

// Verificar que el producto existe
$check = $db->prepare("SELECT id FROM products WHERE id = ?");
$check->execute([$id]);
if (!$check->fetch()) json_err('Producto no encontrado', 404);

$now = date('Y-m-d H:i:s');

switch ($field) {
    case 'title':
        $value = trim((string)$value);
        if (!$value) json_err('El nombre no puede estar vacÃ­o');
        $db->prepare("UPDATE products SET title=?, updated_at=? WHERE id=?")->execute([$value, $now, $id]);
        break;

    case 'price':
        $price = strlen(trim((string)$value)) ? (float)$value : null;
        $db->prepare("UPDATE products SET price=?, price_visible=?, updated_at=? WHERE id=?")->execute([$price, $price !== null ? 1 : 0, $now, $id]);
        break;

    case 'status':
        if (!in_array($value, ['activo', 'reservado', 'vendido'], true)) json_err('Estado invÃ¡lido');
        $db->prepare("UPDATE products SET status=?, updated_at=? WHERE id=?")->execute([$value, $now, $id]);
        break;
}

json_ok(['id' => $id, 'field' => $field, 'value' => $value]);
