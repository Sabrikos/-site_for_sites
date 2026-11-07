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
    if ($status === 200 && isset($GLOBALS['tree'])) {
        try { $payload['choices'] = $GLOBALS['tree']->resolve($_SESSION['chat_tree_node'] ?? 'root')['choices']; }
        catch (InvalidArgumentException $error) { $payload['choices'] = $GLOBALS['tree']->resolve('root')['choices']; }
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
    $service = new ChatService($pdo);
    $tree = new DecisionTree((new AiAssistant($pdo))->catalog());
    if ($action === 'start') {
        if (empty($_SESSION['chat_session_key'])) {
            if (!assistantLimit($pdo, 'chat_start:' . $ipKey, 30, 3600)) {
                chatJson(['error' => 'Слишком много новых диалогов. Попробуйте позже.'], 429);
            }
            $_SESSION['chat_session_key'] = bin2hex(random_bytes(32));
        }
        $conversation = $service->conversation($_SESSION['chat_session_key']);
        $token = appCsrfToken('chat_csrf');
        $_SESSION['chat_tree_node'] = 'root';
        session_write_close();
        chatJson($service->snapshot((int) $conversation['id']) + [
            'csrf_token' => $token,
            'max_input_length' => assistantInt('AI_MAX_INPUT_LENGTH', 2000, 200, 4000),
        ]);
    }
    if (empty($_SESSION['chat_session_key']) || !appCsrfValid('chat_csrf', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        chatJson(['error' => 'Обновите страницу и откройте чат снова.'], 403);
    }
    $conversation = $service->conversation($_SESSION['chat_session_key']);
    if ($action === 'cancel_waiting' && ($conversation['status'] ?? '') !== 'waiting_human') {
        throw new DomainException('Отменить можно только ожидание специалиста.', 409);
    }
    if (($conversation['status'] ?? '') === 'closed') {
        $conversation = $service->startFreshConversation($_SESSION['chat_session_key']);
        $_SESSION['chat_tree_node'] = 'root';
    }
    $id = (int) $conversation['id'];
    $afterId = filter_var($_POST['after_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($afterId === false) {
        chatJson(['error' => 'Некорректный номер сообщения.'], 422);
    }
    if ($action === 'cancel_waiting' || ($action === 'message' && $conversation['status'] === 'waiting_human' && preg_match('/^(?:отмена|отменить|отменить вызов)[.!\s]*$/ui', trim((string) ($_POST['message'] ?? ''))))) {
        $service->cancelWaiting($id);
        $_SESSION['chat_tree_node'] = 'root';
        session_write_close();
        chatJson($service->snapshot($id, $afterId));
    }
    if ($action === 'poll') {
        session_write_close();
        chatJson($service->snapshot($id, $afterId));
    }
    $choice = is_string($_POST['choice'] ?? null) ? $_POST['choice'] : '';
    if ($choice === 'human' && in_array($conversation['status'], ['waiting_human', 'human'], true)) {
        session_write_close();
        chatJson($service->snapshot($id, $afterId));
    }
    if ($choice !== '' && $conversation['status'] !== 'bot') {
        chatJson(['error' => 'Режим чата изменился. Обновите меню.'], 409);
    }
    $treeReply = null;
    if ($conversation['status'] === 'bot') {
        try { $choice = is_string($_POST['choice'] ?? null) ? $_POST['choice'] : ''; $treeReply = $tree->resolve($choice); }
        catch (InvalidArgumentException $error) { chatJson(['error'=>$error->getMessage()],422); }
        $_SESSION['chat_tree_node'] = $choice === 'human' ? 'root' : $choice;
        $_POST['message'] = $treeReply['label'];
    }
    session_write_close();
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
    if ($message === '' || !mb_check_encoding($message, 'UTF-8') || mb_strlen($message) > assistantInt('AI_MAX_INPUT_LENGTH', 2000, 200, 4000)) {
        chatJson(['error' => 'Проверьте длину и текст сообщения.'], 422);
    }
    if (!assistantLimit($pdo, 'chat_message:' . $ipKey, 20, 60)) {
        chatJson(['error' => 'Слишком много сообщений. Попробуйте через минуту.'], 429);
    }
    try {
        $pending = $service->beginMessage($id, $message, $conversation['status']);
    } catch (DomainException $error) {
        if ($choice === 'human' && $error->getCode() === 409) {
            $snapshot = $service->snapshot($id, $afterId);
            if (in_array($snapshot['status'], ['waiting_human', 'human'], true)) {
                chatJson($snapshot);
            }
        }
        throw $error;
    }
    $handoff = $pending['handoff'];
    if ($pending['key']) {
        try {
            $reply = ['text'=>$treeReply['text'] ?? $tree->resolve('root')['text'], 'offered'=>false, 'mode'=>'tree'];
        } catch (Throwable $error) {
            appLog('Assistant failed', ['type' => get_class($error)]);
            $reply = ['text' => 'Не удалось подготовить ответ. Могу позвать администратора Vega Studio.', 'offered' => true];
        }
        $handoff = $service->finishMessage($id, $pending['key'], $reply);
    }
    if ($handoff) {
        chatNotifyHumanRequest($id);
    }
    chatJson($service->snapshot($id, $afterId));
} catch (DomainException $error) {
    chatJson(['error' => $error->getMessage()], in_array($error->getCode(), [409, 422], true) ? $error->getCode() : 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    appLog('Chat endpoint failed', ['type' => get_class($error)]);
    chatJson(['error' => 'Произошла ошибка. Попробуйте ещё раз.'], 500);
}
