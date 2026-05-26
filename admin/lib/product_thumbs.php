<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/image.php';

function regenerate_product_thumbs(?int $productIdFilter = null): array
{
    $summary = product_thumb_counts($productIdFilter);
    $aggregate = [
        'total' => $summary['total'],
        'processed' => 0,
        'failed' => 0,
        'logs' => [],
    ];

    $afterId = 0;
    do {
        $batch = regenerate_product_thumbs_batch($productIdFilter, $afterId, 100);
        $aggregate['processed'] += $batch['processed'];
        $aggregate['failed'] += $batch['failed'];
        $aggregate['logs'] = array_merge($aggregate['logs'], $batch['logs']);
        $afterId = (int)$batch['last_id'];
    } while (!empty($batch['has_more']));

    return $aggregate;
}

function regenerate_product_thumbs_batch(?int $productIdFilter = null, int $afterId = 0, int $limit = 50): array
{
    $db = db();
    $summary = product_thumb_counts($productIdFilter);
    $limit = max(1, min(500, $limit));
    $sql = '
        SELECT id, product_id, hash, path_thumb, path_medium, path_full, position
        FROM product_images
        WHERE id > ?
    ';
    $params = [$afterId];

    if ($productIdFilter !== null && $productIdFilter > 0) {
        $sql .= ' AND product_id = ?';
        $params[] = $productIdFilter;
    }

    $sql .= ' ORDER BY id ASC LIMIT ?';
    $params[] = $limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [
        'total' => $summary['total'],
        'remaining_before' => product_thumb_remaining_count($productIdFilter, $afterId),
        'batch_size' => $limit,
        'after_id' => $afterId,
        'last_id' => $afterId,
        'has_more' => false,
        'processed' => 0,
        'failed' => 0,
        'logs' => [],
    ];

    if (!$rows) {
        return $result;
    }

    foreach ($rows as $row) {
        $result['last_id'] = (int)$row['id'];

        $sourcePath = product_thumb_source_path($row);
        if ($sourcePath === null) {
            $result['failed']++;
            $result['logs'][] = [
                'type' => 'error',
                'message' => 'Imagen ' . $row['id'] . ' (producto ' . $row['product_id'] . '): no encontre archivo fuente.',
            ];
            continue;
        }

        $thumbRelative = trim((string)($row['path_thumb'] ?? ''));
        if ($thumbRelative === '') {
            $thumbRelative = 'uploads/products/' . (int)$row['product_id'] . '/' . $row['hash'] . '_thumb.jpg';
        }

        $thumbAbsolute = ROOT_PATH . '/' . ltrim($thumbRelative, '/');
        $thumbDir = dirname($thumbAbsolute);
        if (!is_dir($thumbDir) && !mkdir($thumbDir, 0755, true) && !is_dir($thumbDir)) {
            $result['failed']++;
            $result['logs'][] = [
                'type' => 'error',
                'message' => 'Imagen ' . $row['id'] . ' (producto ' . $row['product_id'] . '): no pude crear la carpeta de destino.',
            ];
            continue;
        }

        $info = @getimagesize($sourcePath);
        if (!$info) {
            $result['failed']++;
            $result['logs'][] = [
                'type' => 'error',
                'message' => 'Imagen ' . $row['id'] . ' (producto ' . $row['product_id'] . '): la fuente no es valida.',
            ];
            continue;
        }

        $src = image_create_from_path($sourcePath, (int)$info[2]);
        if (!$src) {
            $result['failed']++;
            $result['logs'][] = [
                'type' => 'error',
                'message' => 'Imagen ' . $row['id'] . ' (producto ' . $row['product_id'] . '): formato no soportado.',
            ];
            continue;
        }

        $src = image_fix_orientation($src, $sourcePath);
        $src = image_flatten_to_white($src);
        $thumb = image_fit_square($src, imagesx($src), imagesy($src), IMG_THUMB_SIZE);
        $written = imagejpeg($thumb, $thumbAbsolute, IMG_QUALITY);

        imagedestroy($thumb);
        imagedestroy($src);

        if (!$written) {
            $result['failed']++;
            $result['logs'][] = [
                'type' => 'error',
                'message' => 'Imagen ' . $row['id'] . ' (producto ' . $row['product_id'] . '): no pude escribir la thumb.',
            ];
            continue;
        }

        if ($thumbRelative !== (string)($row['path_thumb'] ?? '')) {
            $update = $db->prepare('UPDATE product_images SET path_thumb = ? WHERE id = ?');
            $update->execute([$thumbRelative, (int)$row['id']]);
        }

        $result['processed']++;
        $result['logs'][] = [
            'type' => 'ok',
            'message' => 'Imagen ' . $row['id'] . ' (producto ' . $row['product_id'] . '): thumb regenerada.',
        ];
    }

    $result['has_more'] = product_thumb_remaining_count($productIdFilter, (int)$result['last_id']) > 0;
    return $result;
}

function product_thumb_counts(?int $productIdFilter = null): array
{
    $db = db();
    $sql = 'SELECT COUNT(*) FROM product_images';
    $params = [];

    if ($productIdFilter !== null && $productIdFilter > 0) {
        $sql .= ' WHERE product_id = ?';
        $params[] = $productIdFilter;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return [
        'total' => (int)$stmt->fetchColumn(),
    ];
}

function product_thumb_remaining_count(?int $productIdFilter = null, int $afterId = 0): int
{
    $db = db();
    $sql = 'SELECT COUNT(*) FROM product_images WHERE id > ?';
    $params = [$afterId];

    if ($productIdFilter !== null && $productIdFilter > 0) {
        $sql .= ' AND product_id = ?';
        $params[] = $productIdFilter;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function product_thumb_source_path(array $row): ?string
{
    $sourceCandidates = [
        $row['path_full'] ?? '',
        $row['path_medium'] ?? '',
        $row['path_thumb'] ?? '',
    ];

    foreach ($sourceCandidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') {
            continue;
        }

        $absolute = ROOT_PATH . '/' . ltrim($candidate, '/');
        if (is_file($absolute)) {
            return $absolute;
        }
    }

    return null;
}
