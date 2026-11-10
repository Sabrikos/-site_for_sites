<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../services/PersonalDataService.php';
require_once __DIR__ . '/../services/OrderLifecycleService.php';
require_once __DIR__ . '/../services/OrderPricingService.php';

requireAdmin();

$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($orderId === false) {
    http_response_code(404);
    exit('Заказ не найден.');
}

$personalDataCsrf = appCsrfToken('admin_personal_data');
$lifecycleError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_price') {
    try {
        if (!appCsrfValid('admin_personal_data', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new DomainException('Обновите страницу и повторите действие.');
        }
        if (!is_string($_POST['agreed_price'] ?? null)) throw new DomainException('Укажите стоимость.');
        (new OrderPricingService($pdo))->save((int) $orderId, $_POST['agreed_price']);
        appRotateCsrf('admin_personal_data');
        header('Location: order.php?id=' . (int) $orderId, true, 303);
        exit;
    } catch (DomainException $exception) {
        $lifecycleError = $exception->getMessage();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['save_lifecycle', 'contact_today', 'link_chat', 'unlink_chat'], true)) {
    try {
        if (!appCsrfValid('admin_personal_data', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new DomainException('Обновите страницу и повторите действие.');
        }
        $lifecycle = new OrderLifecycleService($pdo);
        $action = $_POST['action'];
        if (in_array($action, ['link_chat', 'unlink_chat'], true)) {
            $chatId = filter_var($_POST['chat_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$chatId) throw new DomainException('Укажите номер диалога.');
            if ($action === 'link_chat') $lifecycle->linkChat((int) $orderId, (int) $chatId);
            else $lifecycle->unlinkChat((int) $orderId, (int) $chatId);
        } else {
            $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
            $contact = $action === 'contact_today' ? gmdate('Y-m-d H:i:s') : OrderLifecycleService::contactFromForm(is_string($_POST['last_contact_at'] ?? null) ? $_POST['last_contact_at'] : '');
            $lifecycle->update((int) $orderId, $status, $contact);
        }
        appRotateCsrf('admin_personal_data');
        header('Location: order.php?id=' . (int) $orderId, true, 303);
        exit;
    } catch (DomainException $exception) {
        $lifecycleError = $exception->getMessage();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'withdraw_personal_data') {
    if (!appCsrfValid('admin_personal_data', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        exit('Недействительный токен формы.');
    }
    PersonalDataService::withdrawAndErase($pdo, (int) $orderId, 'admin_request');
    appRotateCsrf('admin_personal_data');
    header('Location: order.php?id=' . (int) $orderId);
    exit;
}

$orderStatement = $pdo->prepare(
    'SELECT id, customer_name, phone, project_comment, total, status, created_at,
            personal_data_expires_at, personal_data_erased_at, last_contact_at, completed_at, closed_at, price_agreed_at
     FROM orders
     WHERE id = ?
     LIMIT 1'
);
$orderStatement->execute([(int) $orderId]);
$order = $orderStatement->fetch();

if (!$order) {
    http_response_code(404);
    exit('Заказ не найден.');
}

$itemsStatement = $pdo->prepare('SELECT service_name, tariff_name, price FROM order_items WHERE order_id = ? ORDER BY id');
$itemsStatement->execute([(int) $orderId]);
$items = $itemsStatement->fetchAll();
$linkedChatsStatement = $pdo->prepare('SELECT id FROM chat_conversations WHERE linked_order_id = ? ORDER BY id');
$linkedChatsStatement->execute([(int) $orderId]);
$linkedChats = $linkedChatsStatement->fetchAll(PDO::FETCH_COLUMN);
$contactLocal = $order['last_contact_at'] ? (new DateTimeImmutable($order['last_contact_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('Y-m-d\TH:i') : '';

$consentStatement = $pdo->prepare(
    'SELECT consent_version, privacy_policy_version, accepted_at, retention_until, withdrawn_at, consent_expires_at, consent_ended_at
     FROM personal_data_consents WHERE order_id = ? ORDER BY id DESC LIMIT 1'
);
$consentStatement->execute([(int) $orderId]);
$consent = $consentStatement->fetch();

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ №<?= (int) $order['id'] ?> | Vega Studio</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="/admin/admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
</head>

<body>
    <main class="admin-shell">
        <?php require __DIR__ . '/navigation.php'; ?>

        <section class="admin-content">
            <h1>Заказ №<?= (int) $order['id'] ?></h1>
            <p><?= appEscape($order['created_at']) ?></p>
            <a href="orders.php" class="cart-back-button">К списку заказов</a>
            <?php if ($lifecycleError !== ''): ?><p class="errors" role="alert"><?= appEscape($lifecycleError) ?></p><?php endif; ?>

            <?php if (empty($order['personal_data_erased_at'])): ?>
            <section class="cart-products admin-order-lifecycle">
                <h2>Работа с обращением</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= appEscape($personalDataCsrf) ?>">
                    <label for="orderStatus">Статус</label>
                    <select id="orderStatus" name="status">
                        <?php foreach (RetentionPolicy::STATUSES as $value => $label): ?>
                        <option value="<?= appEscape($value) ?>" <?= $order['status'] === $value ? 'selected' : '' ?>><?= appEscape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="lastContactAt">Последнее содержательное общение (московское время)</label>
                    <input type="datetime-local" id="lastContactAt" name="last_contact_at" value="<?= appEscape($contactLocal) ?>" max="<?= appEscape((new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d\TH:i')) ?>">
                    <div class="admin-order-lifecycle-actions">
                        <button type="submit" name="action" value="save_lifecycle">Сохранить</button>
                        <button type="submit" name="action" value="contact_today">Отметить контакт сегодня</button>
                    </div>
                </form>
                <?php if ($order['completed_at']): ?><p>Дата завершения (UTC): <?= appEscape($order['completed_at']) ?></p><?php endif; ?>
                <?php if ($order['closed_at']): ?><p>Закрыто без заказа (UTC): <?= appEscape($order['closed_at']) ?></p><?php endif; ?>
                <?php if (RetentionPolicy::protected($order['status'])): ?>
                    <p>Общая очистка консультаций не применяется. Нужно отдельно определить основание, состав и срок хранения документов заказа. Это не разрешение на бессрочное хранение.</p>
                <?php else: ?>
                    <p>Удаление контактных данных запланировано на <?= appEscape((string) $order['personal_data_expires_at']) ?> UTC. При закрытии без заказа — не позднее 30 дней, если текущий срок не истекает раньше.</p>
                <?php endif; ?>
                <h3>Переписка по заказу</h3>
                <p>Привязывайте только диалоги, необходимые для выполнения заказа и подтверждения договорённостей.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= appEscape($personalDataCsrf) ?>">
                    <label for="linkedChatId">Номер диалога из раздела «Чаты»</label>
                    <input id="linkedChatId" name="chat_id" type="number" min="1" required>
                    <button type="submit" name="action" value="link_chat">Связать с заказом</button>
                </form>
                <?php foreach ($linkedChats as $linkedChat): ?>
                    <form method="post" class="admin-order-lifecycle-actions">
                        <input type="hidden" name="csrf_token" value="<?= appEscape($personalDataCsrf) ?>">
                        <input type="hidden" name="chat_id" value="<?= (int) $linkedChat ?>">
                        <a href="chats.php?id=<?= (int) $linkedChat ?>">Диалог №<?= (int) $linkedChat ?></a>
                        <button name="action" value="unlink_chat" type="submit">Отвязать</button>
                    </form>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <section class="cart-products">
                <article class="cart-product">
                    <div class="cart-product-info">
                        <span class="cart-product-service"><?= appEscape(RetentionPolicy::STATUSES[$order['status']] ?? $order['status']) ?></span>
                        <h3>Клиент</h3>
                        <p><?= appEscape($order['customer_name']) ?></p>
                        <p><?= appEscape($order['phone']) ?></p>
                        <p>Срок хранения персональных данных: <?= appEscape((string) ($order['personal_data_expires_at'] ?? 'не задан')) ?></p>
                        <?php if ($consent): ?>
                            <p>Согласие: <?= appEscape($consent['consent_version']) ?>, принято <?= appEscape($consent['accepted_at']) ?></p>
                            <p>Версия политики: <?= appEscape($consent['privacy_policy_version']) ?></p>
                            <p>Согласие действует до: <?= appEscape((string) ($consent['consent_ended_at'] ?: $consent['consent_expires_at'])) ?><?= $consent['consent_ended_at'] ? ' (действие прекращено)' : '' ?></p>
                            <p>Подтверждение согласия хранится до: <?= appEscape($consent['retention_until']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['project_comment'])): ?><p><?= appEscape($order['project_comment']) ?></p><?php endif; ?>
                    </div>
                    <div class="cart-product-actions">
                        <strong><?= $order['total'] === null ? 'Стоимость не согласована' : appMoney((int) $order['total']) ?></strong>
                    </div>
                </article>

                <?php if (empty($order['personal_data_erased_at'])): ?>
                    <form method="post" class="admin-order-pricing">
                        <input type="hidden" name="csrf_token" value="<?= appEscape($personalDataCsrf) ?>">
                        <input type="hidden" name="action" value="save_price">
                        <label for="agreedPrice">Согласованная стоимость, ₽</label>
                        <input type="number" id="agreedPrice" name="agreed_price" min="0" max="4294967295" step="1" value="<?= appEscape((string) ($order['total'] ?? '')) ?>" placeholder="Стоимость не согласована">
                        <p>Укажите итоговую сумму после согласования с клиентом. Пустое поле означает, что стоимость не согласована.</p>
                        <?php if ($order['price_agreed_at']): ?><p>Стоимость сохранена: <?= appEscape($order['price_agreed_at']) ?> (UTC)</p><?php endif; ?>
                        <button type="submit">Сохранить стоимость</button>
                    </form>
                    <?php if (RetentionPolicy::protected($order['status'])): ?><p>Перед удалением по отзыву отдельно проверьте, какие документы необходимо сохранить для исполнения договора или по закону. Общее удаление ниже также удаляет привязанную переписку.</p><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Зафиксировать отзыв согласия, удалить контактные данные и привязанную переписку этой заявки?');">
                        <input type="hidden" name="csrf_token" value="<?= appEscape($personalDataCsrf) ?>">
                        <input type="hidden" name="action" value="withdraw_personal_data">
                        <button type="submit" class="cart-back-button">Зафиксировать отзыв и удалить данные со связанной перепиской</button>
                    </form>
                <?php else: ?>
                    <p>Персональные данные удалены: <?= appEscape($order['personal_data_erased_at']) ?></p>
                <?php endif; ?>

                <h2>Выбранные тарифы</h2>
                <?php foreach ($items as $item): ?>
                    <article class="cart-product">
                        <div class="cart-product-info">
                            <span class="cart-product-service"><?= appEscape($item['service_name']) ?></span>
                            <h3><?= appEscape($item['tariff_name']) ?></h3>
                        </div>
                        <div class="cart-product-actions">
                            <?php if ($item['price'] !== null): ?><strong><?= appMoney((int) $item['price']) ?></strong><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </section>
    </main>
</body>

</html>
