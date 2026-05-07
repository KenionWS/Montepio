<?php

if (!empty($_SERVER['REQUEST_URI'])) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $catalogPos = $requestPath ? stripos($requestPath, '/catalogo') : false;
    $basePath = $catalogPos !== false ? substr($requestPath, 0, $catalogPos) : '';
    $rootIndex = rtrim((string)$basePath, '/') . '/index.php';

    $_SERVER['SCRIPT_NAME'] = $rootIndex;
    $_SERVER['PHP_SELF'] = $rootIndex . ($_SERVER['PATH_INFO'] ?? '');
}

if (empty($_SERVER['PATH_INFO']) && !empty($_SERVER['REQUEST_URI'])) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $catalogPos = $requestPath ? stripos($requestPath, '/catalogo') : false;
    if ($requestPath && $catalogPos !== false) {
        $_SERVER['PATH_INFO'] = substr($requestPath, $catalogPos) ?: '/';
    }
}

if (!empty($_SERVER['PATH_INFO']) && strpos($_SERVER['PATH_INFO'], '/catalogo') !== 0) {
    $_SERVER['PATH_INFO'] = '/catalogo' . $_SERVER['PATH_INFO'];
}

if (!empty($_SERVER['PATH_INFO'])) {
    $_SERVER['ORIG_PATH_INFO'] = $_SERVER['PATH_INFO'];
    $_SERVER['PHP_SELF'] = ($_SERVER['SCRIPT_NAME'] ?? '') . $_SERVER['PATH_INFO'];
}

require __DIR__ . '/../public/index.php';
