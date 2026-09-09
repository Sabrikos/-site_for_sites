<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit;
}

$secret = appEnv('TELEGRAM_WEBHOOK_SECRET');
if ($secret === '') {
	http_response_code(503);
	exit;
}
if (!hash_equals($secret, (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
	http_response_code(403);
	exit;
}

require_once __DIR__ . '/services/TelegramBot.php';
$payload = json_decode((string) file_get_contents('php://input'), true);
if (is_array($payload)) {
	try {
		(new TelegramBot($pdo, new TelegramService()))->handle($payload);
	} catch (Throwable $error) {
		appLog('Telegram webhook failed', ['error' => $error->getMessage()]);
		http_response_code(500);
	}
}
http_response_code(200);
echo 'ok';
