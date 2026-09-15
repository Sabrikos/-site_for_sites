<?php

declare(strict_types=1);

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/services/OrderService.php';

appStartSession();

$csrfToken = appCsrfToken('order_csrf');
if (empty($_SESSION['order_request_token'])) {
    $_SESSION['order_request_token'] = bin2hex(random_bytes(32));
}

$title = 'Оформление заявки | Vega Studio';
$errors = [];
$successOrderId = null;
$customerName = trim((string) ($_POST['customer_name'] ?? ''));
$customerPhone = trim((string) ($_POST['customer_phone'] ?? ''));
$customerEmail = trim((string) ($_POST['customer_email'] ?? ''));
$projectComment = trim((string) ($_POST['project_comment'] ?? ''));
$personalDataConsent = isset($_POST['personal_data_consent']);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!appCsrfValid('order_csrf', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        $errors[] = 'Срок действия формы истёк. Обновите страницу и повторите отправку.';
    }
    [$customerErrors, $customer] = OrderService::validateCustomer($_POST);
    $errors = array_merge($errors, $customerErrors);

    if (!$personalDataConsent) {
        $errors[] = 'Подтвердите согласие на обработку персональных данных.';
    }

    $cart = json_decode(
        (string) ($_POST['cart_json'] ?? ''),
        true
    );

    if (!is_array($cart) || $cart === []) {
        $errors[] = 'Корзина пуста.';
    } elseif (count($cart) > OrderService::MAX_ITEMS) {
        $errors[] = 'В одном заказе можно оформить не более ' . OrderService::MAX_ITEMS . ' тарифов.';
        $cart = [];
    }

    $tariffIds = [];

    foreach ($cart as $item) {
        $tariffId = filter_var(
            $item['tariff_id'] ?? $item['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($tariffId === false) {
            $errors[] = 'В корзине найден некорректный тариф.';
            continue;
        }

        $tariffIds[] = (int) $tariffId;
    }

    $tariffIds = array_values(array_unique($tariffIds));

    if ($errors === [] && $tariffIds === []) {
        $errors[] = 'Не удалось определить тарифы заказа.';
    }
    $requestToken = is_string($_POST['request_token'] ?? null) ? $_POST['request_token'] : '';
    if (!OrderService::validRequestToken($requestToken) || !hash_equals($_SESSION['order_request_token'], $requestToken)) {
        $errors[] = 'Форма устарела. Обновите страницу и повторите отправку.';
    }

    if ($errors === []) {
        if (!appPersistentRateLimit($pdo, 'order:' . appClientIp(), 6, 600)) {
            $errors[] = 'Слишком много заявок с этого адреса. Попробуйте позже.';
        }
    }
    if ($errors === []) {
        try {
            $result = (new OrderService($pdo))->createCartOrder($customer, $tariffIds, $requestToken);
            $successOrderId = $result['id'];
            appRotateCsrf('order_csrf');
            $_SESSION['order_request_token'] = bin2hex(random_bytes(32));

            if ($result['created']) {
                try {
                    appSendTelegram(
                '<b>🚀 НОВАЯ ЗАЯВКА Vega Studio</b>\n'
                    . 'Источник: 🌐 Сайт\n'
                    . 'Заказ: #' . $successOrderId . "\n"
                    . 'Клиент: ' . appTelegramEscape($customerName) . "\n"
                    . 'Телефон: ' . appTelegramEscape($customerPhone) . "\n"
                    . 'Email: ' . appTelegramEscape($customerEmail) . "\n"
                    . 'Сумма: ' . number_format((int) $result['total'], 0, '', ' ') . ' ₽',
                '📋 Открыть заказ',
                appUrl('admin/order.php?id=' . $successOrderId),
                    );
                } catch (Throwable $error) {
                    appLog('Order notification failed after commit', ['order_id' => $successOrderId, 'type' => get_class($error)]);
                }
            }
        } catch (Throwable $error) {
            appLog('Order create failed', ['type' => get_class($error)]);
            $errors[] = 'Не удалось сохранить заказ. Попробуйте ещё раз.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">
</head>

<body>

    <?php renderHeader(); ?>

    <main class="cart-page">
        <div class="cart-page-title">
            <h1>Оформление заявки</h1>
            <p>Оставьте контакты, и мы свяжемся с вами.</p>
        </div>

        <div class="cart-page-layout">
            <section class="cart-products">
                <div class="cart-products-header">
                    <h2>Выбранные услуги</h2>
                </div>
                <div id="orderItems"></div>
                <p id="emptyOrder" class="cart-page-empty">Корзина пуста.</p>
            </section>

            <section class="cart-summary contact-form-wrapper">
                <h2>Данные клиента</h2>

                <?php if ($errors !== []): ?>
                    <div class="errors">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($successOrderId !== null): ?>
                    <div class="success">
                        Заявка №<?= $successOrderId ?> сохранена. Мы свяжемся с вами.
                    </div>
                    <a href="tariffs.php" class="cart-back-button">Вернуться к тарифам</a>
                <?php else: ?>
                    <form method="post" id="orderForm">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="request_token" value="<?= e($_SESSION['order_request_token']) ?>">
                        <input type="hidden" name="cart_json" id="cartJson">

                        <div class="form-group">
                            <label for="customer_name">Имя</label>
                            <input type="text" id="customer_name" name="customer_name" value="<?= e($customerName) ?>" minlength="2" maxlength="150" required>
                        </div>

                        <div class="form-group">
                            <label for="customer_phone">Телефон</label>
                            <input type="tel" id="customer_phone" name="customer_phone" value="<?= e($customerPhone) ?>" maxlength="30" required>
                        </div>

                        <div class="form-group">
                            <label for="customer_email">Email</label>
                            <input type="email" id="customer_email" name="customer_email" value="<?= e($customerEmail) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="project_comment">Комментарий</label>
                            <textarea id="project_comment" name="project_comment" maxlength="1000"><?= e($projectComment) ?></textarea>
                        </div>

                        <label class="form-consent">
                            <input type="checkbox" name="personal_data_consent" value="1" required>
                            <span>Я согласен на обработку персональных данных и ознакомлен с <a href="privacy.php" target="_blank">политикой конфиденциальности</a>.</span>
                        </label>

                        <button type="submit" class="contact-submit">Отправить заявку</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php renderFooter(); ?>

    <script>
        const orderForm = document.getElementById('orderForm');
        const cart = JSON.parse(localStorage.getItem('webstartCart') || '[]');
        const orderItems = document.getElementById('orderItems');
        const emptyOrder = document.getElementById('emptyOrder');

        function tariffName(item) {
            return item.tariff ?? item.name ?? '';
        }

        if (orderForm) {
            document.getElementById('cartJson').value = JSON.stringify(cart);
        }

        if (cart.length > 0) {
            emptyOrder.style.display = 'none';

            cart.forEach(item => {
                const row = document.createElement('p');
                row.textContent = `${item.service ?? ''}: ${tariffName(item)}`;
                orderItems.appendChild(row);
            });
        }

        <?php if ($successOrderId !== null): ?>
            localStorage.removeItem('webstartCart');
            if (typeof updateHeaderCartCounter === 'function') {
                updateHeaderCartCounter();
            }
            if (orderItems) {
                orderItems.innerHTML = '';
            }
            if (emptyOrder) {
                emptyOrder.style.display = 'block';
            }
        <?php endif; ?>
    </script>
    <script src="cursor-stars.js"></script>
</body>

</html>
