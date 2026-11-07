<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';

function websiteNotification(string $kind): array
{
    $chat = $kind === 'chat';
    return [
        'chat_id' => appEnv('TELEGRAM_SUPPORT_CHAT_ID'),
        'text' => $chat ? 'На сайте посетитель ожидает специалиста.' : 'На сайте поступила новая заявка.',
        'reply_markup' => json_encode(['inline_keyboard' => [[[
            'text' => $chat ? 'Открыть чаты' : 'Открыть заявки',
            'url' => $chat ? 'https://vegastud1o.ru/admin/chats.php' : 'https://vegastud1o.ru/admin/orders.php',
        ]]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function websiteHumanNotification(): array
{
    return websiteNotification('chat');
}

function supportSendWebsiteNotification(string $kind): bool
{
    $notification = websiteNotification($kind);
    if (!$notification['chat_id']) return false;
    try {
        return appTelegramSendMessage($notification['chat_id'], $notification['text'], [
            'reply_markup' => $notification['reply_markup'],
        ]);
    } catch (Throwable $error) {
        appLog('Website notification delivery failed', ['kind' => $kind]);
        return false;
    }
}

function supportNotifyWebsiteHumanRequest(): bool
{
    return supportSendWebsiteNotification('chat');
}

function supportNotifyWebsiteOrder(): bool
{
    return supportSendWebsiteNotification('order');
}

// Legacy callers cannot relay visitor data or Telegram replies.
function supportHistory(PDO $pdo, int $conversationId, int $limit = 6): string
{
    return '';
}

function supportNotify(PDO $pdo, int $conversationId, string $channel,
    ?int $clientChatId, string $clientName, string $username, string $lastMessage): void
{
}
