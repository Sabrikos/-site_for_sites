<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require_once __DIR__ . '/../telegram-poll.php';
putenv('TELEGRAM_BOT_TOKEN=');
tgEnsureTables($pdo);

$adminId = random_int(3000000000, 3099999999);
$clientIds = [random_int(3100000000, 3199999999), random_int(3200000000, 3299999999)];
$conversationIds = [];
$checks = 0;
function relayCheck(bool $condition, string $label): void
{
	global $checks;
	if (!$condition) throw new RuntimeException('FAIL: ' . $label);
	$checks++;
	echo 'PASS: ' . $label . PHP_EOL;
}
try {
	foreach ($clientIds as $clientId) {
		$conversationIds[] = (int) tgConversation($pdo, $clientId)['id'];
	}
	$pdo->prepare('INSERT INTO telegram_assignments (conversation_id, admin_user_id, admin_name) VALUES (?, ?, ?)')->execute([$conversationIds[0], $adminId, 'Test Admin']);
	$statement = $pdo->prepare("SELECT a.conversation_id, s.chat_id AS client_chat_id FROM telegram_assignments a INNER JOIN telegram_sessions s ON s.conversation_id = a.conversation_id INNER JOIN chat_conversations c ON c.id = a.conversation_id WHERE a.admin_user_id = ? AND c.status = 'human'");
	$pdo->prepare("UPDATE chat_conversations SET status = 'human' WHERE id = ?")->execute([$conversationIds[0]]);
	$statement->execute([$adminId]);
	$active = $statement->fetchAll();
	relayCheck(count($active) === 1 && (int) $active[0]['client_chat_id'] === $clientIds[0], 'one assignment resolves one client');
	$pdo->prepare("UPDATE chat_conversations SET status = 'human' WHERE id = ?")->execute([$conversationIds[1]]);
	$pdo->prepare('INSERT INTO telegram_assignments (conversation_id, admin_user_id, admin_name) VALUES (?, ?, ?)')->execute([$conversationIds[1], $adminId, 'Test Admin']);
	$statement->execute([$adminId]);
	relayCheck(count($statement->fetchAll()) === 2, 'multiple assignments require Reply');
	echo "Total: {$checks} relay routing checks passed. No Telegram API requests sent.\n";
} finally {
	foreach ($conversationIds as $id) {
		$pdo->prepare('DELETE FROM chat_conversations WHERE id = ?')->execute([$id]);
	}
	$pdo->prepare('DELETE FROM telegram_assignments WHERE admin_user_id = ?')->execute([$adminId]);
}
