<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/ChatService.php';

function supportHistory(PDO $pdo, int $conversationId, int $limit = 6): string
{
	$limit = max(1, min(8, $limit));
	$statement = $pdo->prepare('SELECT id, sender, message FROM chat_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT ' . $limit);
	$statement->execute([$conversationId]);
	$rows = array_reverse($statement->fetchAll());
	$seen = [];
	$lines = [];
	foreach ($rows as $row) {
		if (isset($seen[$row['id']])) continue;
		$seen[$row['id']] = true;
		$label = $row['sender'] === 'user' ? '👤 Клиент' : ($row['sender'] === 'admin' ? '👨‍💻 Менеджер' : '🤖 Vega Assistant');
		$lines[] = $label . ":\n<blockquote>" . appTelegramEscape(mb_substr((string) $row['message'], 0, 700, 'UTF-8')) . '</blockquote>';
	}
	return implode("\n\n", $lines);
}

function supportNotify(
	PDO $pdo,
	int $conversationId,
	string $channel,
	?int $clientChatId,
	string $clientName,
	string $username,
	string $lastMessage,
): void {
	$supportId = trim((string) appEnv('TELEGRAM_SUPPORT_CHAT_ID', ''));
	if ($supportId === '') return;
	$source = $channel === 'website' ? '🌐 Сайт' : '📱 Telegram';
	$history = supportHistory($pdo, $conversationId);
	$keyboard = [[['text' => '✅ Завершить', 'callback_data' => 'closeconv:' . $conversationId]]];
	$publicUrl = appUrl('admin/chats.php?id=' . $conversationId);
	$host = strtolower((string) parse_url($publicUrl, PHP_URL_HOST));
	if (parse_url($publicUrl, PHP_URL_SCHEME) === 'https' && $host !== '' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
		$keyboard[0][] = ['text' => '💬 Открыть переписку', 'url' => $publicUrl];
	}
	$text = "⚠️ <b>Нужен специалист</b>\n\nИсточник: " . $source .
		"\n👤 <b>" . appTelegramEscape($clientName !== '' ? $clientName : 'Клиент') . '</b>' .
		"\nTelegram: @" . appTelegramEscape($username !== '' ? $username : 'без username') .
		"\nДиалог: #" . $conversationId . "\n\n<b>Последние сообщения:</b>\n" . ($history ?: '<blockquote>' . appTelegramEscape($lastMessage) . '</blockquote>') .
		"\n\nСтатус: ⏳ Ожидает специалиста";
	$result = appTelegramSendMessageResult($supportId, $text, [
		'reply_markup' => json_encode(['inline_keyboard' => $keyboard], JSON_UNESCAPED_UNICODE),
	]);
	if (is_array($result) && isset($result['message_id'])) {
		$statement = $pdo->prepare('REPLACE INTO telegram_relays (admin_message_id, client_chat_id, admin_chat_id, conversation_id, channel) VALUES (?, ?, ?, ?, ?)');
		$statement->execute([(int) $result['message_id'], $clientChatId, (int) $supportId, $conversationId, $channel]);
	}
}
