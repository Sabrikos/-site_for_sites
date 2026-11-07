<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../services/AnalyticsConsentService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function analyticsJson(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        analyticsJson(['error' => 'Метод не поддерживается.'], 405);
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $expectedOrigin = (appIsHttps() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site' || ($origin !== '' && $origin !== $expectedOrigin)) {
        analyticsJson(['error' => 'Недопустимый источник запроса.'], 403);
    }
    appStartSession();
    if (!appRateLimit('analytics_consent', 90, 600)) {
        analyticsJson(['error' => 'Слишком много запросов.'], 429);
    }
    $browser = $_COOKIE['vega_analytics_browser'] ?? '';
    if (!is_string($browser) || !preg_match('/^[a-f0-9]{64}$/D', $browser)) {
        if ($method === 'POST') analyticsJson(['error' => 'Обновите настройки аналитики.'], 403);
        $browser = bin2hex(random_bytes(32));
        setcookie('vega_analytics_browser', $browser, [
            'expires' => strtotime(AnalyticsConsentService::addMonths(gmdate('Y-m-d H:i:s'), AnalyticsConsentService::CHOICE_MONTHS) . ' UTC'),
            'path' => '/', 'secure' => appIsHttps(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    $csrf = appCsrfToken('analytics_consent_csrf');
    $input = null;
    if ($method === 'POST') {
        if (!appCsrfValid('analytics_consent_csrf', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            analyticsJson(['error' => 'Обновите настройки аналитики.'], 403);
        }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024) analyticsJson(['error' => 'Запрос слишком большой.'], 413);
        $raw = file_get_contents('php://input', false, null, 0, 1025);
        if ($raw === false || strlen($raw) > 1024) analyticsJson(['error' => 'Запрос слишком большой.'], 413);
        $input = json_decode($raw, true);
        if (!is_array($input) || !is_string($input['requestId'] ?? null) ||
            !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $input['requestId']) ||
            !in_array($input['choice'] ?? null, ['accepted', 'rejected'], true) ||
            !array_key_exists('expectedReceipt', $input) ||
            ($input['expectedReceipt'] !== null && (!is_string($input['expectedReceipt']) ||
                !preg_match('/^[a-f0-9-]{36}$/D', $input['expectedReceipt'])))) {
            analyticsJson(['error' => 'Некорректный выбор.'], 422);
        }
        if (($input['version'] ?? '') !== AnalyticsConsentService::VERSION) {
            analyticsJson(['error' => 'Текст обновлён. Обновите страницу.'], 409);
        }
    }
    require __DIR__ . '/../bd.php';
    $service = new AnalyticsConsentService($pdo);
    $hash = hash('sha256', $browser);
    $state = $input === null ? $service->state($hash) : $service->record($hash, $input['requestId'], $input['choice'], $input['expectedReceipt']);
    if ($input !== null && $state['expiresAt'] !== null) {
        setcookie('vega_analytics_browser', $browser, [
            'expires' => (int) ($state['expiresAt'] / 1000),
            'path' => '/', 'secure' => appIsHttps(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    analyticsJson($state + [
        'csrf' => $csrf, 'version' => AnalyticsConsentService::VERSION,
        'text' => AnalyticsConsentService::TEXT,
    ]);
} catch (DomainException $error) {
    analyticsJson(['error' => 'Выбор изменился. Обновите настройки аналитики.'], 409);
} catch (Throwable $error) {
    // Never log request bodies, browser tokens or database credentials.
    analyticsJson(['error' => 'Не удалось сохранить настройки аналитики. Попробуйте позже.'], 503);
}
