<?php
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/layout.php';

$title = 'Тарифы | WebStart Studio';

/*
    Получаем услуги из базы данных
*/

$servicesStatement = $pdo->query("

    SELECT

        id,
        name,
        slug,
        description

    FROM services

    WHERE active = 1

    ORDER BY id

");

$services =
    $servicesStatement->fetchAll();



/*
    Получаем тарифы из базы данных
*/

$tariffsStatement = $pdo->query("

    SELECT

        id,
        service_id,
        name,
        description,
        price

    FROM tariffs

    WHERE active = 1

    ORDER BY service_id, id

");


$tariffs =
    $tariffsStatement->fetchAll();



/*
    Разделяем тарифы по услугам
*/

$tariffsByService = [];


foreach ($tariffs as $tariff) {

    $serviceId =
        $tariff['service_id'];


    $tariffsByService[$serviceId][] =
        $tariff;
}

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
        href="styles.css?v=hero-title-white-1">

</head>


<body>

    <?php renderHeader(); ?>


    <!-- ============================
     СТРАНИЦА
============================ -->

    <main class="tariffs-page">


        <div class="tariffs-page-header">

            <h1>
                Наши тарифы
            </h1>

            <p>
                Выберите необходимые услуги для вашего проекта
            </p>

        </div>



        <div class="tariffs-layout">


            <!-- ============================
             ЛЕВАЯ ЧАСТЬ
             УСЛУГИ
        ============================ -->

            <div class="tariffs-content">


                <?php foreach ($services as $service): ?>


                    <section
                        class="service-tariffs"
                        id="<?= htmlspecialchars($service['slug']) ?>">


                        <!-- Название услуги -->

                        <div class="service-title">

                            <h2>
                                <?= htmlspecialchars($service['name']) ?>
                            </h2>

                            <p>
                                <?= htmlspecialchars($service['description']) ?>
                            </p>

                        </div>



                        <!-- Тарифы -->

                        <?php

                        $serviceTariffs =
                            $tariffsByService[$service['id']] ?? [];

                        ?>

                        <div
                            class="tariffs-grid"
                            style="--columns: 3;">


                            <?php foreach ($serviceTariffs as $tariff): ?>

                                <article class="tariff-card">


                                    <div>

                                        <h3>
                                            <?= htmlspecialchars($tariff['name']) ?>
                                        </h3>


                                        <p class="tariff-description">

                                            <?= htmlspecialchars(
                                                $tariff['description']
                                            ) ?>

                                        </p>

                                    </div>



                                    <div class="tariff-bottom">


                                        <p class="tariff-price">

                                            от

                                            <strong>

                                                <?= number_format(
                                                    $tariff['price'],
                                                    0,
                                                    '',
                                                    ' '
                                                ) ?>

                                                ₽

                                            </strong>

                                        </p>



                                        <!--
                                        Данные тарифа передаются
                                        JavaScript через data-*
                                    -->

                                        <button
                                            type="button"

                                            class="tariff-button"

                                            data-id="<?= htmlspecialchars(
                                                            $tariff['id']
                                                        ) ?>"

                                            data-service="<?= htmlspecialchars(
                                                                $service['name']
                                                            ) ?>"

                                            data-name="<?= htmlspecialchars(
                                                            $tariff['name']
                                                        ) ?>"

                                            data-price="<?= (int) $tariff['price'] ?>">

                                            Выбрать тариф

                                        </button>


                                    </div>


                                </article>


                            <?php endforeach; ?>


                        </div>


                    </section>


                <?php endforeach; ?>


            </div>



            <!-- ============================
             ПРАВАЯ ЧАСТЬ
             КОРЗИНА
        ============================ -->

            <aside
                class="cart"
                id="cart">


                <h2>
                    Корзина
                </h2>


                <p class="cart-subtitle">
                    Выбранные услуги
                </p>



                <!-- Сюда JS добавляет выбранные тарифы -->

                <div id="cartItems">

                    <p class="cart-empty">
                        Вы пока ничего не выбрали
                    </p>

                </div>



                <div class="cart-total">

                    <span>
                        Итого:
                    </span>

                    <strong id="cartTotal">
                        0 ₽
                    </strong>

                </div>



                <button
                    type="button"
                    class="checkout-button"
                    id="checkoutButton">
                    Оставить заявку
                </button>



                <button
                    type="button"
                    class="clear-cart-button"
                    id="clearCartButton">
                    Очистить корзину
                </button>


            </aside>


        </div>


    </main>



    <?php renderFooter(); ?>



    <!-- ============================
     JAVASCRIPT КОРЗИНЫ
