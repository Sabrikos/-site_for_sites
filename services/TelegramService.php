<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';

final class TelegramService
{
	private string $token;
	private array $adminChatIds;

	public function __construct()
	{
		// ============================================================
		// НАСТРОЙКА TELEGRAM
		// Секретные данные не вставлять прямо сюда.
		// Укажите TELEGRAM_BOT_TOKEN и TELEGRAM_ADMIN_CHAT_IDS в .env.
		// ============================================================
		$this->token = appEnv('TELEGRAM_BOT_TOKEN');
		$this->adminChatIds = array_values(array_filter(array_map(
			'trim',
			explode(',', appEnv('TELEGRAM_ADMIN_CHAT_IDS')),
		), static fn(string $id): bool => preg_match('/^-?\d+$/', $id) === 1));
	}

	public function isConfigured(): bool
	{
		return $this->token !== '';
	}

	public function adminChatIds(): array
	{
		return $this->adminChatIds;
	}

	public function sendMessage(string|int $chatId, string $text, ?array $keyboard = null): bool
	{
		$payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
		if ($keyboard !== null) {
			$payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard], JSON_UNESCAPED_UNICODE);
		}
		return $this->request('sendMessage', $payload) !== null;
	}

	public function answerCallback(string $callbackId, string $text = ''): void
	{
		$this->request('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
	}

	public function getUpdates(int $offset = 0): ?array
	{
		return $this->request('getUpdates', ['offset' => $offset, 'timeout' => 20, 'allowed_updates' => ['message', 'callback_query']]);
	}

	private function request(string $method, array $payload): ?array
	{
		if ($this->token === '') {
			appLog('Telegram request skipped: token is empty', ['method' => $method]);
			return null;
		}

		$url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
		$options = ['http' => [
			'method' => 'POST',
			'header' => "Content-Type: application/json\r\n",
			'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
			'timeout' => 12,
			'ignore_errors' => true,
		]];
		$response = @file_get_contents($url, false, stream_context_create($options));
		$decoded = is_string($response) ? json_decode($response, true) : null;
		if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
			appLog('Telegram request failed', ['method' => $method, 'http_code' => $http_response_header[0] ?? 'unknown']);
			return null;
		}
		return $decoded;
	}
}
