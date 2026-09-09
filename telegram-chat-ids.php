<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/app.php';

$response = appTelegramRequest('getUpdates', [
    'timeout' => 1,
    'allowed_updates' => json_encode(['message'], JSON_UNESCAPED_UNICODE),
]);

if (!$response || empty($response['ok'])) {
    fwrite(STDERR, "Не удалось получить updates. Проверьте TELEGRAM_BOT_TOKEN в .env и напишите боту /start.\n");
    exit(1);
}

$seen = [];
foreach ($response['result'] ?? [] as $update) {
    $chat = $update['message']['chat'] ?? null;
    if (!is_array($chat) || !isset($chat['id'])) {
        continue;
    }

    $id = (string) $chat['id'];
    if (isset($seen[$id])) {
        continue;
    }
    $seen[$id] = true;

    $title = $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''));
    $type = $chat['type'] ?? 'unknown';
    echo $id . ' | ' . $type . ' | ' . ($title !== '' ? $title : 'без имени') . PHP_EOL;
}

if ($seen === []) {
    echo "Updates есть, но chat_id не найден. Напишите боту /start и запустите скрипт ещё раз.\n";
}
