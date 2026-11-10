<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/AiAssistant.php';
require_once __DIR__ . '/../services/SupportRelay.php';
require_once __DIR__ . '/../services/DecisionTree.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function chatJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function chatMenuSnapshot(PDO $pdo, array $snapshot, string $node, ?DecisionTree $tree = null): array
{
    if ($snapshot['status'] === 'bot') {
        $tree ??= new DecisionTree((new AiAssistant($pdo))->catalog());
        try {
            $snapshot['choices'] = $tree->resolve($node)['choices'];
        } catch (InvalidArgumentException) {
            $snapshot['choices'] = $tree->resolve('root')['choices'];
        }
    }
    return $snapshot;
}

function chatNotifyHumanRequest(int $id): void
{
    try {
        if (assistantLimit($GLOBALS['pdo'], 'human_notification:' . $id, 1, 300)
            && !supportNotifyWebsiteHumanRequest()) {
            appLog('Website specialist alert delivery failed');
        }
    } catch (Throwable $error) {
        appLog('Handoff notification failed', ['conversation_id' => $id]);
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        chatJson(['error' => 'Метод не поддерживается.'], 405);
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $expectedOrigin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $host;
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site' || ($origin !== '' && $origin !== $expectedOrigin)) {
        chatJson(['error' => 'Недопустимый источник запроса.'], 403);
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 50000) {
        chatJson(['error' => 'Сообщение слишком большое.'], 413);
    }
    appStartSession();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!in_array($action, ['start', 'message', 'poll', 'cancel_waiting'], true)) {
        chatJson(['error' => 'Неизвестное действие.'], 400);
    }
    $ipKey = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
    // Bound requests before loading a conversation. Polling has a larger allowance.
    $ipScope = $action === 'poll' ? 'chat_poll_ip:' : 'chat_control_ip:';
    $ipMaximum = $action === 'poll' ? 600 : 180;
    if (!assistantLimit($pdo, $ipScope . $ipKey, $ipMaximum, 60)) {
        header('Retry-After: ' . (60 - time() % 60));
        chatJson(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
    }
    $service = new ChatService($pdo);
    if ($action === 'start') {
        if (empty($_SESSION['chat_session_key'])) {
            if (!assistantLimit($pdo, 'chat_start:' . $ipKey, 30, 3600)) {
                chatJson(['error' => 'Слишком много новых диалогов. Попробуйте позже.'], 429);
            }
            $_SESSION['chat_session_key'] = bin2hex(random_bytes(32));
        }
        $conversation = $service->conversation($_SESSION['chat_session_key']);
        $token = appCsrfToken('chat_csrf');
        $node = $_SESSION['chat_menu_node'] ?? 'root';
        session_write_close();
        chatJson(chatMenuSnapshot($pdo, $service->snapshot((int) $conversation['id']), $node) + [
            'csrf_token' => $token,
            'max_input_length' => assistantInt('AI_MAX_INPUT_LENGTH', 2000, 200, 4000),
        ]);
    }
    if (empty($_SESSION['chat_session_key']) || !appCsrfValid('chat_csrf', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        chatJson(['error' => 'Обновите страницу и откройте чат снова.'], 403);
    }
    $conversation = $service->conversation($_SESSION['chat_session_key']);
    if (($conversation['status'] ?? '') === 'closed') {
        $conversation = $service->startFreshConversation($_SESSION['chat_session_key']);
        unset($_SESSION['chat_menu_node']);
    }
    $id = (int) $conversation['id'];
    $afterId = filter_var($_POST['after_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($afterId === false) {
        chatJson(['error' => 'Некорректный номер сообщения.'], 422);
    }
    $rawMessage = is_string($_POST['message'] ?? null) ? $_POST['message'] : '';
    if ($action === 'cancel_waiting' || ($action === 'message' && $conversation['status'] === 'waiting_human' && preg_match('/^(?:отмена|отменить|отменить вызов)[.!\s]*$/ui', trim($rawMessage)))) {
        $service->cancelWaiting($id);
        $_SESSION['chat_menu_node'] = 'root';
        session_write_close();
        chatJson(chatMenuSnapshot($pdo, $service->snapshot($id, $afterId), 'root'));
    }
    $node = $_SESSION['chat_menu_node'] ?? 'root';
    $choice = $action === 'message' ? ($_POST['choice'] ?? null) : null;
    if ($choice === null) {
        session_write_close();
    }
    if ($action === 'poll') {
        // A normal open chat polls every 2.5 seconds. Keep room for multiple tabs.
        if (!assistantLimit($pdo, 'chat_poll_conversation:' . $id, 180, 60)) {
            header('Retry-After: ' . (60 - time() % 60));
            chatJson(['error' => 'Слишком много запросов. Попробуйте позже.'], 429);
        }
        chatJson(chatMenuSnapshot($pdo, $service->snapshot($id, $afterId), $node));
    }

    $status = $conversation['status'];
    if ($choice === 'human' && in_array($status, ['waiting_human', 'human'], true)) { session_write_close(); chatJson(chatMenuSnapshot($pdo, $service->snapshot($id, $afterId), $node)); }
    if ($choice === null && !in_array($status, ['waiting_human', 'human'], true)) {
        chatJson(['error' => 'Чтобы написать сообщение, нажмите «Позвать специалиста».'], 409);
    }

    $tree = null;
    $selection = null;
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
    if ($choice !== null) {
        if ($status !== 'bot') {
            chatJson(['error' => 'Меню помощника сейчас недоступно.'], 409);
        }
        $tree = new DecisionTree((new AiAssistant($pdo))->catalog());
        try {
            $selection = $tree->resolve(is_string($choice) ? $choice : '');
        } catch (InvalidArgumentException $error) {
            chatJson(['error' => $error->getMessage()], 422);
        }
        $message = $selection['label'];
    }
    if ($message === '' || !mb_check_encoding($message, 'UTF-8') || mb_strlen($message) > assistantInt('AI_MAX_INPUT_LENGTH', 2000, 200, 4000)) {
        chatJson(['error' => 'Проверьте длину и текст сообщения.'], 422);
    }
    if (!assistantLimit($pdo, 'chat_message:' . $ipKey, 20, 60)) {
        chatJson(['error' => 'Слишком много сообщений. Попробуйте через минуту.'], 429);
    }
    $pending = $service->beginMessage($id, $message, $status);
    $handoff = $pending['handoff'];
    if ($selection !== null) {
        if ($pending['key'] !== null) {
            $handoff = $service->finishMessage($id, $pending['key'], ['text' => $selection['text'], 'offered' => false]);
        }
        $node = $choice === 'human' ? 'root' : $choice;
        $_SESSION['chat_menu_node'] = $node;
        session_write_close();
        if ($handoff) {
            chatNotifyHumanRequest($id);
        }
        chatJson(chatMenuSnapshot($pdo, $service->snapshot($id, $afterId), $node, $tree));
    }
    chatJson(chatMenuSnapshot($pdo, $service->snapshot($id, $afterId), $node));
} catch (DomainException $error) {
    chatJson(['error' => $error->getMessage()], in_array($error->getCode(), [409, 422], true) ? $error->getCode() : 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    appLog('Chat endpoint failed', ['type' => get_class($error)]);
    chatJson(['error' => 'Произошла ошибка. Попробуйте ещё раз.'], 500);
}
