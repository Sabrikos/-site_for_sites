<?php

declare(strict_types=1);

require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/auth.php';

requireAdmin();

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$allowedStatuses = [
    'new',
    'processing',
    'completed',
];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $orderId = filter_var(
        $_POST['order_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $status = (string) ($_POST['status'] ?? '');

    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        $error = 'Срок действия формы истёк.';
    } elseif ($orderId === false || !in_array($status, $allowedStatuses, true)) {
        $error = 'Некорректные данные статуса.';
    } else {
        $statement = $pdo->prepare(
            'UPDATE orders SET status = ? WHERE id = ?'
        );
        $statement->execute([$status, $orderId]);
        $message = 'Статус заказа обновлён.';
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
}

$statement = $pdo->query(
    'SELECT
        o.id,
        o.customer_name,
        o.phone,
        o.email,
        o.project_comment,
        o.total,
        o.status,
        o.created_at,
        GROUP_CONCAT(
            CONCAT(oi.service_name, ": ", oi.tariff_name, " (", oi.price, " ₽)")
            SEPARATOR ", "
        ) AS items
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY
        o.id, o.customer_name, o.phone, o.email,
        o.project_comment, o.total, o.status, o.created_at
     ORDER BY o.created_at DESC, o.id DESC'
);
$orders = $statement->fetchAll();

function adminOrdersEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказы | Админ-панель</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
<main class="cart-page">
    <div class="cart-page-title">
        <h1>Заказы</h1>
        <p>Администратор: <?= adminOrdersEscape($_SESSION['admin_username'] ?? '') ?></p>
        <a href="logout.php">Выйти</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="success"><?= adminOrdersEscape($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="errors"><?= adminOrdersEscape($error) ?></div>
    <?php endif; ?>

    <section class="cart-products">
        <?php if ($orders === []): ?>
            <p>Заказов пока нет.</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <article class="cart-product">
                    <div class="cart-product-info">
                        <h2>Заказ №<?= (int) $order['id'] ?></h2>
                        <p><?= adminOrdersEscape($order['customer_name']) ?></p>
                        <p><?= adminOrdersEscape($order['phone']) ?>, <?= adminOrdersEscape($order['email']) ?></p>
                        <p><?= adminOrdersEscape($order['items']) ?></p>
                        <p><?= adminOrdersEscape($order['project_comment']) ?></p>
                        <small><?= adminOrdersEscape($order['created_at']) ?></small>
                    </div>
                    <div class="cart-product-actions">
                        <strong><?= number_format((int) $order['total'], 0, '', ' ') ?> ₽</strong>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= adminOrdersEscape($_SESSION['admin_csrf']) ?>">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <select name="status">
                                <?php foreach ($allowedStatuses as $status): ?>
                                    <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>>
                                        <?= $status === 'new' ? 'Новый' : ($status === 'processing' ? 'В работе' : 'Завершён') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Сохранить</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
