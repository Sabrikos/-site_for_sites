<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/bd.php';

function tgEnsureTables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_sessions (
        chat_id BIGINT NOT NULL PRIMARY KEY,
        state VARCHAR(60) NULL,
        selected_tariff_id INT UNSIGNED NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function tgSend(int|string $chatId, string $text, array $extra = []): bool
{
    return appTelegramSendMessage($chatId, $text, $extra);
}

function tgSession(PDO $pdo, int $chatId): array
{
    $statement = $pdo->prepare('SELECT chat_id, state, selected_tariff_id FROM telegram_sessions WHERE chat_id = ? LIMIT 1');
    $statement->execute([$chatId]);
    $row = $statement->fetch();
    if ($row) {
        return $row;
    }

    $pdo->prepare('INSERT INTO telegram_sessions (chat_id, state) VALUES (?, NULL)')->execute([$chatId]);
    return ['chat_id' => $chatId, 'state' => null, 'selected_tariff_id' => null];
}

function tgSetSession(PDO $pdo, int $chatId, ?string $state, ?int $tariffId = null): void
{
    $pdo->prepare(
        'INSERT INTO telegram_sessions (chat_id, state, selected_tariff_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE state = VALUES(state), selected_tariff_id = VALUES(selected_tariff_id)'
    )->execute([$chatId, $state, $tariffId]);
}

function tgServicesText(PDO $pdo): string
{
    $rows = $pdo->query('SELECT name, description FROM services WHERE active = 1 ORDER BY id')->fetchAll();
    if ($rows === []) {
        return 'Список услуг сейчас обновляется.';
    }

    $lines = ['Услуги WebStart Studio:'];
    foreach ($rows as $row) {
        $lines[] = '- ' . $row['name'] . ': ' . $row['description'];
    }
    return implode("\n", $lines);
}

function tgTariffs(PDO $pdo): array
{
    return $pdo->query(
        'SELECT t.id, s.name AS service_name, t.name AS tariff_name, t.price
         FROM tariffs t
         INNER JOIN services s ON s.id = t.service_id
         WHERE t.active = 1 AND s.active = 1
         ORDER BY s.id, t.price'
    )->fetchAll();
}

function tgTariffsText(PDO $pdo): string
{
    $rows = tgTariffs($pdo);
    if ($rows === []) {
        return 'Тарифы сейчас обновляются.';
    }

    $lines = ['Актуальные тарифы:'];
    foreach ($rows as $row) {
        $lines[] = $row['id'] . '. ' . $row['service_name'] . ' - ' . $row['tariff_name'] . ' - ' . appMoney((int) $row['price']);
    }
    return implode("\n", $lines);
}

function tgFindTariff(PDO $pdo, int $tariffId): ?array
{
    $statement = $pdo->prepare(
        'SELECT t.id, s.name AS service_name, t.name AS tariff_name, t.price
         FROM tariffs t
         INNER JOIN services s ON s.id = t.service_id
         WHERE t.id = ? AND t.active = 1 AND s.active = 1
         LIMIT 1'
    );
    $statement->execute([$tariffId]);
    $tariff = $statement->fetch();
    return $tariff ?: null;
}

function tgCreateOrder(PDO $pdo, int $chatId, array $from, array $contact, array $tariff): int
{
    $tariff = tgFindTariff($pdo, (int) ($tariff['id'] ?? 0));
    if (!$tariff) {
        throw new DomainException('Selected tariff is no longer active');
    }
    $name = trim(($contact['first_name'] ?? $from['first_name'] ?? 'Telegram') . ' ' . ($contact['last_name'] ?? $from['last_name'] ?? ''));
    $phone = mb_substr((string) ($contact['phone_number'] ?? ('telegram:' . $chatId)), 0, 30, 'UTF-8');
    $email = 'telegram_' . abs($chatId) . '@telegram.local';
    $username = isset($from['username']) ? '@' . $from['username'] : 'без username';
    $total = (int) $tariff['price'];
    $comment = 'Заявка из Telegram. Chat ID: ' . $chatId . '. Username: ' . $username . '. Нужно связаться и уточнить детали.';

    $pdo->beginTransaction();
    try {
        $orderStatement = $pdo->prepare(
            'INSERT INTO orders (customer_name, phone, email, project_comment, total, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $orderStatement->execute([$name !== '' ? $name : 'Telegram user', $phone, $email, $comment, $total, 'new']);
        $orderId = (int) $pdo->lastInsertId();

        $itemStatement = $pdo->prepare(
            'INSERT INTO order_items (order_id, tariff_id, service_name, tariff_name, price)
             VALUES (?, ?, ?, ?, ?)'
        );
        $itemStatement->execute([
            $orderId,
            (int) $tariff['id'],
            $tariff['service_name'],
            $tariff['tariff_name'],
            $total,
        ]);

        $pdo->commit();
        return $orderId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function tgNotifyAdminNewOrder(int $orderId, string $source, int $total): void
{
    appSendTelegram(
        'Новая заявка #' . $orderId . "\nИсточник: " . $source . "\nИтого: " . appMoney($total),
        'Открыть заказ',
        appUrl('admin/order.php?id=' . $orderId)
    );
}

function tgHandleMessage(PDO $pdo, array $message): void
{
    if (($message['chat']['type'] ?? 'private') !== 'private') {
        return;
    }
    $chatId = (int) $message['chat']['id'];
    $from = is_array($message['from'] ?? null) ? $message['from'] : [];
    $text = trim((string) ($message['text'] ?? ''));
    $session = tgSession($pdo, $chatId);

    if ($text === '/id') {
        tgSend($chatId, 'Ваш chat_id: ' . $chatId);
        return;
    }

    if ($text === '/cancel') {
        tgSetSession($pdo, $chatId, null);
        tgSend($chatId, 'Оформление отменено.', ['reply_markup' => json_encode(['remove_keyboard' => true])]);
        return;
    }

    if ($text === '/start') {
        tgSetSession($pdo, $chatId, null);
        tgSend($chatId, "Привет! Я помощник WebStart Studio.\n\nКоманды:\n/start - начало\n/help - помощь\n/services - услуги\n/tariffs - тарифы\n/order - оставить заявку");
        return;
    }

    if ($text === '/help') {
        tgSend($chatId, "Я могу показать услуги и тарифы или помочь оставить заявку.\n\n/services — услуги\n/tariffs — тарифы\n/order — заявка\n/cancel — отменить оформление\n/id — ваш chat_id");
        return;
    }

    if ($text === '/services') {
        tgSend($chatId, tgServicesText($pdo));
        return;
    }

    if ($text === '/tariffs') {
        tgSend($chatId, tgTariffsText($pdo));
        return;
    }

    if ($text === '/order') {
        tgSetSession($pdo, $chatId, 'waiting_tariff');
        tgSend($chatId, tgTariffsText($pdo) . "\n\nОтправьте номер тарифа, который хотите заказать.");
        return;
    }

    if (($session['state'] ?? null) === 'waiting_tariff') {
        $tariffId = filter_var($text, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tariff = $tariffId !== false ? tgFindTariff($pdo, (int) $tariffId) : null;
        if (!$tariff) {
            tgSend($chatId, "Не нашёл активный тариф с таким номером. Отправьте номер из списка:\n\n" . tgTariffsText($pdo));
            return;
        }

        tgSetSession($pdo, $chatId, 'waiting_contact', (int) $tariff['id']);
        tgSend($chatId, 'Вы выбрали: ' . $tariff['service_name'] . ' - ' . $tariff['tariff_name'] . ' - ' . appMoney((int) $tariff['price']) . "\n\nТеперь отправьте контакт кнопкой ниже.", [
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => 'Отправить контакт', 'request_contact' => true]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ], JSON_UNESCAPED_UNICODE),
        ]);
        return;
    }

    if (isset($message['contact']) && is_array($message['contact'])) {
        if (($session['state'] ?? '') !== 'waiting_contact' || (int) ($message['contact']['user_id'] ?? 0) !== (int) ($from['id'] ?? -1)) {
            tgSend($chatId, 'Выберите тариф через /order и отправьте свой контакт кнопкой.');
            return;
        }
        $selectedTariffId = filter_var($session['selected_tariff_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tariff = $selectedTariffId !== false ? tgFindTariff($pdo, (int) $selectedTariffId) : null;
        if (!$tariff) {
            tgSetSession($pdo, $chatId, 'waiting_tariff');
            tgSend($chatId, 'Сначала выберите тариф через /order.', [
                'reply_markup' => json_encode(['remove_keyboard' => true], JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        $orderId = tgCreateOrder($pdo, $chatId, $from, $message['contact'], $tariff);
        tgSetSession($pdo, $chatId, null);
        tgNotifyAdminNewOrder($orderId, 'Telegram', (int) $tariff['price']);
        tgSend($chatId, 'Заявка #' . $orderId . ' создана. Менеджер свяжется с вами.', [
            'reply_markup' => json_encode(['remove_keyboard' => true], JSON_UNESCAPED_UNICODE),
        ]);
        return;
    }

    if (preg_match('/услуг|сайт|лендинг|магазин|бот|тариф|цен|стоим/u', mb_strtolower($text, 'UTF-8'))) {
        tgSend($chatId, tgTariffsText($pdo));
        return;
    }

    tgSend($chatId, 'Напишите /services, /tariffs или /order.');
}

function tgRun(PDO $pdo): int
{
    tgEnsureTables($pdo);
    $storageDir = __DIR__ . '/storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }

    $offsetFile = $storageDir . '/telegram-offset.txt';
    $offset = is_file($offsetFile) ? (int) trim((string) file_get_contents($offsetFile)) : 0;
    $response = appTelegramRequest('getUpdates', [
        'offset' => $offset,
        'timeout' => 1,
        'allowed_updates' => json_encode(['message'], JSON_UNESCAPED_UNICODE),
    ]);

    if (!$response || empty($response['ok'])) {
        appLog('Telegram getUpdates failed');
        return 1;
    }

    foreach ($response['result'] as $update) {
        if (isset($update['message']) && is_array($update['message'])) {
            tgHandleMessage($pdo, $update['message']);
        }
        $offset = max($offset, (int) $update['update_id'] + 1);
    }

    file_put_contents($offsetFile, (string) $offset, LOCK_EX);
    return 0;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(tgRun($pdo));
}
