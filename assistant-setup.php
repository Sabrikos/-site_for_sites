<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/bd.php';
$pdo->exec('DELETE FROM assistant_limits WHERE expires_at < NOW()');
echo "Assistant cleanup complete. Apply schema changes with php migrate.php.\n";