============================ -->

    <script>
        /*
        Получаем сохранённую корзину.

        Благодаря localStorage выбранные услуги
        останутся даже после обновления страницы.
    */

        let cart = JSON.parse(
            localStorage.getItem('webstartCart')
        ) || [];

        function getTariffId(item) {
            return String(item.tariff_id ?? item.id);
        }

        function getTariffName(item) {
            return item.tariff ?? item.name ?? '';
        }



        const buttons =
            document.querySelectorAll('.tariff-button');

        const cartItems =
            document.getElementById('cartItems');

        const cartTotal =
            document.getElementById('cartTotal');

        const cartCounter =
            document.getElementById('cartCounter');

        const clearCartButton =
            document.getElementById('clearCartButton');



        /*
            Форматирование цены:

            15000 -> 15 000 ₽
        */

        function formatPrice(price) {

            return new Intl.NumberFormat('ru-RU').format(price) + ' ₽';

        }



        /*
            Сохраняем корзину
        */

        function saveCart() {

            localStorage.setItem(
                'webstartCart',
                JSON.stringify(cart)
            );

        }



        /*
            Проверяем, выбран ли тариф
        */

        function tariffIsSelected(id) {

            return cart.some(
                item => getTariffId(item) === String(id)
            );

        }



        /*
            Обновляем всё отображение корзины
        */

        function updateCart() {


            /*
                Счётчик около слова "Корзина"
            */

            cartCounter.textContent = cart.length;



            /*
                Если корзина пустая
            */

            if (cart.length === 0) {

                cartItems.innerHTML = `

                <p class="cart-empty">

                    Вы пока ничего не выбрали

                </p>

            `;

            } else {


                cartItems.innerHTML = '';


                /*
                    Создаём элемент для каждого тарифа
                */

                cart.forEach(item => {


                    const cartItem =
                        document.createElement('div');


                    cartItem.classList.add('cart-item');


                    cartItem.innerHTML = `

                    <div class="cart-item-info">

                        <span class="cart-item-service">
                            ${item.service}
                        </span>

                        <strong>
                            ${getTariffName(item)}
                        </strong>

                        <span class="cart-item-price">
                            ${formatPrice(item.price)}
                        </span>

                    </div>


                    <button
                        class="cart-remove"
                        data-id="${getTariffId(item)}"
                        type="button"
                    >
                        ×
                    </button>

                `;


                    cartItems.appendChild(cartItem);

                });

            }



            /*
                Подсчёт общей стоимости
            */

            const total = cart.reduce(

                (sum, item) => sum + item.price,

                0

            );


            cartTotal.textContent =
                formatPrice(total);



            /*
                Меняем состояние кнопок
            */

            buttons.forEach(button => {


                const id =
                    button.dataset.id;


                if (tariffIsSelected(id)) {


                    button.classList.add('selected');

                    button.textContent =
                        'Тариф выбран';

                } else {


                    button.classList.remove('selected');

                    button.textContent =
                        'Выбрать тариф';

                }

            });



            /*
                Кнопки удаления внутри корзины
            */

            document
                .querySelectorAll('.cart-remove')
                .forEach(removeButton => {


                    removeButton.addEventListener(
                        'click',
                        function() {


                            const id =
                                this.dataset.id;


                            cart = cart.filter(
                                item => getTariffId(item) !== String(id)
                            );


                            saveCart();

                            updateCart();

                        }
                    );

                });


        }



        /*
            Нажатие "Выбрать тариф"
        */

        buttons.forEach(button => {


            button.addEventListener(
                'click',
                function() {


                    const id =
                        this.dataset.id;


                    /*
                        Если тариф уже есть,
                        повторный клик удаляет его
                    */

                    if (tariffIsSelected(id)) {


                        cart = cart.filter(
                            item => getTariffId(item) !== String(id)
                        );


                    } else {


                        /*
                            Добавляем новый тариф
                        */

                        cart.push({

                            tariff_id: id,

                            service: this.dataset.service,

                            tariff: this.dataset.name,

                            price: Number(this.dataset.price)

                        });


                    }


                    saveCart();

                    updateCart();


                }
            );


        });



        /*
            Очистка корзины
        */

        clearCartButton.addEventListener(
            'click',
            function() {


                cart = [];


                saveCart();

                updateCart();


            }
        );



        /*
            Оставить заявку
        */

        updateCart();

        const checkoutButton =
            document.getElementById('checkoutButton');

        checkoutButton.addEventListener(
            'click',
            function() {

                window.location.href = 'cart.php';

            }
        );
    </script>


    <script src="cursor-stars.js?v=protected-hero-1"></script>
</body>

</html>
