<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Inicia sesión con cookies seguras
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Redirige a setup si no hay contraseña configurada
if (ADMIN_PASS_HASH === '' && basename($_SERVER['PHP_SELF']) !== 'setup.php') {
    header('Location: ' . ADMIN_URL . '/setup.php');
    exit;
}

// ─── Guard: requiere sesión activa ───────────────────────────────────────────
function auth_require(): void
{
    if (empty($_SESSION['admin_ok'])) {
        header('Location: ' . ADMIN_URL . '/index.php');
        exit;
    }
}

// ─── Login / Logout ───────────────────────────────────────────────────────────
function auth_login(string $password): bool
{
    if (ADMIN_PASS_HASH === '') return false;
    if (!password_verify($password, ADMIN_PASS_HASH)) return false;

    session_regenerate_id(true);
    $_SESSION['admin_ok']  = true;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Token CSRF inválido']));
    }
}
