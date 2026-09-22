<?php

require_once __DIR__ . '/layout.php';

$title = 'Корзина | WebStart Studio';

?>

<!DOCTYPE html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($title) ?>
    </title>

    <link
        rel="stylesheet"
        href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">


    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=cursor-3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=cursor-3">
    <link rel="apple-touch-icon" href="assets/images/favicon-512.png?v=cursor-3">
</head>


<body>

    <?php renderHeader(); ?>

    <!-- =========================================
     КОРЗИНА
========================================= -->

    <main class="cart-page">


        <div class="cart-page-title">

            <h1>
                Корзина
            </h1>

            <p>
                Проверьте выбранные услуги перед оформлением заявки.
            </p>

        </div>



        <div class="cart-page-layout">


            <!-- =================================
             ВЫБРАННЫЕ УСЛУГИ
        ================================= -->

            <section class="cart-products">


                <div class="cart-products-header">

                    <h2>
                        Выбранные услуги
                    </h2>

                    <span id="cartItemsCount">
                        0 услуг
                    </span>

                </div>



                <div id="cartPageItems">

                    <!-- JavaScript добавит сюда товары -->

                </div>



                <div
                    class="cart-page-empty"
                    id="cartPageEmpty">

                    <div class="empty-cart-icon">
                        🛒
                    </div>

                    <h3>
                        Корзина пока пуста
                    </h3>

                    <p>
                        Перейдите к тарифам и выберите необходимые услуги.
                    </p>

                    <a
                        href="tariffs.php"
                        class="continue-shopping-button">
                        Перейти к тарифам
                    </a>

                </div>


            </section>



            <!-- =================================
             ИТОГ
        ================================= -->

            <aside class="cart-summary">
                <div class="cart-vega-constellation" tabindex="0" role="img" aria-label="Созвездие Лиры" aria-describedby="vega-nebula-note"></div>
                <svg class="cart-vega-pointer" aria-hidden="true" focusable="false"><path class="cart-vega-pointer__curve"/><path class="cart-vega-pointer__head"/></svg>
                <div class="cart-vega-nebula" id="vega-nebula-note" role="tooltip" aria-hidden="true">Созвездие Лиры — дом Веги, одной из самых ярких звёзд ночного неба.</div>



                <h2>
                    Ваш заказ
                </h2>



                <div class="summary-row">

                    <span>
                        Количество услуг
                    </span>

                    <strong id="summaryCount">
                        0
                    </strong>

                </div>



                <div class="summary-divider"></div>



                <div class="summary-total">

                    <span>
                        Итого
                    </span>

                    <strong id="cartPageTotal">
                        0 ₽
                    </strong>

                </div>



                <a
                    href="create-order.php"
                    class="cart-order-button"
                    id="cartOrderButton">
                    Оформить заявку
                </a>



                <a
                    href="tariffs.php"
                    class="cart-back-button">
                    Продолжить выбор
                </a>



                <button
                    type="button"
                    class="cart-clear-page"
                    id="cartClearPage">
                    Очистить корзину
                </button>


            </aside>


        </div>


    </main>




    <?php renderFooter(); ?>

    <script>
        let cart =
            JSON.parse(
                localStorage.getItem('webstartCart')
            ) || [];

        function getTariffId(item) {
            return String(item.tariff_id ?? item.id);
        }

        function getTariffName(item) {
            return item.tariff ?? item.name ?? '';
        }


        const cartPageItems =
            document.getElementById('cartPageItems');

        const cartPageEmpty =
            document.getElementById('cartPageEmpty');

        const cartPageTotal =
            document.getElementById('cartPageTotal');

        const cartCounter =
            document.getElementById('cartCounter');

        const cartItemsCount =
            document.getElementById('cartItemsCount');

        const summaryCount =
            document.getElementById('summaryCount');

        const cartClearPage =
            document.getElementById('cartClearPage');

        const cartOrderButton =
            document.getElementById('cartOrderButton');



        function formatPrice(price) {

            return new Intl.NumberFormat(
                'ru-RU'
            ).format(price) + ' ₽';

        }



        function saveCart() {

            localStorage.setItem(
                'webstartCart',
                JSON.stringify(cart)
            );

        }



        function updateCartPage() {


            /*
                СЧЁТЧИК
            */

            cartCounter.textContent =
                cart.length;

            summaryCount.textContent =
                cart.length;

            cartItemsCount.textContent =
                cart.length + ' шт.';



            /*
                ПУСТАЯ КОРЗИНА
            */

            if (cart.length === 0) {

                cartPageItems.innerHTML = '';

                cartPageEmpty.style.display =
                    'flex';

                cartOrderButton.classList.add(
                    'disabled'
                );

            } else {

                cartPageEmpty.style.display =
                    'none';

                cartOrderButton.classList.remove(
                    'disabled'
                );

                cartPageItems.innerHTML = '';



                cart.forEach(item => {


                    const element =
                        document.createElement('div');


                    element.classList.add(
                        'cart-product'
                    );


                    const info = document.createElement('div');
                    info.className = 'cart-product-info';

                    const service = document.createElement('span');
                    service.className = 'cart-product-service';
                    service.textContent = item.service || 'Услуга';

                    const title = document.createElement('h3');
                    title.textContent = getTariffName(item) || 'Тариф';

                    if (item.description) {
                        const description = document.createElement('p');
                        description.className = 'cart-product-description';
                        description.textContent = item.description;
                        info.append(service, title, description);
                    } else {
                        info.append(service, title);
                    }

                    const actions = document.createElement('div');
                    actions.className = 'cart-product-actions';

                    const price = document.createElement('strong');
                    price.textContent = formatPrice(Number(item.price) || 0);

                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'cart-product-remove';
                    removeButton.dataset.id = getTariffId(item);
                    removeButton.textContent = 'Удалить';

                    actions.append(price, removeButton);
                    element.append(info, actions);


                    cartPageItems.appendChild(
                        element
                    );


                });

            }



            /*
                ОБЩАЯ ЦЕНА
            */

            const total =
                cart.reduce(

                    (sum, item) =>
                    sum + item.price,

                    0

                );


            cartPageTotal.textContent =
                formatPrice(total);



            /*
                УДАЛЕНИЕ ОДНОЙ УСЛУГИ
            */

            document
                .querySelectorAll(
                    '.cart-product-remove'
                )
                .forEach(button => {


                    button.addEventListener(
                        'click',
                        function() {


                            const id =
                                this.dataset.id;


                            cart =
                                cart.filter(

                                    item =>
                                    getTariffId(item) !== String(id)

                                );


                            saveCart();

                            updateCartPage();


                        }
                    );


                });

        }



        /*
            ОЧИСТИТЬ ВСЁ
        */

        cartClearPage.addEventListener(
            'click',
            function() {

                cart = [];

                saveCart();

                updateCartPage();

            }
        );



        /*
            НЕ ДАЁМ ОФОРМЛЯТЬ
            ПУСТУЮ КОРЗИНУ
        */

        cartOrderButton.addEventListener(
            'click',
            function(event) {

                if (cart.length === 0) {

                    event.preventDefault();

                }

            }
        );



        updateCartPage();
    </script>


    <script src="cursor-stars.js?v=vega-nebula-4"></script>
</body>

</html>
