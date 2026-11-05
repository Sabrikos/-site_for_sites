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
        $statement = $GLOBALS['pdo']->prepare('SELECT customer_name, customer_contact FROM chat_conversations WHERE id = ?');
        $statement->execute([$id]);
        $conversation = $statement->fetch() ?: [];
        supportNotify(
            $GLOBALS['pdo'],
            $id,
            'website',
            null,
            (string) ($conversation['customer_name'] ?? 'Пользователь сайта'),
            '',
            'Пользователь сайта запросил помощь специалиста.',
        );
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
    if (!in_array($action, ['start', 'message', 'poll'], true)) {
        chatJson(['error' => 'Неизвестное действие.'], 400);
    }
    $ipKey = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
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
    $node = $_SESSION['chat_menu_node'] ?? 'root';
    $choice = $action === 'message' ? ($_POST['choice'] ?? null) : null;
    if ($choice === null) {
        session_write_close();
    }
    if ($action === 'poll') {
        chatJson(chatMenuSnapshot($pdo, $service->snapshot($id, $afterId), $node));
    }

    $tree = null;
    $selection = null;
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
    if ($choice !== null) {
        if ($conversation['status'] !== 'bot') {
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
    $pending = $service->beginMessage($id, $message, $selection === null ? null : 'bot');
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
    if (!$handoff && $pending['key'] === null) {
        $conversationState = $service->snapshot($id);
        if (in_array((string) ($conversationState['status'] ?? ''), ['waiting_human', 'human'], true)) {
            supportNotify($pdo, $id, 'website', null, 'Пользователь сайта', '', $message);
        }
    }
    if ($pending['key']) {
        $allowApi = false;
        if (appEnv('AI_API_KEY', appEnv('OPENAI_API_KEY')) && !AiAssistant::restricted($message)) {
            $allowApi = assistantLimit($pdo, 'ai_conversation:' . $id, 20, 3600)
                && assistantLimit($pdo, 'ai_ip:' . $ipKey, 40, 3600)
                && assistantLimit($pdo, 'ai_global', assistantInt('AI_MAX_REQUESTS_PER_DAY', 100, 1, 5000), 86400);
        }
        try {
            $reply = (new AiAssistant($pdo))->reply($id, $message, $allowApi);
        } catch (Throwable $error) {
            appLog('Assistant failed', ['type' => get_class($error)]);
            $reply = ['text' => 'Не удалось подготовить ответ. Могу позвать администратора Vega Studio.', 'offered' => true];
        }
        $handoff = $service->finishMessage($id, $pending['key'], $reply);
    }
    if ($handoff) {
        chatNotifyHumanRequest($id);
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
