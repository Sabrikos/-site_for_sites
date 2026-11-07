<?php

declare(strict_types=1);

// Only public pages and assets are served by the local PHP server.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = str_replace('\\', '/', $path);
if ($path === '/admin' || $path === '/admin/') {
    header('Location: /admin/index.php');
    return true;
}

$publicPages = ['404.php', 'index.php', 'home.php', 'tariffs.php', 'cart.php', 'create-order.php', 'privacy.php', 'telegram-webhook.php'];
$blocked = preg_match('~(?:^|/)[.]|\x00|^/(?:storage|services|migrations|tests|docs)(?:/|$)~i', $path);
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($path !== '/' && $extension === 'php') {
    $blocked = $blocked || !(in_array(ltrim($path, '/'), $publicPages, true) ||
        preg_match('~^/(?:api/(?:chat|analytics-consent)|admin/(?:index|login|logout|orders|order|chats|chat-api|services|tariffs|faq))\.php$~', $path));
} elseif ($path !== '/') {
    $portfolioPage = preg_match('~^/portfolio/(?:apex-detailing|ramen-404)/(?:index|404)\.html$~', $path)
        || preg_match('~^/portfolio/tiho/(?:index|favorites)\.html$~', $path);
    $blocked = $blocked || (!$portfolioPage && !in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico', 'svg', 'woff', 'woff2'], true));
}
if ($blocked) {
    require __DIR__ . '/404.php';
    return true;
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
$file = realpath(__DIR__ . $path);
$root = realpath(__DIR__) . DIRECTORY_SEPARATOR;
if ($file !== false && strncmp($file, $root, strlen($root)) === 0 && is_file($file)) {
    return false;
}
require __DIR__ . '/404.php';
return true;
