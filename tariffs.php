<?php

$title = 'Тарифы | WebStart Studio';

$services = [

    [
        'name' => 'Лендинг',
        'slug' => 'landing',
        'columns' => 3,

        'description' =>
        'Одностраничные сайты для продвижения товара, услуги или компании.',

        'tariffs' => [

            [
                'id' => 'landing-start',
                'name' => 'Старт',
                'description' =>
                'Простой лендинг для небольшой услуги или начинающего бизнеса.',
                'price' => 15000,
            ],

            [
                'id' => 'landing-business',
                'name' => 'Бизнес',
                'description' =>
                'Расширенный лендинг с дополнительными блоками, формами и анимациями.',
                'price' => 22000,
            ],

            [
                'id' => 'landing-premium',
                'name' => 'Премиум',
                'description' =>
                'Индивидуальный лендинг с уникальным дизайном и расширенным функционалом.',
                'price' => 30000,
            ],

        ],
    ],


    [
        'name' => 'Интернет-магазин',
        'slug' => 'shop',
        'columns' => 3,

        'description' =>
        'Сайты для продажи товаров через интернет.',

        'tariffs' => [

            [
                'id' => 'shop-start',
                'name' => 'Старт',
                'description' =>
                'Небольшой каталог товаров, корзина и форма оформления заказа.',
                'price' => 30000,
            ],

            [
                'id' => 'shop-business',
                'name' => 'Бизнес',
                'description' =>
                'Полноценный интернет-магазин с каталогом, корзиной и онлайн-оплатой.',
                'price' => 45000,
            ],

            [
                'id' => 'shop-premium',
                'name' => 'Премиум',
                'description' =>
                'Большой интернет-магазин с индивидуальным дизайном и интеграциями.',
                'price' => 65000,
            ],

        ],
    ],


    [
        'name' => 'Доработка сайта',
        'slug' => 'revision',
        'columns' => 3,

        'description' =>
        'Исправление ошибок и добавление нового функционала на существующий сайт.',

        'tariffs' => [

            [
                'id' => 'revision-small',
                'name' => 'Мелкая доработка',
                'description' =>
                'Изменение текста, дизайна или отдельных элементов сайта.',
                'price' => 5000,
            ],

            [
                'id' => 'revision-business',
                'name' => 'Расширенная доработка',
                'description' =>
                'Добавление новых страниц, блоков и функций.',
                'price' => 10000,
            ],

            [
                'id' => 'revision-complex',
                'name' => 'Комплексная доработка',
                'description' =>
                'Большое обновление структуры, дизайна и функциональности сайта.',
                'price' => 20000,
            ],

        ],
    ],


    /*
        ============================
        ИИ-РЕШЕНИЯ
        ============================
    */

    [
        'name' => 'ИИ-решения',
        'slug' => 'ai',
        'columns' => 3,

        'description' =>
        'Разработка Telegram-ботов, AI-ассистентов и внедрение искусственного интеллекта в сайты и сервисы.',

        'tariffs' => [

            [
                'id' => 'ai-telegram',
                'name' => 'Telegram-бот',
                'description' =>
                'Telegram-бот для автоматизации заявок, ответов клиентам, уведомлений и других задач.',
                'price' => 10000,
            ],

            [
                'id' => 'ai-assistant',
                'name' => 'AI-ассистент',
                'description' =>
                'Умный помощник на базе нейросети, способный отвечать клиентам и работать с вашей информацией.',
                'price' => 20000,
            ],

            [
                'id' => 'ai-business',
                'name' => 'AI для бизнеса',
                'description' =>
                'Интеграция AI в сайт, Telegram-бота или внутренний сервис компании.',
                'price' => 30000,
            ],

        ],
    ],


    [
        'name' => 'Другое',
        'slug' => 'other',
        'columns' => 3,

        'description' =>
        'Если подходящего варианта нет среди наших основных услуг.',

        'tariffs' => [

            [
                'id' => 'other-individual',
                'name' => 'Индивидуальный проект',
                'description' =>
                'Расскажите о вашей задаче, и мы подберём подходящий вариант разработки.',
                'price' => 10000,
            ],

        ],
    ],

];

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
        href="styles.css">

</head>


<body>


    <!-- ============================
     ВЕРХНЕЕ МЕНЮ
============================ -->

    <header class="main-header">

        <div class="container">

            <a
                href="index.php"
                class="logo">
                WebStart Studio
            </a>


            <nav>

                <a href="index.php">
                    Главная
                </a>

                <a href="tariffs.php">
                    Тарифы
                </a>

                <a
                    href="#cart"
                    class="cart-menu-link"
                    aria-label="Корзина">

                    <svg
                        class="cart-icon"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M3 3H5L7.2 14.2C7.4 15.2 8.3 16 9.4 16H17.5C18.5 16 19.4 15.3 19.7 14.3L21 8H6"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round" />

                        <circle
                            cx="10"
                            cy="20"
                            r="1.5"
                            fill="currentColor" />

                        <circle
                            cx="18"
                            cy="20"
                            r="1.5"
                            fill="currentColor" />
                    </svg>

                    <span
                        class="cart-counter"
                        id="cartCounter">
                        0
                    </span>

                </a>

            </nav>

        </div>

    </header>



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

                        <div
                            class="tariffs-grid"
                            style="--columns: <?= (int) $service['columns'] ?>;">


                            <?php foreach ($service['tariffs'] as $tariff): ?>


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



    <footer>

        <p>
            Команда WebStart Studio
        </p>

    </footer>



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
                item => item.id === id
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
                            ${item.name}
                        </strong>

                        <span class="cart-item-price">
                            ${formatPrice(item.price)}
                        </span>

                    </div>


                    <button
                        class="cart-remove"
                        data-id="${item.id}"
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
                                item => item.id !== id
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
                            item => item.id !== id
                        );


                    } else {


                        /*
                            Добавляем новый тариф
                        */

                        cart.push({

                            id: id,

                            service: this.dataset.service,

                            name: this.dataset.name,

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


</body>

</html>