<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/ChatService.php';


$chatService = new ChatService($pdo);
$error = '';
$chatId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$allowedStatuses = ['bot' => 'Ассистент', 'waiting_human' => 'Ожидает администратора', 'human' => 'Администратор', 'closed' => 'Завершён'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!appCsrfValid('admin_csrf', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new DomainException('Обновите страницу и повторите действие.');
        }
        $postId = filter_var($_POST['chat_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $action = (string) ($_POST['action'] ?? '');
        if (!$postId || !in_array($action, ['reply', 'status'], true)) {
            throw new DomainException('Некорректное действие.');
        }
        $newStatus = (string) ($_POST['value'] ?? '');
        $chatService->adminAction($postId, (int) $_SESSION['admin_id'], $action, $newStatus);
        header('Location: chats.php?id=' . $postId, true, 303);
        exit;
    } catch (Throwable $exception) {
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'Не удалось выполнить действие.';
    }
}
$csrf = appCsrfToken('admin_csrf');
$conversations = $chatService->adminConversations((int) $_SESSION['admin_id']);
$currentChat = null;
$messages = [];
if ($chatId) {
    $statement = $pdo->prepare('SELECT * FROM chat_conversations WHERE id = ?');
    $statement->execute([$chatId]);
    $currentChat = $statement->fetch();
    if ($currentChat) {
        $chatService->adminAction($chatId, (int) $_SESSION['admin_id'], 'poll');
        $messages = $chatService->snapshot($chatId)['messages'];
        if ($messages) {
            $chatService->markRead($chatId, (int) $_SESSION['admin_id'], (int) end($messages)['id']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru" class="admin-chat-document">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title>Чаты | Vega Studio</title>
    <link rel="stylesheet" href="../styles.css?v=<?= filemtime(__DIR__ . '/../styles.css') ?>">
    <link rel="stylesheet" href="/admin/admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
    <script src="chats.js?v=<?= filemtime(__DIR__ . '/chats.js') ?>" defer></script>
</head>

<body>
    <main class="admin-shell">
        <?php require __DIR__ . '/navigation.php'; ?>
        <section class="admin-content" data-admin-chat-csrf="<?= appEscape($csrf) ?>">
            <h1>Чаты</h1>
            <p class="admin-chat-error" role="alert"><?= appEscape($error) ?></p>
            <div class="admin-chat-layout">
                <nav id="adminChatList" class="admin-chat-list" aria-label="Последние диалоги">
                    <?php if (!$conversations): ?><p>Диалогов пока нет.</p><?php endif; ?>
                    <?php foreach ($conversations as $row): ?>
                        <a class="admin-chat-list-item" href="chats.php?id=<?= (int) $row['id'] ?>#chatThread" <?= (int) $row['id'] === (int) $chatId ? 'aria-current="page"' : '' ?>>
                            <strong>#<?= (int) $row['id'] ?> <span class="admin-chat-badge is-<?= appEscape($row['status']) ?>"><?= appEscape($allowedStatuses[$row['status']] ?? $row['status']) ?></span></strong>
                            <span class="admin-chat-preview"><?= appEscape(mb_substr((string) $row['last_message'], 0, 90)) ?></span>
                            <?php if ((int) $row['unread_count'] > 0): ?><small class="admin-chat-unread">Новых сообщений: <?= (int) $row['unread_count'] ?></small><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div id="chatThread" class="admin-chat-thread" data-status="<?= appEscape($currentChat['status'] ?? '') ?>" data-chat-id="<?= $currentChat ? (int) $chatId : 0 ?>" data-csrf="<?= appEscape($csrf) ?>">
                    <?php if (!$currentChat): ?>
                        <p><?= $chatId ? 'Диалог не найден. Выберите другой из списка.' : 'Выберите диалог, чтобы открыть переписку.' ?></p>
                    <?php else: ?>
                        <a class="admin-chat-back" href="#adminChatList">К списку диалогов</a>
                        <h2>Диалог #<?= (int) $chatId ?></h2>
                        <p class="admin-chat-status"><?= appEscape($allowedStatuses[$currentChat['status']] ?? '') ?></p>
                        <div class="admin-chat-actions">
                            <?php foreach (['human' => 'Подключиться', 'bot' => 'Вернуть ассистенту', 'closed' => 'Завершить диалог'] as $status => $label): ?>
                                <form method="post" class="admin-chat-status-form">
                                    <input type="hidden" name="csrf_token" value="<?= appEscape($csrf) ?>">
                                    <input type="hidden" name="chat_id" value="<?= (int) $chatId ?>">
                                    <input type="hidden" name="action" value="status">
                                    <button name="value" value="<?= $status ?>" type="submit"><?= $label ?></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                        <div class="admin-chat-messages" role="log" aria-live="polite">
                            <?php foreach ($messages as $row): ?>
                                <div class="admin-chat-message is-<?= appEscape($row['sender']) ?>" data-message-id="<?= (int) $row['id'] ?>">
                                    <small><?= appEscape(['user' => 'Посетитель', 'bot' => 'Ассистент', 'admin' => 'Администратор'][$row['sender']] ?? '') ?> · <?= appEscape($row['created_at']) ?></small>
                                    <p><?= nl2br(appEscape($row['message'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" class="admin-reply-form">
                            <input type="hidden" name="csrf_token" value="<?= appEscape($csrf) ?>">
                            <input type="hidden" name="chat_id" value="<?= (int) $chatId ?>">
                            <input type="hidden" name="action" value="reply">
                            <label for="adminReply">Ваш ответ</label>
                            <textarea id="adminReply" name="value" maxlength="4000" required rows="3"></textarea>
                            <button type="submit">Отправить ответ</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</body>

</html>
