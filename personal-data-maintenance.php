<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/services/PersonalDataService.php';

$result = PersonalDataService::eraseExpired($pdo);
echo 'Erased expired personal data: orders=' . $result['orders']
    . ', conversations=' . $result['conversations']
    . ', telegram_profiles=' . $result['telegram_profiles'] . ".\n";
