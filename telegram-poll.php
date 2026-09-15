<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('VEGA_TELEGRAM_TRANSPORT')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/services/ChatService.php';
require_once __DIR__ . '/services/AiAssistant.php';
require_once __DIR__ . '/services/SupportRelay.php';

function tgSend(int|string $chatId, string $text, array $extra = []): bool
{
    return appTelegramSendMessage($chatId, $text, $extra);
}

function tgSupportChatId(): string
{
    return trim((string) appEnv('TELEGRAM_SUPPORT_CHAT_ID', ''));
}

function tgConversation(PDO $pdo, int $chatId): array
{
    $session = tgSession($pdo, $chatId);
    $conversationId = (int) ($session['conversation_id'] ?? 0);
    $service = new ChatService($pdo);
    if ($conversationId > 0) {
        $statement = $pdo->prepare('SELECT * FROM chat_conversations WHERE id = ?');
        $statement->execute([$conversationId]);
        $conversation = $statement->fetch();
        if ($conversation && $conversation['status'] !== 'closed') return $conversation;
    }
    $conversation = $service->conversation('telegram:' . $chatId . ':' . bin2hex(random_bytes(8)));
    $pdo->prepare('UPDATE telegram_sessions SET conversation_id = ? WHERE chat_id = ?')->execute([(int) $conversation['id'], $chatId]);
    return $conversation;
}

function tgConversationHistory(PDO $pdo, int $conversationId, int $limit = 8): string
{
    $limit = max(1, min(10, $limit));
    $statement = $pdo->prepare('SELECT id, sender, message FROM chat_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT ' . $limit);
    $statement->execute([$conversationId]);
    $rows = array_reverse($statement->fetchAll());
    $lines = [];
    $seen = [];
    foreach ($rows as $row) {
        $messageId = (int) $row['id'];
        if (isset($seen[$messageId])) {
            continue;
        }
        $seen[$messageId] = true;
        $label = $row['sender'] === 'user' ? '👤 Клиент' : ($row['sender'] === 'admin' ? '👨‍💻 Менеджер' : '🤖 Vega Assistant');
        $lines[] = $label . ":\n<blockquote>" . appTelegramEscape(mb_substr((string) $row['message'], 0, 700, 'UTF-8')) . '</blockquote>';
    }
    return implode("\n\n", $lines);
}

function tgAdminName(array $from): string
{
    $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    return $name !== '' ? $name : ('Telegram ID ' . (int) ($from['id'] ?? 0));
}

function tgUpsertProfile(PDO $pdo, int $chatId, array $from): void
{
    $statement = $pdo->prepare('INSERT INTO telegram_profiles (chat_id, user_id, username, first_name, last_name, language_code) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), username = VALUES(username), first_name = VALUES(first_name), last_name = VALUES(last_name), language_code = VALUES(language_code), last_activity = CURRENT_TIMESTAMP');
    $statement->execute([$chatId, (int) ($from['id'] ?? $chatId), $from['username'] ?? null, $from['first_name'] ?? null, $from['last_name'] ?? null, $from['language_code'] ?? null]);
}

function tgRelayToSupport(PDO $pdo, int $chatId, array $from, string $text): void
{
    $supportId = tgSupportChatId();
    if ($supportId === '') return;
    $conversation = tgConversation($pdo, $chatId);
    $conversationId = (int) $conversation['id'];
    $history = tgConversationHistory($pdo, $conversationId);
    $keyboard = [[['text' => '❌ Закрыть', 'callback_data' => 'close:' . $chatId]]];
    $publicUrl = appUrl('admin/chats.php?id=' . $conversationId);
    $host = strtolower((string) parse_url($publicUrl, PHP_URL_HOST));
    if (parse_url($publicUrl, PHP_URL_SCHEME) === 'https' && $host !== '' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
        $keyboard[0][] = ['text' => '💬 Открыть переписку', 'url' => $publicUrl];
    }
    $result = appTelegramSendMessageResult($supportId, "⚠️ <b>НОВЫЙ ЗАПРОС СПЕЦИАЛИСТА</b>\n\n👤 <b>Клиент:</b> " . appTelegramEscape(trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''))) .
        "\nTelegram: @" . appTelegramEscape((string) ($from['username'] ?? 'без username')) . "\nChat ID: " . $chatId .
        "\nДиалог: #" . $conversationId . "\n\n<b>Последние сообщения:</b>\n" . ($history !== '' ? $history : '<blockquote>' . appTelegramEscape($text) . '</blockquote>') .
        "\n\nСтатус: ⏳ Ожидает специалиста", [
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard], JSON_UNESCAPED_UNICODE),
    ]);
    if ($result && isset($result['message_id'])) {
        $statement = $pdo->prepare('REPLACE INTO telegram_relays (admin_message_id, client_chat_id, admin_chat_id, conversation_id) VALUES (?, ?, ?, ?)');
        $statement->execute([(int) $result['message_id'], $chatId, (int) $supportId, $conversationId]);
    }
}

