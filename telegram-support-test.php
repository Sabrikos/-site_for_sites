<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require_once __DIR__ . '/app.php';

$supportChatId = trim((string) appEnv('TELEGRAM_SUPPORT_CHAT_ID', ''));
if ($supportChatId === '') {
	fwrite(STDERR, "TELEGRAM_SUPPORT_CHAT_ID is empty.\n");
	exit(1);
}

if (!appTelegramSendMessage($supportChatId, '✅ Vega Studio Support test')) {
	fwrite(STDERR, "Support test failed. Check the group ID, bot membership and permissions.\n");
	exit(1);
}

echo "Support test sent to the configured support group.\n";
