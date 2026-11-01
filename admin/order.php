<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../services/PersonalDataService.php';

requireAdmin();

$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($orderId === false) {
    http_response_code(404);
    exit('Заказ не найден.');
}

$personalDataCsrf = appCsrfToken('admin_personal_data');
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
            personal_data_expires_at, personal_data_erased_at
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

$consentStatement = $pdo->prepare(
    'SELECT consent_version, privacy_policy_version, accepted_at, retention_until, withdrawn_at
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
</head>

<body>
    <main class="admin-shell">
        <aside class="admin-sidebar">
            <a href="index.php">Dashboard</a>
            <a href="orders.php">Заказы</a>
            <a href="chats.php">Чаты</a>
            <a href="services.php">Услуги</a>
            <a href="tariffs.php">Тарифы</a>
            <a href="logout.php">Выход</a>
        </aside>

        <section class="admin-content">
            <h1>Заказ №<?= (int) $order['id'] ?></h1>
            <p><?= appEscape($order['created_at']) ?></p>
            <a href="orders.php" class="cart-back-button">К списку заказов</a>

            <section class="cart-products">
                <article class="cart-product">
                    <div class="cart-product-info">
                        <span class="cart-product-service"><?= appEscape($order['status']) ?></span>
                        <h3>Клиент</h3>
                        <p><?= appEscape($order['customer_name']) ?></p>
                        <p><?= appEscape($order['phone']) ?></p>
                        <p>Срок хранения персональных данных: <?= appEscape((string) ($order['personal_data_expires_at'] ?? 'не задан')) ?></p>
                        <?php if ($consent): ?>
                            <p>Согласие: <?= appEscape($consent['consent_version']) ?>, принято <?= appEscape($consent['accepted_at']) ?></p>
                            <p>Версия политики: <?= appEscape($consent['privacy_policy_version']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($order['project_comment'])): ?><p><?= appEscape($order['project_comment']) ?></p><?php endif; ?>
                    </div>
                    <div class="cart-product-actions">
                        <strong><?= appMoney((int) $order['total']) ?></strong>
                    </div>
                </article>

                <?php if (empty($order['personal_data_erased_at'])): ?>
                    <form method="post" onsubmit="return confirm('Зафиксировать отзыв согласия и удалить контактные данные этой заявки?');">
                        <input type="hidden" name="csrf_token" value="<?= appEscape($personalDataCsrf) ?>">
                        <input type="hidden" name="action" value="withdraw_personal_data">
                        <button type="submit" class="cart-back-button">Зафиксировать отзыв и удалить персональные данные</button>
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
                            <strong><?= appMoney((int) $item['price']) ?></strong>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </section>
    </main>
</body>

</html>
