<?php

$title = 'Корзина | WebStart Studio';

?>

<!DOCTYPE html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($title) ?>
    </title>

    <link
        rel="stylesheet"
        href="styles.css"
    >

</head>


<body>


<!-- =========================================
     ВЕРХНЕЕ МЕНЮ
========================================= -->

<header class="main-header">

    <div class="container">

        <a
            href="index.php"
            class="logo"
        >
            WebStart Studio
        </a>


        <nav>

            <a href="index.php">
                Главная
            </a>

            <a href="index.php#services">
                Услуги
            </a>

            <a href="tariffs.php">
                Тарифы
            </a>

            <a href="index.php#projects">
                Проекты
            </a>

            <a href="index.php#application">
                Контакты
            </a>


            <a
                href="cart.php"
                class="cart-menu-link"
                aria-label="Корзина"
            >

                <svg
                    class="cart-icon"
                    viewBox="0 0 24 24"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >

                    <path
                        d="M3 3H5L7.2 14.2C7.4 15.2 8.3 16 9.4 16H17.5C18.5 16 19.4 15.3 19.7 14.3L21 8H6"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />

                    <circle
                        cx="10"
                        cy="20"
                        r="1.5"
                        fill="currentColor"
                    />

                    <circle
                        cx="18"
                        cy="20"
                        r="1.5"
                        fill="currentColor"
                    />

                </svg>


                <span
                    class="cart-counter"
                    id="cartCounter"
                >
                    0
                </span>

            </a>

        </nav>

    </div>

</header>



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
                id="cartPageEmpty"
            >

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
                    class="continue-shopping-button"
                >
                    Перейти к тарифам
                </a>

            </div>


        </section>



        <!-- =================================
             ИТОГ
        ================================= -->

        <aside class="cart-summary">


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
                href="index.php#application"
                class="cart-order-button"
                id="cartOrderButton"
            >
                Оформить заявку
            </a>



            <a
                href="tariffs.php"
                class="cart-back-button"
            >
                Продолжить выбор
            </a>



            <button
                type="button"
                class="cart-clear-page"
                id="cartClearPage"
            >
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



<script>

    let cart =
        JSON.parse(
            localStorage.getItem('webstartCart')
        ) || [];


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

        }

        else {

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


                element.innerHTML = `

                    <div class="cart-product-info">

                        <span class="cart-product-service">
                            ${item.service}
                        </span>

                        <h3>
                            ${item.name}
                        </h3>

                    </div>


                    <div class="cart-product-actions">

                        <strong>
                            ${formatPrice(item.price)}
                        </strong>

                        <button
                            type="button"
                            class="cart-product-remove"
                            data-id="${item.id}"
                        >
                            Удалить
                        </button>

                    </div>

                `;


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
                    function () {


                        const id =
                            this.dataset.id;


                        cart =
                            cart.filter(

                                item =>
                                    item.id !== id

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
        function () {

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
        function (event) {

            if (cart.length === 0) {

                event.preventDefault();

            }

        }
    );



    updateCartPage();

</script>


</body>

</html>