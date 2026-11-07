<?php
declare(strict_types=1);

require_once __DIR__ . '/TelegramService.php';

final class TelegramBot
{
    public function __construct(private PDO $pdo, private TelegramService $telegram) {}

    public function handle(array $update): void
    {
        // Telegram is used only for outgoing generic website notifications.
    }
}
