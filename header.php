<header class="main-header">

    <div class="container">

	<div class="container">

            <a
                href="index.php"
                class="logo">
                WebStart Studio
            </a>

        </div>

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


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

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