function tgHandleSupportMessage(PDO $pdo, array $message): void
{
    $supportId = tgSupportChatId();
    $sourceChatId = (string) ($message['chat']['id'] ?? '');
    $adminId = (int) ($message['from']['id'] ?? 0);
    appLog('Telegram support update received', ['chat_id' => $sourceChatId, 'sender_user_id' => $adminId]);
    if ($supportId === '' || $sourceChatId !== $supportId) return;
    if (!in_array($adminId, array_map('intval', appTelegramAdminChatIds()), true)) {
        appLog('Telegram support relay denied', ['sender_user_id' => $adminId]);
        return;
    }
    $replyId = (int) ($message['reply_to_message']['message_id'] ?? 0);
    $text = trim((string) ($message['text'] ?? ''));
    if ($text === '') return;
    $relay = null;
    if ($replyId > 0) {
        $statement = $pdo->prepare('SELECT client_chat_id, conversation_id, channel FROM telegram_relays WHERE admin_message_id = ?');
        $statement->execute([$replyId]);
        $relay = $statement->fetch() ?: null;
    }
    if (!$relay) {
        appLog('Telegram support message ignored: reply mapping not found', [
            'admin_user_id' => $adminId,
            'reply_message_id' => $replyId,
        ]);
        return;
    }
    $clientId = (int) ($relay['client_chat_id'] ?? 0);
    $channel = (string) ($relay['channel'] ?? 'telegram');
    if ($channel === 'website' && (int) ($relay['conversation_id'] ?? 0) > 0) {
        (new ChatService($pdo))->telegramAdminAction((int) $relay['conversation_id'], 'reply', $text);
        appLog('Telegram active assignment relay to website succeeded', [
            'admin_user_id' => $adminId,
            'conversation_id' => (int) $relay['conversation_id'],
        ]);
        return;
    }
    $channel = (string) ($relay['channel'] ?? 'telegram');
    if ($channel === 'website' && (int) ($relay['conversation_id'] ?? 0) > 0) {
        (new ChatService($pdo))->telegramAdminAction((int) $relay['conversation_id'], 'reply', $text);
        appLog('Telegram admin relay to website succeeded', [
            'admin_user_id' => $adminId,
            'conversation_id' => (int) $relay['conversation_id'],
        ]);
        return;
    }
    if ($clientId > 0) {
        $conversationId = (int) ($relay['conversation_id'] ?? 0);
        if ($conversationId > 0) {
            (new ChatService($pdo))->telegramAdminAction($conversationId, 'reply', $text);
        }
        appTelegramChatAction($clientId);
        $adminName = tgAdminName((array) ($message['from'] ?? []));
        $sent = appTelegramSendMessage($clientId, "👨‍💻 <b>" . appTelegramEscape($adminName) . " · Vega Studio:</b>\n\n" . appTelegramEscape($text), tgMenu());
        appLog('Telegram admin relay ' . ($sent ? 'succeeded' : 'failed'), [
            'admin_user_id' => $adminId,
            'conversation_id' => $conversationId,
            'client_chat_id' => $clientId,
        ]);
        return;
    }
    appSendTelegram('⚠️ Telegram relay: сообщение администратора не связано с диалогом. Нажмите Reply на карточке клиента.');
}

