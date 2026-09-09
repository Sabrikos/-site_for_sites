<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/app.php';

$sent = appSendTelegram(
    "Тестовое уведомление WebStart Studio\nЕсли вы видите это сообщение, Telegram-уведомления настроены.",
    'Открыть сайт',
    appUrl('/')
);

if (!$sent) {
    fwrite(STDERR, "Тестовое уведомление не отправлено. Проверьте TELEGRAM_BOT_TOKEN и TELEGRAM_ADMIN_CHAT_IDS в .env.\n");
    exit(1);
}

echo "Тестовое уведомление отправлено.\n";