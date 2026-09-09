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
                aria-label="Vega Studio — на главную"
            >
                <img
                    src="assets/images/vega-logo.png"
                    alt=""
                    class="logo-image"
                    aria-hidden="true"
                >
                <span class="logo-name">
                    <span class="logo-name-vega">Vega</span>
                    <span class="logo-name-studio">Studio</span>
                </span>
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

    <script src="web-chat.js?v=<?= filemtime(__DIR__ . '/web-chat.js') ?>" defer></script>

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
                <path d="M-90 126 C150 46 250 170 430 104 C620 34 720 176 900 94 C1080 18 1220 150 1540 58" />
                <path d="M-120 196 C110 112 300 232 490 154 C680 76 820 212 1016 130 C1194 56 1305 188 1550 98" />
                <path d="M-80 276 C140 206 300 312 540 236 C748 170 898 294 1100 212 C1278 138 1375 238 1532 170" />
            </svg>

            <svg class="footer-lunar-svg" viewBox="0 0 1920 520" preserveAspectRatio="xMidYMax slice" focusable="false">
                <path class="moon-back" d="M0 158 C80 128 132 142 202 118 C278 92 330 150 405 118 C484 84 536 148 612 110 C700 66 760 142 842 104 C930 58 994 136 1080 100 C1178 58 1240 140 1326 108 C1426 70 1494 140 1588 106 C1690 70 1770 122 1920 84 L1920 520 L0 520 Z" />
                <path class="moon-middle" d="M0 248 C88 220 172 254 260 220 C362 182 446 258 556 212 C674 162 760 258 890 206 C1010 158 1114 254 1248 202 C1386 150 1480 244 1608 208 C1736 172 1810 218 1920 196 L1920 520 L0 520 Z" />
                <path class="moon-front" d="M0 330 C92 292 176 356 284 306 C388 260 486 356 612 302 C748 244 848 360 990 300 C1128 244 1252 348 1398 300 C1530 256 1642 332 1768 292 C1836 270 1884 284 1920 272 L1920 520 L0 520 Z" />

                <path class="moon-highlight moon-highlight-1" d="M70 292 C178 260 282 282 374 258 C456 236 524 242 602 218" />
                <path class="moon-highlight moon-highlight-2" d="M760 286 C884 244 1020 270 1128 234 C1208 208 1304 214 1390 192" />
                <path class="moon-highlight moon-highlight-3" d="M1286 350 C1398 316 1510 332 1636 296 C1720 272 1812 272 1908 250" />

                <g class="svg-crater svg-crater-xl" transform="translate(142 384) rotate(4)">
                    <path class="crater-outer" d="M-132 6 C-96 -44 -20 -60 78 -42 C152 -28 188 8 170 42 C142 92 44 110 -68 86 C-140 70 -176 36 -132 6 Z" />
                    <path class="crater-rim" d="M-108 0 C-70 -26 6 -36 82 -22 C126 -14 148 8 134 28 C112 58 34 68 -50 54 C-112 44 -142 24 -108 0 Z" />
                    <ellipse class="crater-inner" cx="14" cy="22" rx="106" ry="30" />
                    <path class="crater-shadow" d="M-88 24 C-32 48 58 48 124 22 C104 62 28 82 -60 62 C-94 54 -116 40 -88 24 Z" />
                </g>

                <g class="svg-crater svg-crater-large" transform="translate(548 424) rotate(-5)">
                    <path class="crater-outer" d="M-112 4 C-76 -34 2 -48 82 -30 C140 -18 166 12 142 42 C110 82 22 94 -68 72 C-126 58 -150 30 -112 4 Z" />
                    <path class="crater-rim" d="M-86 2 C-48 -20 12 -28 72 -16 C106 -10 122 8 108 24 C84 48 20 58 -48 44 C-92 36 -112 20 -86 2 Z" />
                    <ellipse class="crater-inner" cx="10" cy="22" rx="82" ry="23" />
                    <path class="crater-shadow" d="M-70 22 C-22 40 48 42 100 20 C82 50 20 64 -42 50 C-72 44 -90 34 -70 22 Z" />
                </g>

                <g class="svg-crater svg-crater-deep" transform="translate(1038 388) rotate(2)">
                    <path class="crater-outer" d="M-126 8 C-84 -42 10 -58 100 -34 C162 -18 190 18 158 50 C118 94 18 106 -84 78 C-146 60 -168 34 -126 8 Z" />
                    <path class="crater-rim" d="M-94 4 C-52 -22 20 -34 88 -18 C124 -10 144 10 126 30 C98 58 22 68 -56 50 C-102 40 -124 22 -94 4 Z" />
                    <ellipse class="crater-inner" cx="12" cy="24" rx="92" ry="28" />
                    <path class="crater-shadow" d="M-78 24 C-24 48 62 48 120 20 C98 60 20 78 -62 58 C-92 50 -108 36 -78 24 Z" />
                </g>

                <g class="svg-crater svg-crater-wide" transform="translate(1578 412) rotate(-4)">
                    <path class="crater-outer" d="M-118 6 C-76 -30 2 -44 88 -28 C156 -16 184 12 156 40 C120 76 20 88 -78 66 C-138 52 -154 28 -118 6 Z" />
                    <path class="crater-rim" d="M-88 4 C-48 -16 20 -24 78 -14 C116 -8 132 8 116 22 C90 46 18 52 -54 40 C-96 32 -114 20 -88 4 Z" />
                    <ellipse class="crater-inner" cx="14" cy="20" rx="86" ry="21" />
                    <path class="crater-shadow" d="M-74 22 C-18 38 58 38 108 18 C88 46 24 58 -48 46 C-78 40 -96 32 -74 22 Z" />
                </g>

                <g class="svg-crater svg-crater-medium" transform="translate(820 348) rotate(6)">
                    <path class="crater-outer" d="M-58 2 C-32 -20 12 -26 54 -14 C86 -6 98 12 78 28 C52 48 0 52 -46 38 C-76 30 -84 16 -58 2 Z" />
                    <ellipse class="crater-inner" cx="8" cy="16" rx="48" ry="14" />
                    <path class="crater-shadow" d="M-40 16 C-10 28 36 28 68 12 C56 34 8 42 -34 30 C-50 26 -58 20 -40 16 Z" />
                </g>

                <g class="svg-crater svg-crater-small" transform="translate(1268 454) rotate(-8)">
                    <path class="crater-outer" d="M-46 2 C-24 -14 14 -20 46 -10 C70 -4 78 10 62 22 C42 38 0 42 -34 32 C-58 26 -66 14 -46 2 Z" />
                    <ellipse class="crater-inner" cx="7" cy="13" rx="36" ry="10" />
                </g>

                <g class="svg-crater svg-crater-small" transform="translate(1830 362) rotate(7)">
                    <path class="crater-outer" d="M-48 2 C-26 -14 12 -20 48 -10 C74 -4 82 10 64 23 C42 40 0 42 -36 32 C-60 26 -68 14 -48 2 Z" />
                    <ellipse class="crater-inner" cx="7" cy="13" rx="38" ry="10" />
                </g>

                <path class="moon-rock-shape" d="M420 454 l24 -18 l34 9 l12 25 l-54 10 Z" />
                <path class="moon-rock-shape" d="M1188 474 l18 -15 l25 7 l10 18 l-38 8 Z" />
                <path class="moon-rock-shape" d="M1748 472 l26 -16 l30 10 l8 20 l-48 9 Z" />
            </svg>

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