function tgTextButton(string $text, string $callback): array
{
    return ['text' => $text, 'callback_data' => $callback];
}

function tgHome(int $chatId): void
{
    tgSend($chatId, "🚀 <b>Vega Studio</b>\n\nПривет! Я виртуальный помощник Vega Studio.\n\nПомогу подобрать решение, узнать актуальные цены, оформить заявку или позвать специалиста.", tgMenu());
}

function tgAskHuman(int $chatId, array $from, string $lastMessage): void
{
    $conversation = tgConversation($GLOBALS['pdo'], $chatId);
    $status = (string) ($conversation['status'] ?? 'bot');
    if ($status === 'bot') {
        (new ChatService($GLOBALS['pdo']))->beginMessage((int) $conversation['id'], $lastMessage);
    }
    if (tgSupportChatId() !== '') {
        tgRelayToSupport($GLOBALS['pdo'], $chatId, $from, $lastMessage);
    } else {
        appLog('Telegram human handoff skipped: support chat id is empty', ['chat_id' => $chatId]);
    }
    tgSend($chatId, '👨‍💻 Передал ваш диалог специалисту Vega Studio. Он сможет продолжить разговор здесь.', tgMenu());
}

function tgAiReply(PDO $pdo, int $chatId, string $text, array $from): bool
{
    if (ChatService::needsHuman($text, false)) {
        tgAskHuman($chatId, $from, $text);
        return true;
    }
    $service = new ChatService($pdo);
    $conversation = tgConversation($pdo, $chatId);
    if (in_array((string) ($conversation['status'] ?? ''), ['waiting_human', 'human'], true)) {
        $service->add((int) $conversation['id'], 'user', $text);
        tgRelayToSupport($pdo, $chatId, $from, $text);
        return true;
    }
    $pending = $service->beginMessage((int) $conversation['id'], $text);
    if ($pending['handoff']) {
        tgAskHuman($chatId, $from, $text);
        return true;
    }
    appTelegramChatAction($chatId);
    $reply = (new AiAssistant($pdo))->reply((int) $conversation['id'], $text, true);
    if ($service->finishMessage((int) $conversation['id'], (string) $pending['key'], $reply)) {
        tgAskHuman($chatId, $from, $text);
        return true;
    }
    tgSend($chatId, appTelegramEscape((string) ($reply['text'] ?? 'Я пока не смог точно понять вопрос. Могу показать услуги или пригласить специалиста.')), tgMenu());
    return true;
}

function tgSession(PDO $pdo, int $chatId): array
{
    $statement = $pdo->prepare('SELECT chat_id, state, selected_tariff_id, conversation_id, human_mode FROM telegram_sessions WHERE chat_id = ? LIMIT 1');
    $statement->execute([$chatId]);
    $row = $statement->fetch();
    if ($row) {
        return $row;
    }

    $pdo->prepare('INSERT INTO telegram_sessions (chat_id, state) VALUES (?, NULL)')->execute([$chatId]);
    return ['chat_id' => $chatId, 'state' => null, 'selected_tariff_id' => null, 'conversation_id' => null, 'human_mode' => 0];
}

function tgSetSession(PDO $pdo, int $chatId, ?string $state, ?int $tariffId = null): void
{
    $pdo->prepare(
        'INSERT INTO telegram_sessions (chat_id, state, selected_tariff_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE state = VALUES(state), selected_tariff_id = VALUES(selected_tariff_id)'
    )->execute([$chatId, $state, $tariffId]);
}

function tgMenu(): array
{
    return ['reply_markup' => json_encode([
        'keyboard' => [
            [['text' => '🌐 Услуги'], ['text' => '💎 Тарифы']],
            [['text' => '✨ Подобрать решение']],
            [['text' => '🛒 Оставить заявку']],
            [['text' => '💬 Задать вопрос'], ['text' => '👨‍💻 Позвать специалиста']],
            [['text' => '📞 Контакты']],
        ],
        'resize_keyboard' => true,
        'is_persistent' => true,
    ], JSON_UNESCAPED_UNICODE)];
}

