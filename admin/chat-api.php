<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/ChatService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (!appAdminSessionActive()) {
        throw new DomainException('Требуется вход администратора.', 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !appCsrfValid('admin_csrf', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        throw new DomainException('Обновите страницу и повторите действие.', 403);
    }
    $service = new ChatService($pdo);
    if (($_POST['action'] ?? '') === 'list') {
        $adminId = (int) $_SESSION['admin_id'];
        session_write_close();
        echo json_encode(['conversations' => $service->adminConversations($adminId)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $id = filter_var($_POST['chat_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!$id || !in_array($action, ['poll', 'typing', 'reply', 'status'], true)) {
        throw new DomainException('Некорректный запрос.', 422);
    }
    $value = is_string($_POST['value'] ?? null) ? $_POST['value'] : '';
    $adminId = (int) $_SESSION['admin_id'];
    session_write_close();
    $service = new ChatService($pdo);
    $service->adminAction($id, $adminId, $action, $value);
    $after = filter_var($_POST['after_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $snapshot = $service->snapshot($id, $after === false ? 0 : $after);
    if ($snapshot['messages']) {
        $service->markRead($id, $adminId, (int) end($snapshot['messages'])['id']);
    }
    echo json_encode($snapshot, JSON_UNESCAPED_UNICODE);
} catch (DomainException $error) {
    http_response_code(in_array($error->getCode(), [401, 403, 409, 422], true) ? $error->getCode() : 422);
    echo json_encode(['error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    appLog('Admin chat failed', ['type' => get_class($error)]);
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось выполнить действие.'], JSON_UNESCAPED_UNICODE);
}
