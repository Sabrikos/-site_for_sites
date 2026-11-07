<?php
declare(strict_types=1);

// Kept as inert entry points for existing launchers and integrations.
function tgHandleUpdate(PDO $pdo, array $update): bool
{
    return false;
}

function tgRun(PDO $pdo): int
{
    return 0;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    if (PHP_SAPI !== 'cli') http_response_code(410);
    echo "Telegram polling is disabled. Website notifications remain available.\n";
}
