<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/ChatService.php';

$service = new ChatService($pdo);
$key = 'lifecycle-test-' . bin2hex(random_bytes(6));
$old = $service->conversation($key);
$service->telegramAdminAction((int) $old['id'], 'status', 'closed');
$fresh = $service->startFreshConversation($key);
try {
	if ((int) $fresh['id'] === (int) $old['id']) throw new RuntimeException('Fresh conversation reused closed ID');
	if (($fresh['status'] ?? '') !== 'bot') throw new RuntimeException('Fresh conversation is not bot');
	echo "PASS: closed conversation starts a fresh bot conversation\n";
} finally {
	$pdo->prepare('DELETE FROM chat_conversations WHERE id IN (?, ?)')->execute([(int) $old['id'], (int) $fresh['id']]);
}
