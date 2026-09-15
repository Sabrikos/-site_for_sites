<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once __DIR__ . '/../telegram-poll.php';

putenv('TELEGRAM_BOT_TOKEN=');
putenv('TELEGRAM_ADMIN_CHAT_IDS=');
putenv('TELEGRAM_SUPPORT_CHAT_ID=');
tgEnsureTables($pdo);

$chatId = random_int(2000000000, 2099999999);
$conversationId = null;
$checks = 0;
function handoffCheck(bool $condition, string $label): void
{
	global $checks;
	if (!$condition) throw new RuntimeException('FAIL: ' . $label);
	$checks++;
	echo 'PASS: ' . $label . PHP_EOL;
}

try {
	$conversation = tgConversation($pdo, $chatId);
	$conversationId = (int) $conversation['id'];
	tgAskHuman($chatId, ['id' => $chatId, 'first_name' => 'Test'], 'Позови специалиста');
	$statement = $pdo->prepare('SELECT status FROM chat_conversations WHERE id = ?');
	$statement->execute([$conversationId]);
	handoffCheck($statement->fetchColumn() === 'waiting_human', 'handoff sets waiting_human');

	$chat = new ChatService($pdo);
	$pending = $chat->beginMessage($conversationId, 'Ещё хочу уточнить сроки.');
	handoffCheck($pending['key'] === null && $pending['handoff'] === false, 'waiting_human does not start AI');
	$statement = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE conversation_id = ? AND sender = 'user'");
	$statement->execute([$conversationId]);
	handoffCheck((int) $statement->fetchColumn() >= 2, 'client messages share conversation history');

	$chat->telegramAdminAction($conversationId, 'reply', 'Здравствуйте, подключился специалист.');
	$statement = $pdo->prepare('SELECT status, COUNT(*) FROM chat_conversations c JOIN chat_messages m ON m.conversation_id = c.id WHERE c.id = ? AND m.sender = \'admin\' GROUP BY status');
	$statement->execute([$conversationId]);
	$row = $statement->fetch();
	handoffCheck($row['status'] === 'human' && (int) $row['COUNT(*)'] === 1, 'admin reply sets human and enters shared history');

	$chat->telegramAdminAction($conversationId, 'status', 'bot');
	$statement = $pdo->prepare('SELECT status FROM chat_conversations WHERE id = ?');
	$statement->execute([$conversationId]);
	handoffCheck($statement->fetchColumn() === 'bot', 'admin can return conversation to bot');

	$chat->telegramAdminAction($conversationId, 'status', 'closed');
	$statement->execute([$conversationId]);
	handoffCheck($statement->fetchColumn() === 'closed', 'admin can close conversation');
	echo "Total: {$checks} handoff checks passed. No Telegram API requests sent.\n";
} finally {
	if ($conversationId !== null) {
		$pdo->prepare('DELETE FROM chat_conversations WHERE id = ?')->execute([$conversationId]);
	}
	$pdo->prepare('DELETE FROM telegram_sessions WHERE chat_id = ?')->execute([$chatId]);
}
