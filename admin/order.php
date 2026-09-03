<?php

declare(strict_types=1);

require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/auth.php';

requireAdmin();

$orderId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($orderId === false) {
    http_response_code(404);
    exit('Заказ не найден.');
}

$orderStatement = $pdo->prepare(
    'SELECT id, customer_name, phone, email, project_comment, total, status, created_at
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

$itemsStatement = $pdo->prepare(
    'SELECT service_name, tariff_name, price
     FROM order_items
     WHERE order_id = ?
     ORDER BY id'
);
$itemsStatement->execute([(int) $orderId]);
$items = $itemsStatement->fetchAll();

function adminOrderEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ №<?= (int) $order['id'] ?> | Админ-панель</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
<main class="cart-page">
    <div class="cart-page-title">
        <h1>Заказ №<?= (int) $order['id'] ?></h1>
        <p><?= adminOrderEscape($order['created_at']) ?></p>
        <a href="orders.php" class="cart-back-button">К списку заказов</a>
    </div>

    <section class="cart-products">
        <article class="cart-product">
            <div class="cart-product-info">
                <h2>Клиент</h2>
                <p><?= adminOrderEscape($order['customer_name']) ?></p>
                <p><?= adminOrderEscape($order['phone']) ?></p>
                <p><?= adminOrderEscape($order['email']) ?></p>
                <p><?= adminOrderEscape($order['project_comment']) ?></p>
                <p>Статус: <?= adminOrderEscape($order['status']) ?></p>
            </div>
            <div class="cart-product-actions">
                <strong><?= number_format((int) $order['total'], 0, '', ' ') ?> ₽</strong>
            </div>
        </article>

        <h2>Выбранные тарифы</h2>
        <?php foreach ($items as $item): ?>
            <article class="cart-product">
                <div class="cart-product-info">
                    <p><?= adminOrderEscape($item['service_name']) ?></p>
                    <h3><?= adminOrderEscape($item['tariff_name']) ?></h3>
                </div>
                <div class="cart-product-actions">
                    <strong><?= number_format((int) $item['price'], 0, '', ' ') ?> ₽</strong>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>