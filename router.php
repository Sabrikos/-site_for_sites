<?php
// Router for the PHP development server. Existing resources keep native handling.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$root = realpath(__DIR__);
$file = strpos($path, "\0") === false ? realpath(__DIR__ . $path) : false;
$insideRoot = $file !== false && strncmp($file, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) === 0;
if ($path === '/' || ($insideRoot && is_file($file))) {
    return false;
}
if ($insideRoot && is_dir($file) && (is_file($file . '/index.php') || is_file($file . '/index.html'))) {
    return false;
}
require __DIR__ . '/404.php';
