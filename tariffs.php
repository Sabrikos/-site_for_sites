<?php
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/services/TariffPresentation.php';

$title = 'Тарифы | Vega Studio';

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
        href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">

    <script src="assets/vendor/gsap.min.js?v=3.15.0" defer></script>
    <script src="assets/vendor/ScrollTrigger.min.js?v=3.15.0" defer></script>
    <script src="assets/vendor/lenis.min.js?v=1.3.27" defer></script>
    <script src="assets/vendor/motion.js?v=14.6.0" defer></script>
    <script src="animation-runtime.js?v=1" defer></script>


    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=cursor-3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=cursor-3">
    <link rel="apple-touch-icon" href="assets/images/favicon-512.png?v=cursor-3">
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


        </div>



        <aside class="tariffs-price-note" aria-labelledby="tariffs-price-note-title">
            <h2 id="tariffs-price-note-title">Каждый проект начинается с задачи</h2>
            <p>Цены в тарифах — ориентир. После обсуждения согласуем объём работ и итоговую стоимость с учётом ваших целей и бюджета.</p>
        </aside>

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
                            <span class="tariff-section-icon"><?= tariffIcon($service['slug']) ?></span>
                            <div>

                            <h2>
                                <?= htmlspecialchars($service['name']) ?>
                            </h2>

                            <p>
                                <?= htmlspecialchars($service['description']) ?>
                            </p>
                            </div>

                        </div>



                        <!-- Тарифы -->

                        <?php

                        $serviceTariffs =
                            $tariffsByService[$service['id']] ?? [];

                        ?>

                        <div
                            class="tariffs-grid"
                            style="--columns: 3;">


                            <?php foreach ($serviceTariffs as $tariffIndex => $tariff): ?>
                                <?php $presentation = tariffPresentation($service['slug'], $tariff['name']); ?>

                                <article class="tariff-card">


                                    <div class="tariff-intro">
                                        <span class="tariff-tier-icon"><?= tariffIcon(['start', 'business', 'premium'][$tariffIndex] ?? 'other') ?></span>

                                        <h3>
                                            <?= htmlspecialchars($tariff['name']) ?>
                                        </h3>


                                        <p class="tariff-description">

                                            <span class="tariff-description-desktop"><?= htmlspecialchars(
                                                $presentation[0] ?? $tariff['description']
                                            ) ?></span>
                                            <span class="tariff-description-mobile"><?= htmlspecialchars(tariffMobileDescription($tariff['name'], $service['slug']) ?? $presentation[0] ?? $tariff['description']) ?></span>

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

                            <?php if ($service['slug'] === 'other'): ?>
                                <div class="other-project-info" aria-labelledby="other-project-heading">
                                    <h3 id="other-project-heading">Что можно заказать?</h3>
                                    <ul class="other-project-options">
                                        <li>Telegram-боты</li>
                                        <li>AI-интеграции</li>
                                        <li>CRM-доработки</li>
                                        <li>Парсеры</li>
                                        <li>Личный кабинет</li>
                                        <li>Нестандартный функционал</li>
                                    </ul>
                                    <p class="other-project-message"><strong>Не нашли подходящий тариф?</strong><br>Соберём решение под вашу задачу.</p>
                                    <a class="other-project-cta" href="index.php#application">Обсудить задачу <span aria-hidden="true">↗</span></a>
                                </div>
                            <?php endif; ?>

                        </div>


                        <?php if ($service['slug'] === 'other'): ?>
                            <img class="tariffs-alien" src="assets/images/tariffs-alien.png" width="1244" height="1264" alt="" aria-hidden="true" loading="lazy" draggable="false">
                        <?php endif; ?>
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
                <div class="tariff-cart-heading">
                <span class="tariff-basket-icon" aria-hidden="true"><?= tariffIcon('shop') ?></span>
                <div>


                <h2>
                    Корзина
                </h2>


                <p class="cart-subtitle">
                    Выбранные услуги
                </p>
                </div>
                </div>



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

            <div class="tariffs-ursa-minor" role="img" tabindex="0" aria-label="Созвездие Малой Медведицы, Полярная звезда на конце ручки ковша" aria-describedby="ursa-minor-note"></div>
            <div class="tariffs-cassiopeia" role="img" tabindex="0" aria-label="Созвездие Кассиопеи" aria-describedby="cassiopeia-note"></div>
            <div class="tariffs-ursa-major" role="img" tabindex="0" aria-label="Созвездие Большой Медведицы" aria-describedby="ursa-major-note"></div>
            <div class="tariffs-reference-constellation" role="img" tabindex="0" aria-label="Созвездие Ориона" aria-describedby="reference-constellation-note"></div>


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
                    const itemInfo = document.createElement('div');
                    itemInfo.className = 'cart-item-info';

                    const service = document.createElement('span');
                    service.className = 'cart-item-service';
                    service.textContent = item.service ?? '';

                    const title = document.createElement('strong');
                    title.textContent = getTariffName(item);

                    const price = document.createElement('span');
                    price.className = 'cart-item-price';
                    price.textContent = formatPrice(item.price);

                    const removeButton = document.createElement('button');
                    removeButton.className = 'cart-remove';
                    removeButton.dataset.id = getTariffId(item);
                    removeButton.type = 'button';
                    removeButton.textContent = '×';
                    removeButton.setAttribute('aria-label', `Удалить: ${item.service ?? ''} — ${getTariffName(item)}`);
                    removeButton.title = 'Удалить из корзины';

                    itemInfo.append(service, title, price);
                    cartItem.append(itemInfo, removeButton);
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


    <div class="cart-vega-nebula tariffs-ursa-nebula" id="ursa-minor-note" role="tooltip" aria-hidden="true">Малая медведица</div>
    <div class="cart-vega-nebula tariffs-cassiopeia-nebula" id="cassiopeia-note" role="tooltip" aria-hidden="true">Кассиопея</div>
    <div class="cart-vega-nebula tariffs-ursa-major-nebula" id="ursa-major-note" role="tooltip" aria-hidden="true">Большая медведица</div>
    <div class="cart-vega-nebula tariffs-reference-nebula" id="reference-constellation-note" role="tooltip" aria-hidden="true">Орион</div>
    <script src="cursor-stars.js?v=constellations-7"></script>
</body>

</html>
