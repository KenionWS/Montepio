<?php
declare(strict_types=1);

// ─── Texto ────────────────────────────────────────────────────────────────────
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slug(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $map  = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u'];
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return $text;
}

function unique_slug(string $base, int $excludeId = 0, string $table = 'products'): string
{
    $s    = slug($base);
    $orig = $s;
    $i    = 1;
    while (true) {
        $allowedTables = ['products', 'categories'];
        if (!in_array($table, $allowedTables, true)) {
            $table = 'products';
        }

        $row = db()->prepare("SELECT id FROM $table WHERE slug = ? AND id != ?");
        $row->execute([$s, $excludeId]);
        if (!$row->fetch()) break;
        $s = $orig . '-' . $i++;
    }
    return $s;
}

function sku_generate(): string
{
    $last = db()->query("SELECT sku FROM products ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }
    return 'MON-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

// ─── HTTP / JSON ──────────────────────────────────────────────────────────────
function json_ok(array $data = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data);
    exit;
}

function json_err(string $msg, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ─── Flash messages ───────────────────────────────────────────────────────────
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ─── Archivos ─────────────────────────────────────────────────────────────────
function rmdir_recursive(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $dir . '/' . $f;
        is_dir($path) ? rmdir_recursive($path) : unlink($path);
    }
    rmdir($dir);
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
