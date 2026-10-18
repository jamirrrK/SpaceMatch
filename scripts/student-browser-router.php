<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath(__DIR__.'/../public');
$file = realpath($public.'/'.rawurldecode($path));
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)
    && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['css', 'js', 'json', 'ico', 'svg', 'ttf', 'woff', 'woff2', 'png', 'jpg', 'jpeg'], true)) {
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'ttf' => 'font/ttf', 'json' => 'application/json'];
    header('Content-Type: '.($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
    readfile($file);
    return;
}
$app = require __DIR__.'/student-browser-bootstrap.php';
$app->handleRequest(Illuminate\Http\Request::capture());
