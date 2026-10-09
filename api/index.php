<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$route = (string) ($_GET['route'] ?? '/');
unset($_GET['route']);
$routePath = parse_url($route, PHP_URL_PATH);
$routePath = is_string($routePath) && $routePath !== '' ? rawurldecode($routePath) : '/';

if (str_contains($routePath, "\0") || str_contains($routePath, '\\') || preg_match('#(?:^|/)\.\.(?:/|$)#', $routePath)) {
    http_response_code(400);
    exit('Bad request.');
}

// Static app assets are served through the dispatcher because all requests
// enter this single PHP function. Never serve PHP, dotfiles, or uploads here.
$staticRelative = ltrim($routePath, '/');
$isAsset = str_starts_with($staticRelative, 'asset/')
    && preg_match('#^asset/[A-Za-z0-9_./-]+\.(?:css|js|png|jpe?g|gif|webp|svg|ico|woff2?)$#i', $staticRelative);
$isPublicImage = $staticRelative === 'public/file.png';
if ($isAsset || $isPublicImage) {
    $assetPath = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $staticRelative));
    $assetRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . ($isAsset ? 'asset' : 'public'));
    if ($assetPath === false || $assetRoot === false || !str_starts_with($assetPath, $assetRoot . DIRECTORY_SEPARATOR) || !is_file($assetPath)) {
        http_response_code(404);
        exit('Not found.');
    }
    $mime = mime_content_type($assetPath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . filesize($assetPath));
    readfile($assetPath);
    exit();
}

if ($routePath === '/') {
    $routePath = '/index.php';
}

if ($routePath === '/index.php') {
    $scriptPath = $projectRoot . DIRECTORY_SEPARATOR . 'index.php';
} elseif (preg_match('#^/(public|admin)/[A-Za-z0-9_-]+\.php$#', $routePath)) {
    $scriptPath = $projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $routePath);
} else {
    http_response_code(404);
    exit('Not found.');
}

$realScript = realpath($scriptPath);
$allowedRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . (str_starts_with($routePath, '/admin/') ? 'admin' : (str_starts_with($routePath, '/public/') ? 'public' : '.')));
if ($realScript === false || $allowedRoot === false || !is_file($realScript) || !str_starts_with($realScript, $allowedRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Not found.');
}

$originalQuery = $_GET;
$_SERVER['REQUEST_URI'] = $routePath . ($originalQuery ? '?' . http_build_query($originalQuery) : '');
$_SERVER['SCRIPT_NAME'] = $routePath;
$_SERVER['PHP_SELF'] = $routePath;
$_SERVER['PATH_INFO'] = '';
chdir(dirname($realScript));
require $realScript;
