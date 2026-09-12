<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/ChatService.php';

appEnsureAssistantTables($pdo);
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
        $chatService->adminAction($postId, (int) $_SESSION['admin_id'], $action, (string) ($_POST['value'] ?? ''));
        header('Location: chats.php?id=' . $postId, true, 303);
        exit;
    } catch (Throwable $exception) {
        $error = $exception instanceof DomainException ? $exception->getMessage() : 'Не удалось выполнить действие.';
    }
}
$csrf = appCsrfToken('admin_csrf');
$conversations = $pdo->query('SELECT c.id, c.status, c.updated_at,
    (SELECT message FROM chat_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_message
    FROM chat_conversations c ORDER BY c.updated_at DESC, c.id DESC LIMIT 50')->fetchAll();
$currentChat = null;
$messages = [];
if ($chatId) {
    $statement = $pdo->prepare('SELECT * FROM chat_conversations WHERE id = ?');
    $statement->execute([$chatId]);
    $currentChat = $statement->fetch();
    if ($currentChat) {
        $chatService->adminAction($chatId, (int) $_SESSION['admin_id'], 'poll');
        $messages = $chatService->snapshot($chatId)['messages'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чаты | WebStart Studio</title>
    <link rel="stylesheet" href="../styles.css?v=<?= filemtime(__DIR__ . '/../styles.css') ?>">
    <script src="chats.js?v=<?= filemtime(__DIR__ . '/chats.js') ?>" defer></script>
</head>
<body>
<main class="admin-shell">
    <aside class="admin-sidebar">
        <a href="index.php">Обзор</a><a href="orders.php">Заказы</a>
        <a href="chats.php" aria-current="page">Чаты</a><a href="faq.php">База знаний</a>
        <a href="services.php">Услуги</a><a href="tariffs.php">Тарифы</a><a href="logout.php">Выход</a>
    </aside>
    <section class="admin-content">
        <h1>Чаты</h1>
        <p class="admin-chat-error" role="alert"><?= appEscape($error) ?></p>
        <div class="admin-chat-layout">
            <div class="admin-chat-list">
                <?php if (!$conversations): ?><p>Диалогов пока нет.</p><?php endif; ?>
                <?php foreach ($conversations as $row): ?>
                    <a class="admin-chat-list-item" href="chats.php?id=<?= (int) $row['id'] ?>" <?= (int) $row['id'] === (int) $chatId ? 'aria-current="page"' : '' ?>>
                        <strong>#<?= (int) $row['id'] ?> · <?= appEscape($allowedStatuses[$row['status']] ?? $row['status']) ?></strong>
                        <span><?= appEscape(mb_substr((string) $row['last_message'], 0, 90)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="admin-chat-thread" data-chat-id="<?= $currentChat ? (int) $chatId : 0 ?>" data-csrf="<?= appEscape($csrf) ?>">
                <?php if (!$currentChat): ?>
                    <p>Выберите диалог.</p>
                <?php else: ?>
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
