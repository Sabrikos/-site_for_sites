<?php


/* ==================================================
   ШАПКА САЙТА
================================================== */

function renderHeader()
{
?>

    <header class="main-header">

        <div class="container">

            <a
                href="index.php"
                class="logo"
            >
                Vega Studio
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


                <!-- Корзина -->

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

<?php
}



/* ==================================================
   ПОДВАЛ САЙТА
================================================== */

function renderFooter()
{
?>

    <footer class="main-footer">

        <div class="footer-cosmic-scene" aria-hidden="true">

            <div class="footer-stars-layer"></div>

            <div class="footer-planet footer-planet-large"></div>
            <div class="footer-planet footer-planet-small"></div>
            <div class="footer-planet footer-planet-dot"></div>

            <svg class="footer-atmosphere-lines" viewBox="0 0 1440 360" preserveAspectRatio="none" focusable="false">
                <path d="M-80 150 C130 80 250 180 410 126 C600 62 700 190 890 108 C1050 42 1180 158 1520 70" />
                <path d="M-110 210 C110 132 290 252 470 176 C650 98 810 226 990 146 C1160 72 1290 204 1540 118" />
                <path d="M-90 276 C150 210 270 302 520 238 C720 184 880 294 1085 214 C1260 146 1365 244 1530 176" />
            </svg>

            <div class="footer-moon-landscape">
                <div class="footer-moon-ridge footer-moon-ridge-back"></div>
                <div class="footer-moon-ridge footer-moon-ridge-front"></div>
                <span class="moon-crater moon-crater-1"></span>
                <span class="moon-crater moon-crater-2"></span>
                <span class="moon-crater moon-crater-3"></span>
                <span class="moon-crater moon-crater-4"></span>
                <span class="moon-crater moon-crater-5"></span>
                <span class="moon-crater moon-crater-6"></span>
                <span class="moon-crater moon-crater-7"></span>
                <span class="moon-crater moon-crater-8"></span>
                <span class="moon-rock moon-rock-1"></span>
                <span class="moon-rock moon-rock-2"></span>
                <span class="moon-rock moon-rock-3"></span>
            </div>

        </div>

        <div class="footer-grid">


            <!-- Левая часть -->

            <div class="footer-brand">

                <a
                    href="index.php"
                    class="footer-logo"
                >
                    Vega Studio
                </a>

                <p>
                    Создаём сайты, интернет-магазины,
                    Telegram-ботов и AI-решения для бизнеса.
                </p>

            </div>



            <!-- Услуги -->

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



            <!-- Студия -->

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



            <!-- Контакты -->

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

                <a href="mailto:vega@example.ru">
                    vega@example.ru
                </a>

            </div>

        </div>



        <!-- Нижняя строка -->

        <div class="footer-bottom">

            <span>
                © <?= date('Y') ?> Vega Studio
            </span>

            <span>
                Разработка цифровых решений
            </span>

        </div>

    </footer>



    <!-- ==================================================
         ОБЩИЙ СКРИПТ СЧЁТЧИКА КОРЗИНЫ
    ================================================== -->

    <script>

        function updateHeaderCartCounter() {

            const cartCounter =
                document.getElementById('cartCounter');


            if (!cartCounter) {
                return;
            }


            let cart = [];


            try {

                cart =
                    JSON.parse(
                        localStorage.getItem('vegaCart')
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

                cartCounter.textContent =
                    '0';

                cartCounter.style.display =
                    'none';

            }

        }


        updateHeaderCartCounter();


        /*
            Если корзина изменилась в другой вкладке
            браузера, число тоже обновится.
        */

        window.addEventListener(
            'storage',
            updateHeaderCartCounter
        );



        function updateFloatingHeader() {

            const header =
                document.querySelector('.main-header');

            if (!header) {
                return;
            }

            document.body.style.setProperty(
                '--floating-header-height',
                `${header.offsetHeight}px`
            );

            if (window.scrollY >= 450) {
                document.body.classList.add('header-is-floating');
                header.classList.add('is-floating');
                return;
            }

            if (window.scrollY <= 5) {
                header.classList.remove('is-floating');
                document.body.classList.remove('header-is-floating');
            }

        }


        updateFloatingHeader();


        window.addEventListener(
            'scroll',
            updateFloatingHeader,
            { passive: true }
        );
    </script>

<?php
}