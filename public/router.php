<?php

declare(strict_types=1);

/**
 * Router for PHP’s built-in server: existing files → serve as-is; else → index.php
 * php -S 127.0.0.1:8089 -t public public/router.php
 */
$uriPath = '/';
if (isset($_SERVER['REQUEST_URI'])) {
    $parsed = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (\is_string($parsed) && $parsed !== '') {
        $uriPath = $parsed;
    }
}

$filepath = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim(rawurldecode($uriPath), '/'));
$publicDir = realpath(__DIR__);
if ($filepath !== false && $publicDir !== false
    && str_starts_with($filepath, $publicDir . DIRECTORY_SEPARATOR)
    && is_file($filepath)
) {
    return false;
}

require __DIR__ . '/index.php';
