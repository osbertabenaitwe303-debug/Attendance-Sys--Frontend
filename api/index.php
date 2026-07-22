<?php
/**
 * Entrypoint Router for Vercel Serverless PHP
 * Routes incoming web requests to the appropriate frontend views and static assets.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Root path handler
if ($uri === '/' || $uri === '' || $uri === '/index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

$targetFile = __DIR__ . '/..' . $uri;

if (file_exists($targetFile) && !is_dir($targetFile)) {
    $ext = pathinfo($targetFile, PATHINFO_EXTENSION);

    if ($ext === 'php') {
        require $targetFile;
        exit;
    }

    // Static asset MIME handling
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon'
    ];

    if (isset($mimeTypes[$ext])) {
        header("Content-Type: " . $mimeTypes[$ext]);
    }
    readfile($targetFile);
    exit;
}

// Fallback to index.php
require __DIR__ . '/../index.php';
?>
