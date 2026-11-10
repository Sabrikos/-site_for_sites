<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/services/PersonalDataService.php';
require_once __DIR__ . '/services/AnalyticsConsentService.php';

$result = PersonalDataService::eraseExpired($pdo);
$analyticsEvents = (new AnalyticsConsentService($pdo))->eraseExpired();
echo 'Erased expired personal data: orders=' . $result['orders']
    . ', conversations=' . $result['conversations']
    . ', telegram_profiles=' . $result['telegram_profiles']
    . ', request_consent_evidence=' . $result['consent_evidence']
    . ', analytics_consents=' . $analyticsEvents . ".\n";
