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
    [$customerErrors, $customer] = OrderService::validateCustomer($_POST, false);
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

    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=cursor-3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=cursor-3">
    <link rel="apple-touch-icon" href="assets/images/favicon-512.png?v=cursor-3">
</head>

<body>

    <?php renderHeader(); ?>

    <main class="cart-page checkout-page">
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
                <div id="emptyOrder" class="cart-page-empty">
                    <div class="checkout-empty-desktop">
                        <div class="empty-cart-icon" aria-hidden="true">🛒</div>
                        <h3>Корзина пока пуста</h3>
                        <p>Перейдите к тарифам и выберите необходимые услуги.</p>
                        <a href="tariffs.php" class="continue-shopping-button">Перейти к тарифам</a>
                    </div>
                    <div class="checkout-empty-mobile">
                        <div class="empty-cart-icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M4 6h4l4 15h13l3-11H9"/><circle cx="14" cy="26" r="1.5"/><circle cx="24" cy="26" r="1.5"/></svg></div>
                        <h3>Начнём с вашей задачи</h3>
                        <p>Выберите услугу — она появится здесь для оформления заявки.</p>
                        <a href="tariffs.php" class="continue-shopping-button">Выбрать услугу</a>
                    </div>
                </div>
                <a href="cart.php" class="checkout-edit">Изменить выбор →</a>
                <div class="checkout-sky" aria-hidden="false">
                    <div class="cart-vega-constellation" tabindex="0" role="img" aria-label="Созвездие Лиры" aria-describedby="vega-nebula-note"></div>
                </div>
            </section>

            <section class="cart-summary contact-form-wrapper">
                <h2>Обсудим ваш проект</h2>

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
                            <input type="text" id="customer_name" name="customer_name" autocomplete="name" placeholder="Как к вам обращаться?" value="<?= e($customerName) ?>" minlength="2" maxlength="150" required>
                        </div>

                        <div class="form-group">
                            <label for="customer_phone">Телефон</label>
                            <input type="tel" id="customer_phone" name="customer_phone" autocomplete="tel" placeholder="+7 (___) ___-__-__" value="<?= e($customerPhone) ?>" maxlength="30" required>
                        </div>



                        <div class="form-group">
                            <label for="project_comment">Комментарий</label>
                            <textarea id="project_comment" name="project_comment" placeholder="Расскажите о задаче и пожеланиях" maxlength="1000"><?= e($projectComment) ?></textarea>
                        </div>

                        <label class="form-consent">
                            <input type="checkbox" name="personal_data_consent" value="1" required>
                            <span>Я согласен на обработку персональных данных и ознакомлен с <a href="privacy.php" target="_blank">политикой конфиденциальности</a>.</span>
                        </label>

                        <button type="submit" class="contact-submit">Отправить заявку</button><p class="checkout-free-note">Обсуждение проекта бесплатно</p>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php renderFooter(); ?>
    <div class="cart-vega-nebula" id="vega-nebula-note" role="tooltip" aria-hidden="true">Созвездие Лиры — дом Веги, одной из самых ярких звёзд ночного неба.</div>
    <svg class="cart-vega-pointer" aria-hidden="true"><path class="cart-vega-pointer__curve"/><path class="cart-vega-pointer__head"/></svg>

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
                const row = document.createElement('div');
                row.className = 'cart-product';
                const info = document.createElement('div');
                info.className = 'cart-product-info';
                const service = document.createElement('span');
                service.className = 'cart-product-service';
                service.textContent = item.service ?? '';
                const name = document.createElement('h3');
                name.textContent = tariffName(item);
                const price = document.createElement('span');
                price.className = 'checkout-item-price';
                price.textContent = 'от ' + new Intl.NumberFormat('ru-RU').format(Number(item.price) || 0) + ' ₽';
                info.append(service, name);
                row.append(info, price);
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
    <script src="cursor-stars.js?v=canis-major-5"></script>
</body>

</html>