function tgInline(array $rows): array
{
    return ['reply_markup' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
}

function tgServices(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, description, deadline FROM services WHERE active = 1 ORDER BY id')->fetchAll();
}

function tgServiceCard(PDO $pdo, int $serviceId): ?string
{
    $statement = $pdo->prepare('SELECT id, name, description, deadline FROM services WHERE id = ? AND active = 1');
    $statement->execute([$serviceId]);
    $service = $statement->fetch();
    if (!$service) return null;
    return "🌐 <b>" . appTelegramEscape((string) $service['name']) . "</b>\n\n" . appTelegramEscape((string) $service['description']) .
        "\n\n⏱ Срок: " . appTelegramEscape((string) ($service['deadline'] ?: 'уточнит специалист'));
}

function tgTariffCard(PDO $pdo, int $tariffId): ?array
{
    $statement = $pdo->prepare('SELECT t.id, t.name AS tariff_name, t.description, t.price, s.id AS service_id, s.name AS service_name, s.deadline FROM tariffs t JOIN services s ON s.id = t.service_id WHERE t.id = ? AND t.active = 1 AND s.active = 1');
    $statement->execute([$tariffId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function tgSendTariffCard(int $chatId, array $tariff): void
{
    $text = "💎 <b>" . appTelegramEscape($tariff['service_name'] . ' — ' . $tariff['tariff_name']) . "</b>\n\n"
        . appTelegramEscape((string) ($tariff['description'] ?: 'Описание уточнит специалист.')) . "\n\n"
        . "💰 <b>Стоимость:</b> " . appMoney((int) $tariff['price']) . "\n"
        . "⏱ <b>Срок:</b> " . appTelegramEscape((string) ($tariff['deadline'] ?: 'уточнит специалист'));
    tgSend($chatId, $text, tgInline([
        [tgTextButton('🛒 Заказать', 'order_tar:' . (int) $tariff['id'])],
        [tgTextButton('← Назад', 'svc:' . (int) $tariff['service_id']), tgTextButton('🏠 Меню', 'menu:home')],
    ]));
}

function tgHandleCallback(PDO $pdo, array $callback): void
{
    $chatId = filter_var($callback['message']['chat']['id'] ?? null, FILTER_VALIDATE_INT);
    $data = (string) ($callback['data'] ?? '');
    if ($chatId === false || mb_strlen($data) > 80) return;
    appTelegramRequest('answerCallbackQuery', ['callback_query_id' => (string) ($callback['id'] ?? ''), 'text' => 'Обработка запроса...']);

    if (preg_match('/^(closeconv):(\d+)$/', $data, $match) && in_array((int) ($callback['from']['id'] ?? 0), array_map('intval', appTelegramAdminChatIds()), true)) {
        $conversationId = (int) $match[2];
        $adminId = (int) ($callback['from']['id'] ?? 0);
        $adminName = tgAdminName((array) ($callback['from'] ?? []));
        $status = 'closed';
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT status FROM chat_conversations WHERE id = ? FOR UPDATE');
            $statement->execute([$conversationId]);
            $currentStatus = (string) $statement->fetchColumn();
            if ($currentStatus === '') {
                $pdo->rollBack();
                appTelegramRequest('answerCallbackQuery', ['callback_query_id' => (string) ($callback['id'] ?? ''), 'text' => 'Диалог не найден']);
                return;
            }
            if (in_array($currentStatus, ['closed', 'bot'], true)) {
                $pdo->rollBack();
                appTelegramRequest('answerCallbackQuery', ['callback_query_id' => (string) ($callback['id'] ?? ''), 'text' => 'Диалог уже завершён']);
                return;
            }
            $pdo->prepare('UPDATE chat_conversations SET status = ? WHERE id = ?')->execute([$status, $conversationId]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        appTelegramRequest('answerCallbackQuery', ['callback_query_id' => (string) ($callback['id'] ?? ''), 'text' => 'Диалог завершён']);
        if ($match[1] === 'closeconv') {
            $pdo->prepare('DELETE FROM telegram_assignments WHERE conversation_id = ?')->execute([$conversationId]);
            appSendTelegram('✅ Диалог #' . $conversationId . ' завершён.\nМенеджер: ' . appTelegramEscape($adminName) . '\nКанал: Telegram');
        }
        return;
    }

    if (preg_match('/^(close|resume):(\d+)$/', $data, $match) && in_array((int) ($callback['from']['id'] ?? 0), array_map('intval', appTelegramAdminChatIds()), true)) {
        $clientId = (int) $match[2];
        $conversation = tgConversation($pdo, $clientId);
        $conversationId = (int) $conversation['id'];
        $adminId = (int) ($callback['from']['id'] ?? 0);
        $adminName = tgAdminName((array) ($callback['from'] ?? []));
        $status = $match[1] === 'take' ? 'human' : ($match[1] === 'close' ? 'closed' : 'bot');
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT status FROM chat_conversations WHERE id = ? FOR UPDATE');
            $statement->execute([$conversationId]);
            $current = (string) $statement->fetchColumn();
            if ($match[1] === 'take' && $current === 'human') {
                $pdo->commit();
                appTelegramRequest('answerCallbackQuery', ['callback_query_id' => (string) ($callback['id'] ?? ''), 'text' => 'Диалог уже взял другой администратор']);
                return;
            }
            if ($match[1] === 'close' && in_array($current, ['closed', 'bot'], true)) {
                $pdo->rollBack();
                appTelegramRequest('answerCallbackQuery', ['callback_query_id' => (string) ($callback['id'] ?? ''), 'text' => 'Диалог уже завершён']);
                return;
            }
            $pdo->prepare('UPDATE chat_conversations SET status = ? WHERE id = ?')->execute([$status, $conversationId]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        if ($match[1] === 'close') {
            tgSend($clientId, '✅ Диалог со специалистом завершён. Vega Assistant снова доступен. Если появятся вопросы, просто напишите сообщение.', tgMenu());
            appSendTelegram('✅ Диалог #' . $conversationId . ' завершён.\nМенеджер: ' . appTelegramEscape($adminName) . '\nКанал: Telegram');
            $pdo->prepare('DELETE FROM telegram_assignments WHERE conversation_id = ?')->execute([$conversationId]);
        } elseif ($match[1] === 'resume') {
            tgSend($clientId, '🤖 AI снова подключён к диалогу.', tgMenu());
        } else {
            $pdo->prepare('UPDATE chat_assistant_state SET generation_key = NULL, generation_started_at = NULL, handoff_offered = 0 WHERE conversation_id = ?')->execute([$conversationId]);
            appTelegramRequest('answerCallbackQuery', ['callback_query_id' => (string) ($callback['id'] ?? ''), 'text' => 'AI снова подключён']);
        }
        return;
    }

    if ($data === 'menu:home') {
        tgHome((int) $chatId);
        return;
    }
    if ($data === 'menu:services') {
        tgSend((int) $chatId, "🌐 <b>Наши услуги</b>\n\nВыберите направление:", tgServicesKeyboard($pdo));
        return;
    }
    if ($data === 'menu:tariffs') {
        tgSend((int) $chatId, "💎 <b>Тарифы</b>\n\nВыберите услугу:", tgServicesKeyboard($pdo));
        return;
    }
    if ($data === 'menu:support') {
        tgAskHuman((int) $chatId, (array) ($callback['from'] ?? []), 'Клиент нажал «Позвать специалиста»');
        return;
    }
    if (preg_match('/^svc:(\d+)$/', $data, $match)) {
        $serviceId = (int) $match[1];
        $card = tgServiceCard($pdo, $serviceId);
        if ($card === null) {
            tgSend((int) $chatId, 'Эта услуга больше недоступна.', tgMenu());
            return;
        }
        tgSend((int) $chatId, $card . "\n\nВыберите тариф:", tgTariffsKeyboard($pdo, $serviceId));
        return;
    }
    if (preg_match('/^tar:(\d+)$/', $data, $match)) {
        $tariff = tgTariffCard($pdo, (int) $match[1]);
        if ($tariff === null) {
            tgSend((int) $chatId, 'Этот тариф больше недоступен.', tgMenu());
            return;
        }
        tgSendTariffCard((int) $chatId, $tariff);
        return;
    }
    if (preg_match('/^order_tar:(\d+)$/', $data, $match)) {
        $tariff = tgTariffCard($pdo, (int) $match[1]);
        if ($tariff === null) {
            tgSend((int) $chatId, 'Этот тариф больше недоступен.', tgMenu());
            return;
        }
        tgSetSession($pdo, (int) $chatId, 'waiting_contact', (int) $tariff['id']);
        tgSend((int) $chatId, "Вы выбрали <b>" . appTelegramEscape($tariff['service_name'] . ' — ' . $tariff['tariff_name']) . '</b> за ' . appMoney((int) $tariff['price']) . ".\n\nНажмите кнопку и отправьте свой номер телефона.", [
            'reply_markup' => json_encode(['keyboard' => [[['text' => '📞 Отправить контакт', 'request_contact' => true]], [['text' => '❌ Отмена']]], 'resize_keyboard' => true, 'one_time_keyboard' => true], JSON_UNESCAPED_UNICODE),
        ]);
    }
}

function tgServicesKeyboard(PDO $pdo): array
{
    $rows = [];
    foreach (tgServices($pdo) as $service) {
        $rows[] = [['text' => $service['name'], 'callback_data' => 'svc:' . (int) $service['id']]];
    }
    return tgInline($rows);
}

function tgTariffsKeyboard(PDO $pdo, int $serviceId = 0): array
{
    $sql = 'SELECT id, name FROM tariffs WHERE active = 1';
    $params = [];
    if ($serviceId > 0) {
        $sql .= ' AND service_id = ?';
        $params[] = $serviceId;
    }
    $sql .= ' ORDER BY service_id, price';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $rows = [];
    foreach ($statement->fetchAll() as $tariff) {
        $rows[] = [['text' => $tariff['name'], 'callback_data' => 'tar:' . (int) $tariff['id']]];
    }
    $rows[] = [['text' => '← Назад', 'callback_data' => 'menu:services'], ['text' => '🏠 Меню', 'callback_data' => 'menu:home']];
    return tgInline($rows);
}

function tgServicesText(PDO $pdo): string
{
    $rows = $pdo->query('SELECT name, description FROM services WHERE active = 1 ORDER BY id')->fetchAll();
    if ($rows === []) {
        return 'Список услуг сейчас обновляется.';
    }

    $lines = ['🌐 <b>Услуги Vega Studio</b>'];
    foreach ($rows as $row) {
        $lines[] = '• <b>' . appTelegramEscape((string) $row['name']) . '</b>: ' . appTelegramEscape((string) $row['description']);
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
            'INSERT INTO orders (customer_name, phone, email, project_comment, total, status, source)
               VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $orderStatement->execute([$name !== '' ? $name : 'Telegram user', $phone, $email, $comment, $total, 'new', 'telegram']);
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
        tgHandleSupportMessage($pdo, $message);
        return;
    }
    if (($message['chat']['type'] ?? 'private') !== 'private') {
        return;
    }
    $chatId = (int) $message['chat']['id'];
    $from = is_array($message['from'] ?? null) ? $message['from'] : [];
    $text = trim((string) ($message['text'] ?? ''));
    tgUpsertProfile($pdo, $chatId, $from);
    $session = tgSession($pdo, $chatId);
    $conversation = tgConversation($pdo, $chatId);
    if (in_array((string) ($conversation['status'] ?? ''), ['waiting_human', 'human'], true) && $text !== '' && !str_starts_with($text, '/')) {
        (new ChatService($pdo))->add((int) $conversation['id'], 'user', $text);
        tgRelayToSupport($pdo, $chatId, $from, $text);
        return;
    }
    if (($conversation['status'] ?? '') === 'closed' && $text !== '') {
        $pdo->prepare('UPDATE telegram_sessions SET conversation_id = NULL, human_mode = 0 WHERE chat_id = ?')->execute([$chatId]);
    }

    if ($text === '/id') {
        tgSend($chatId, 'Ваш chat_id: ' . $chatId);
        return;
    }

    if ($text === '/cancel') {
        tgSetSession($pdo, $chatId, null);
        tgSend($chatId, 'Оформление отменено.', ['reply_markup' => json_encode(['remove_keyboard' => true])]);
        return;
    }

    if ($text === '/start' || $text === '🏠 Главное меню') {
        tgSetSession($pdo, $chatId, null);
        tgHome($chatId);
        return;
    }

    if ($text === '/help') {
        tgSend($chatId, "Я могу помочь:\n🌐 подобрать сайт\n💎 рассказать о тарифах\n🤖 объяснить AI-решения\n🛒 оформить заявку\n👨‍💻 позвать специалиста\n\nИспользуйте кнопки ниже 👇", tgMenu());
        return;
    }

    if ($text === '/services' || $text === '🌐 Услуги') {
        tgSend($chatId, "🌐 <b>Наши услуги</b>\n\nВыберите направление:", tgServicesKeyboard($pdo));
        return;
    }

    if ($text === '/tariffs' || $text === '💎 Тарифы') {
        tgSend($chatId, "💎 <b>Тарифы</b>\n\nВыберите услугу:", tgServicesKeyboard($pdo));
        return;
    }

    if ($text === '/order' || $text === '🛒 Оставить заявку') {
        tgSetSession($pdo, $chatId, 'waiting_tariff');
        tgSend($chatId, tgTariffsText($pdo) . "\n\nОтправьте номер тарифа, который хотите заказать.");
        return;
    }

    if ($text === '👨‍💻 Позвать специалиста' || $text === '/support' || preg_match('/позов|специалист|оператор|менеджер|жив(ого|ой) человек|администратор/iu', $text)) {
        tgAskHuman($chatId, $from, $text);
        return;
    }

    if ($text === '💬 Задать вопрос' || $text === '✨ Подобрать решение') {
        tgSend($chatId, 'Опишите задачу обычным сообщением. Например: «Нужен сайт для цветочного магазина».', tgMenu());
        return;
    }

    if ($text === '📞 Контакты') {
        tgSend($chatId, 'Оставьте заявку или напишите специалисту через кнопку «Позвать специалиста». Подтверждённые контакты Vega Studio сообщит администратор.', tgMenu());
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

    tgAiReply($pdo, $chatId, $text, $from);
}

function tgHandleUpdate(PDO $pdo, array $update): bool
{
    $updateId = filter_var($update['update_id'] ?? null, FILTER_VALIDATE_INT);
    if ($updateId === false) {
        return false;
    }
    $claim = $pdo->prepare('INSERT IGNORE INTO telegram_processed_updates (update_id) VALUES (?)');
    $claim->execute([(int) $updateId]);
    if ($claim->rowCount() !== 1) {
        return false;
    }
    if (isset($update['callback_query']) && is_array($update['callback_query'])) {
        tgHandleCallback($pdo, $update['callback_query']);
    }
    if (isset($update['message']) && is_array($update['message'])) {
        tgHandleMessage($pdo, $update['message']);
    }
    return true;
}

function tgRun(PDO $pdo): int
{
    if (appEnv('TELEGRAM_MODE', 'polling') !== 'polling') {
        appLog('Telegram polling skipped: TELEGRAM_MODE is not polling');
        return 2;
    }
    $storageDir = __DIR__ . '/storage';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }

    $offsetFile = $storageDir . '/telegram-offset.txt';
    $offset = is_file($offsetFile) ? (int) trim((string) file_get_contents($offsetFile)) : 0;
    $response = appTelegramRequest('getUpdates', [
        'offset' => $offset,
        'timeout' => 1,
        'allowed_updates' => json_encode(['message', 'callback_query'], JSON_UNESCAPED_UNICODE),
    ]);

    if (!$response || empty($response['ok'])) {
        appLog('Telegram getUpdates failed');
        return 1;
    }

    foreach ($response['result'] as $update) {
        $updateId = filter_var($update['update_id'] ?? null, FILTER_VALIDATE_INT);
        if ($updateId !== false) {
            tgHandleUpdate($pdo, $update);
            $offset = max($offset, (int) $updateId + 1);
        }
    }

    file_put_contents($offsetFile, (string) $offset, LOCK_EX);
    $pdo->exec('DELETE FROM telegram_processed_updates WHERE processed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    return 0;
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(tgRun($pdo));
}
