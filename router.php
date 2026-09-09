<?php

declare(strict_types=1);

// Only public pages and assets are served by the local PHP server.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = str_replace('\\', '/', $path);
$publicPages = ['index.php', 'home.php', 'tariffs.php', 'cart.php', 'create-order.php', 'privacy.php', 'telegram-webhook.php'];
$blocked = preg_match('~(?:^|/)[.]|\x00|^/(?:storage|services|migrations|tests|docs)(?:/|$)~i', $path);
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($path !== '/' && $extension === 'php') {
    $blocked = $blocked || !(in_array(ltrim($path, '/'), $publicPages, true) ||
        preg_match('~^/(?:api/chat|admin/(?:index|login|logout|orders|order|chats|chat-api|services|tariffs|faq))\.php$~', $path));
} elseif ($path !== '/') {
    $blocked = $blocked || !in_array($extension, ['css', 'js', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico', 'svg', 'woff', 'woff2'], true);
}
if ($blocked) {
    http_response_code(404);
    exit('Not found');
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
return false;
