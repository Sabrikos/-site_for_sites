<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require_once __DIR__ . '/telegram-poll.php';

$lockPath = __DIR__ . '/storage/telegram-worker.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
	fwrite(STDERR, "Telegram worker уже запущен. Остановите второй экземпляр.\n");
	exit(1);
}

try {
	while (true) {
		tgRun($pdo);
		sleep(2);
	}
} finally {
	flock($lock, LOCK_UN);
	fclose($lock);
}
