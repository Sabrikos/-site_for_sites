<?php

function renderHeader()
{
?>

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

                <a href="index.php#services">
                    Услуги
                </a>

                <a href="tariffs.php">
                    Тарифы
                </a>

                <a href="index.php#technologies">
                    Технологии
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


    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function() {

                const cartCounter =
                    document.getElementById('cartCounter');

                if (!cartCounter) {
                    return;
                }

                let cart = [];

                try {

                    cart =
                        JSON.parse(
                            localStorage.getItem('webstartCart')
                        ) || [];

                } catch (error) {

                    cart = [];

                }

                if (cart.length > 0) {

                    cartCounter.textContent =
                        cart.length;

                    cartCounter.style.display =
                        'flex';

                } else {

                    cartCounter.style.display =
                        'none';

                }

            }
        );
    </script>

<?php
}



function renderFooter()
{
?>

    <footer class="main-footer">

        <div class="footer-grid">

            <div class="footer-brand">

                <a
                    href="index.php"
                    class="footer-logo">
                    WebStart Studio
                </a>

                <p>
                    Создаём сайты, интернет-магазины,
                    Telegram-ботов и AI-решения для бизнеса.
                </p>

            </div>


            <div class="footer-column">

                <h3>
                    Услуги
                </h3>

                <a href="tariffs.php#landing">
                    Лендинги
                </a>

                <a href="tariffs.php#shop">
                    Интернет-магазины
                </a>

                <a href="tariffs.php#revision">
                    Доработка сайтов
                </a>

                <a href="tariffs.php#ai">
                    AI-решения
                </a>

            </div>


            <div class="footer-column">

                <h3>
                    Студия
                </h3>

                <a href="index.php#technologies">
                    Технологии
                </a>

                <a href="index.php#projects">
                    Наши проекты
                </a>

                <a href="index.php#application">
                    Контакты
                </a>

            </div>


            <div class="footer-column">

                <h3>
                    Связаться
                </h3>

                <a href="#">
                    Telegram
                </a>

                <a href="#">
                    ВКонтакте
                </a>

                <a href="mailto:webstart@example.ru">
                    webstart@example.ru
                </a>

            </div>

        </div>


        <div class="footer-bottom">

            <span>
                © <?= date('Y') ?> WebStart Studio
            </span>

            <span>
                Разработка цифровых решений
            </span>

        </div>

    </footer>

<?php
}
