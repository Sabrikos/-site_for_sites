<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/auth.php';

requireAdmin();
appCsrfToken('admin_csrf');

$allowedStatuses = ['new', 'processing', 'completed'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = (string) ($_POST['status'] ?? '');

    if (!appCsrfValid('admin_csrf', $token)) {
        $error = 'Срок действия формы истёк.';
    } elseif ($orderId === false || !in_array($status, $allowedStatuses, true)) {
        $error = 'Некорректные данные статуса.';
    } else {
        $statement = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $statement->execute([$status, (int) $orderId]);
        $message = 'Статус заказа обновлён.';
        appRotateCsrf('admin_csrf');
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
        GROUP_CONCAT(CONCAT(oi.service_name, ": ", oi.tariff_name, " (", oi.price, " ₽)") SEPARATOR ", ") AS items
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY o.id, o.customer_name, o.phone, o.email, o.project_comment, o.total, o.status, o.created_at
     ORDER BY o.created_at DESC, o.id DESC'
);
$orders = $statement->fetchAll();

function adminOrderStatusLabel(string $status): string
{
    return match ($status) {
        'new' => 'Новый',
        'processing' => 'В работе',
        'completed' => 'Завершён',
        default => $status,
    };
}

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказы | Vega Studio</title>
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
            <h1>Заказы</h1>
            <p>Администратор: <?= appEscape($_SESSION['admin_username'] ?? '') ?></p>

            <?php if ($message !== ''): ?><div class="success"><?= appEscape($message) ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="errors"><?= appEscape($error) ?></div><?php endif; ?>

            <section class="cart-products">
                <?php if ($orders === []): ?>
                    <p>Заказов пока нет.</p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <article class="cart-product">
                            <div class="cart-product-info">
                                <span class="cart-product-service"><?= appEscape(adminOrderStatusLabel($order['status'])) ?></span>
                                <h3><a href="order.php?id=<?= (int) $order['id'] ?>">Заказ №<?= (int) $order['id'] ?></a></h3>
                                <p><?= appEscape($order['customer_name']) ?></p>
                                <p><?= appEscape($order['phone']) ?>, <?= appEscape($order['email']) ?></p>
                                <p><?= appEscape($order['items']) ?></p>
                                <?php if (!empty($order['project_comment'])): ?><p><?= appEscape($order['project_comment']) ?></p><?php endif; ?>
                                <small><?= appEscape($order['created_at']) ?></small>
                            </div>
                            <div class="cart-product-actions">
                                <strong><?= appMoney((int) $order['total']) ?></strong>
                                <form method="post" class="admin-inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= appEscape(appCsrfToken('admin_csrf')) ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <select name="status">
                                        <?php foreach ($allowedStatuses as $status): ?>
                                            <option value="<?= appEscape($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= appEscape(adminOrderStatusLabel($status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Сохранить</button>
                                    <a href="order.php?id=<?= (int) $order['id'] ?>">Открыть</a>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </section>
    </main>
</body>

</html>