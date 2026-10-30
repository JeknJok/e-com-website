<?php
// Router for PHP's local development server. Apache uses .htaccess instead.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$localPath = dirname(__DIR__) . '/config.local.php';
$routerConfig = is_file($localPath) ? require $localPath : [];
$baseSetting = getenv('APP_BASE_PATH');
$base = rtrim($baseSetting !== false ? $baseSetting : ($routerConfig['APP_BASE_PATH'] ?? ''), '/');
if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
    $path = substr($path, strlen($base));
} elseif ($base !== '') {
    http_response_code(404); exit;
}
if (preg_match('#(^|/)(\.|app(?:/|$)|database(?:/|$)|scripts(?:/|$)|tests(?:/|$)|config\.)#', $path) || str_contains($path, "\0")) {
    http_response_code(404); exit;
}
$root = dirname(__DIR__);
if ($path === '/admin/shop_admin.php') { $path = '/admin/Shop_admin.php'; }
$file = realpath($root . ($path === '/' || $path === '' ? '/index.php' : $path));
if (!$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404); echo 'Not found'; exit;
}
// Legacy uploads must never execute, even if a previous version accepted PHP files.
if (str_starts_with($file, $root . '/assets/uploads/') && preg_match('/\.(?:php[0-9]*|phtml|phar)$/i', $file)) {
    http_response_code(404); exit;
}
if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
} else {
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'html' => 'text/html'];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    readfile($file);
}
