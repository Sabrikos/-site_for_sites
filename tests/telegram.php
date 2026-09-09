<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../telegram-poll.php';
putenv('TELEGRAM_BOT_TOKEN=');
putenv('TELEGRAM_ADMIN_CHAT_IDS=');
putenv('TELEGRAM_ADMIN_CHAT_ID=');
tgEnsureTables($pdo);
$chatId = random_int(1000000000, 1999999999);
$orderId = null;
$count = 0;
function tgCheck(bool $condition, string $name): void {
    global $count;
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $count++;
    echo 'PASS: ' . $name . PHP_EOL;
}
try {
    $message = ['chat' => ['id' => $chatId, 'type' => 'private'], 'from' => ['id' => $chatId, 'first_name' => 'Test']];
    foreach (['/start', '/help', '/services', '/tariffs', '/id', '/order'] as $command) {
        tgHandleMessage($pdo, $message + ['text' => $command]);
        tgCheck(true, 'local handler ' . $command . ' (sending disabled)');
    }
    tgCheck(tgSession($pdo, $chatId)['state'] === 'waiting_tariff', 'order starts tariff selection');
    $tariff = tgTariffs($pdo)[0];
    tgHandleMessage($pdo, $message + ['text' => (string) $tariff['id']]);
    tgCheck(tgSession($pdo, $chatId)['state'] === 'waiting_contact', 'tariff selection requests contact');
    tgHandleMessage($pdo, $message + ['contact' => ['user_id' => $chatId + 1, 'phone_number' => '+70000000000']]);
    tgCheck(tgSession($pdo, $chatId)['state'] === 'waiting_contact', 'someone else contact rejected');
    $tariff['price'] = 1;
    $orderId = tgCreateOrder($pdo, $chatId, $message['from'], ['phone_number' => '+70000000000'], $tariff);
    $statement = $pdo->prepare('SELECT o.total, i.price, t.price AS db_price FROM orders o JOIN order_items i ON i.order_id = o.id JOIN tariffs t ON t.id = i.tariff_id WHERE o.id = ?');
    $statement->execute([$orderId]);
    $row = $statement->fetch();
    tgCheck((int) $row['total'] === (int) $row['db_price'] && (int) $row['price'] !== 1, 'Telegram order uses DB price and same order_items');
    tgNotifyAdminNewOrder($orderId, 'Telegram', (int) $row['total']);
    $statement->execute([$orderId]);
    tgCheck((bool) $statement->fetch(), 'notification unavailable does not undo order');
    tgHandleMessage($pdo, $message + ['text' => '/cancel']);
    tgCheck(tgSession($pdo, $chatId)['state'] === null, 'cancel clears order state');
    echo "Total: $count checks passed. No Telegram API requests sent.\n";
} finally {
    if ($orderId) $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    $pdo->prepare('DELETE FROM telegram_sessions WHERE chat_id = ?')->execute([$chatId]);
}
