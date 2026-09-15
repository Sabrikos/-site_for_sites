<?php

declare(strict_types=1);

function appLoadEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

appLoadEnv(__DIR__ . '/.env');

function appEnv(string $key, ?string $default = ''): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function appEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function appLog(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $safeContext = [];
    foreach ($context as $key => $value) {
        if (preg_match('/password|token|secret|key|csrf|session/i', (string) $key)) {
            $safeContext[$key] = '[filtered]';
            continue;
        }
        $safeContext[$key] = is_scalar($value) ? $value : '[complex]';
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($safeContext !== []) {
        $line .= ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    @file_put_contents($logDir . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function appStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_start([
        'use_strict_mode' => 1,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function appCsrfToken(string $key): string
{
    appStartSession();
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$key];
}

function appCsrfValid(string $key, ?string $token): bool
{
    appStartSession();
    return isset($_SESSION[$key]) && is_string($token) && hash_equals($_SESSION[$key], $token);
}

function appRotateCsrf(string $key): string
{
    appStartSession();
    $_SESSION[$key] = bin2hex(random_bytes(32));
    return $_SESSION[$key];
}

function appRateLimit(string $scope, int $limit, int $windowSeconds): bool
{
    appStartSession();
    $now = time();
    $bucketKey = 'rate_' . preg_replace('/[^a-z0-9_\-]/i', '_', $scope);
    $bucket = $_SESSION[$bucketKey] ?? [];
    $bucket = array_values(array_filter($bucket, static fn($time) => is_int($time) && $time > $now - $windowSeconds));

    if (count($bucket) >= $limit) {
        $_SESSION[$bucketKey] = $bucket;
        return false;
    }

    $bucket[] = $now;
    $_SESSION[$bucketKey] = $bucket;
    return true;
}

function appPersistentRateLimit(PDO $pdo, string $scope, int $limit, int $windowSeconds): bool
{
    $window = intdiv(time(), $windowSeconds);
    $bucket = hash('sha256', $scope . ':' . $window);
    $pdo->prepare('INSERT IGNORE INTO app_rate_limits (bucket, expires_at) VALUES (?, ?)')
        ->execute([$bucket, date('Y-m-d H:i:s', ($window + 1) * $windowSeconds)]);
    $statement = $pdo->prepare('UPDATE app_rate_limits SET hits = hits + 1 WHERE bucket = ? AND hits < ?');
    $statement->execute([$bucket, $limit]);
    return $statement->rowCount() === 1;
}

function appClientIp(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function appIsHttps(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function appApplySecurityHeaders(): void
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");
    if (appEnv('APP_ENV', 'local') === 'production' && appIsHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function appAdminSessionActive(): bool
{
    appStartSession();
    if (empty($_SESSION['admin_id'])) return false;
    $timeout = max(300, min(28800, (int) appEnv('ADMIN_SESSION_IDLE_SECONDS', '1800')));
    $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
    if ($lastActivity === 0 || $lastActivity < time() - $timeout) {
        $_SESSION = [];
        session_regenerate_id(true);
        return false;
    }
    $_SESSION['admin_last_activity'] = time();
    return true;
}

appApplySecurityHeaders();

function appMoney(int $amount): string
{
    return number_format($amount, 0, '', ' ') . ' ₽';
}

function appUrl(string $path): string
{
    $baseUrl = rtrim((string) appEnv('APP_URL', ''), '/');
    if ($baseUrl === '') {
        return $path;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}


function appTelegramRequest(string $method, array $payload = []): ?array
{
    $token = appEnv('TELEGRAM_BOT_TOKEN');
    if (!$token) {
        appLog('Telegram request skipped: token is empty', ['method' => $method]);
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($payload),
            'timeout' => $method === 'getUpdates' ? 20 : 5,
        ],
    ]);

    $result = @file_get_contents('https://api.telegram.org/bot' . $token . '/' . $method, false, $context);
    if ($result === false) {
        appLog('Telegram request failed', ['method' => $method]);
        return null;
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : null;
}

function appTelegramSendMessage(int|string $chatId, string $text, array $extra = []): bool
{
    $payload = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], $extra);

    $response = appTelegramRequest('sendMessage', $payload);
    return (bool) ($response['ok'] ?? false);
}

function appTelegramSendMessageResult(int|string $chatId, string $text, array $extra = []): ?array
{
    $payload = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], $extra);
    $response = appTelegramRequest('sendMessage', $payload);
    return ($response['ok'] ?? false) && is_array($response['result'] ?? null)
        ? $response['result']
        : null;
}

function appTelegramEditMessage(int|string $chatId, int $messageId, string $text, array $extra = []): bool
{
    $payload = array_merge([
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ], $extra);
    $response = appTelegramRequest('editMessageText', $payload);
    return (bool) ($response['ok'] ?? false);
}

function appTelegramChatAction(int|string $chatId, string $action = 'typing'): bool
{
    $response = appTelegramRequest('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
    return (bool) ($response['ok'] ?? false);
}
function appTelegramAdminChatIds(): array
{
    $raw = appEnv('TELEGRAM_ADMIN_CHAT_IDS', appEnv('TELEGRAM_ADMIN_CHAT_ID', ''));
    if (!$raw) {
        return [];
    }

    $ids = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_filter(array_map('trim', $ids), static fn($id) => $id !== '')));
}

function appTelegramEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function appSendTelegram(string $text, ?string $buttonText = null, ?string $buttonUrl = null): bool
{
    $supportChatId = trim((string) appEnv('TELEGRAM_SUPPORT_CHAT_ID', ''));
    if ($supportChatId === '') {
        appLog('Telegram support notification skipped: support chat id is empty');
        return false;
    }

    $extra = [];
    $buttonHost = strtolower((string) parse_url((string) $buttonUrl, PHP_URL_HOST));
    $publicHost = $buttonHost !== '' && str_contains($buttonHost, '.') &&
        !preg_match('/(?:^|\.)(?:localhost|local|test|invalid)$/', $buttonHost) &&
        (!filter_var($buttonHost, FILTER_VALIDATE_IP) || filter_var($buttonHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
    if ($buttonText && $buttonUrl && $publicHost && parse_url($buttonUrl, PHP_URL_SCHEME) === 'https') {
        $extra['reply_markup'] = json_encode([
            'inline_keyboard' => [[[
                'text' => $buttonText,
                'url' => $buttonUrl,
            ]]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!appTelegramSendMessage($supportChatId, $text, $extra)) {
        appLog('Telegram support notification failed', ['support_chat_id' => $supportChatId]);
        return false;
    }
    return true;
}
