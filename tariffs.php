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
    <link rel="stylesheet" href="landing-offer.css?v=<?= filemtime(__DIR__ . '/landing-offer.css') ?>">
    <link rel="stylesheet" href="tariff-cart.css?v=<?= filemtime(__DIR__ . '/tariff-cart.css') ?>">

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
                Услуги и стоимость
            </h1>


        </div>



        <div class="tariffs-layout">


            <!-- ============================
             ЛЕВАЯ ЧАСТЬ
             УСЛУГИ
        ============================ -->

            <div class="tariffs-content">


                <?php foreach ($services as $service): ?>

                    <?php
                    if ($service['slug'] === 'landing' && !empty($tariffsByService[$service['id']])) {
                        $landingTariffs = $tariffsByService[$service['id']];
                        usort($landingTariffs, static fn($a, $b) => (int) $a['price'] <=> (int) $b['price']);
                        $landingTariff = $landingTariffs[0];
                        require __DIR__ . '/partials/landing-tariff.php';
                        continue;
                    }
                    if ($service['slug'] === 'shop' && !empty($tariffsByService[$service['id']])) {
                        $shopTariffs = $tariffsByService[$service['id']];
                        usort($shopTariffs, static fn($a, $b) => (int) $a['price'] <=> (int) $b['price']);
                        $shopTariff = $shopTariffs[0];
                        require __DIR__ . '/partials/shop-tariff.php';
                        continue;
                    }
                    if ($service['slug'] === 'revision' && !empty($tariffsByService[$service['id']])) {
                        $revisionTariffs = $tariffsByService[$service['id']];
                        usort($revisionTariffs, static fn($a, $b) => (int) $a['price'] <=> (int) $b['price']);
                        $revisionTariff = $revisionTariffs[0];
                        require __DIR__ . '/partials/revision-tariff.php';
                        continue;
                    }
                    if ($service['slug'] === 'ai' && !empty($tariffsByService[$service['id']])) {
                        $aiTariffs = $tariffsByService[$service['id']];
                        usort($aiTariffs, static fn($a, $b) => (int) $a['price'] <=> (int) $b['price']);
                        $aiTariff = $aiTariffs[0];
                        require __DIR__ . '/partials/ai-tariff.php';
                        continue;
                    }
                    if ($service['slug'] === 'other' && !empty($tariffsByService[$service['id']])) {
                        $otherTariffs = $tariffsByService[$service['id']];
                        usort($otherTariffs, static fn($a, $b) => (int) $a['price'] <=> (int) $b['price']);
                        $otherTariff = $otherTariffs[0];
                        require __DIR__ . '/partials/other-tariff.php';
                        continue;
                    }
                    ?>


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
                        if (in_array($service['slug'], ['landing', 'shop', 'revision'], true) && $serviceTariffs) {
                            usort($serviceTariffs, static fn($a, $b) => (int) $a['price'] <=> (int) $b['price']);
                            $serviceTariffs = [reset($serviceTariffs)];
                        }

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
                                            <?= htmlspecialchars(['landing' => 'Базовый лендинг', 'shop' => 'Базовый интернет-магазин', 'revision' => 'Базовая доработка'][$service['slug']] ?? $tariff['name']) ?>
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

                                            Обсудить проект

                                        </button>


                                    </div>


                                </article>


                            <?php endforeach; ?>

                            <?php if (in_array($service['slug'], ['landing', 'shop', 'revision'], true) && $serviceTariffs): ?>
                                <div class="landing-extras" aria-labelledby="extras-title-<?= htmlspecialchars($service['slug']) ?>" data-extra-service="<?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <h3 id="extras-title-<?= htmlspecialchars($service['slug']) ?>">Дополнительные возможности</h3>
                                    <p>Выберите то, что может понадобиться. Стоимость каждой функции уточним после обсуждения; в базовую цену она не включена.</p>
                                    <div class="landing-extras-list">
                                        <?php foreach (['Дополнительные страницы', 'Форма заявки', 'Онлайн-оплата', 'Интеграция с CRM', 'Чат или бот', 'Анимация'] as $extra): ?>
                                            <label><input type="checkbox" class="landing-extra" value="<?= htmlspecialchars($extra, ENT_QUOTES, 'UTF-8') ?>"> <span><?= htmlspecialchars($extra, ENT_QUOTES, 'UTF-8') ?></span><small>По запросу</small></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="landing-extras-note">Пожелания появятся в комментарии при оформлении заявки на эту услугу.</p>
                                </div>
                            <?php endif; ?>

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
                                    <a class="other-project-cta" href="index.php#application">Обсудить проект <span aria-hidden="true">↗</span></a>
                                </div>
                            <?php endif; ?>

                        </div>


                        <?php if ($service['slug'] === 'other'): ?>
                            <?php if (is_file(__DIR__ . '/assets/alien/tariffs/idle-manifest.js')): ?>
                                <div class="tariffs-alien" data-contact-alien aria-hidden="true">
                                    <img class="contact-alien__fallback" src="assets/alien/tariffs/idle-full-poster.png" width="320" height="326" alt="" loading="lazy" draggable="false">
                                    <canvas class="contact-alien__canvas" width="320" height="326" hidden></canvas>
                                </div>
                            <?php else: ?>
                                <img class="tariffs-alien" src="assets/images/tariffs-alien.png" width="1244" height="1264" alt="" aria-hidden="true" loading="lazy" draggable="false">
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>


                <?php endforeach; ?>


            </div>



            <!-- ============================
             ПРАВАЯ ЧАСТЬ
             КОРЗИНА
        ============================ -->

            <aside
                class="cart cart--reference"
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

                <div id="cartItems" aria-live="polite">

                    <p class="cart-empty">
                        Вы пока ничего не выбрали
                    </p>

                </div>





                <button
                    type="button"
                    class="checkout-button"
                    id="checkoutButton">
                    <span>Оставить заявку</span><span aria-hidden="true">↗</span>
                </button>



                <button
                    type="button"
                    class="clear-cart-button"
                    id="clearCartButton">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7m4-7v7"/></svg>
                    <span>Очистить корзину</span>
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

        let cart = window.VegaCart.read();

        window.addEventListener('vega:cart-changed', () => {
            cart = window.VegaCart.read();
            updateCart();
        });

        function getTariffId(item) {
            return String(item.tariff_id ?? item.id);
        }

        function getTariffName(item) {
            return item.tariff ?? item.name ?? '';
        }



        document.querySelectorAll('.landing-extras').forEach(section => {
            const service = section.dataset.extraService;
            let selected = {};
            try { selected = JSON.parse(localStorage.getItem('vegaServiceExtras') || '{}'); } catch (_) {}
            if (!selected || typeof selected !== 'object' || Array.isArray(selected)) selected = {};
            section.querySelectorAll('.landing-extra').forEach(input => {
                input.checked = Array.isArray(selected[service]) && selected[service].includes(input.value);
                input.addEventListener('change', () => {
                    let choices = {};
                    try { choices = JSON.parse(localStorage.getItem('vegaServiceExtras') || '{}'); } catch (_) {}
                    if (!choices || typeof choices !== 'object' || Array.isArray(choices)) choices = {};
                    choices[service] = Array.from(section.querySelectorAll('.landing-extra:checked')).map(item => item.value);
                    localStorage.setItem('vegaServiceExtras', JSON.stringify(choices));
                });
            });
        });

        const buttons =
            document.querySelectorAll('.tariff-button');

        const cartItems =
            document.getElementById('cartItems');

        const cartCounter =
            document.getElementById('cartCounter');

        const clearCartButton =
            document.getElementById('clearCartButton');
        const checkoutButton = document.getElementById('checkoutButton');
        const cartCatalog = <?php
            $cartCatalog = [];
            $cartSummaries = ['landing' => 'Разработка сайта под задачу', 'shop' => 'Онлайн-магазин для вашего бизнеса', 'revision' => 'Изменения и улучшения сайта', 'ai' => 'Интеграция AI в ваш бизнес', 'other' => 'Решение вашей индивидуальной задачи'];
            foreach ($services as $catalogService) {
                foreach ($tariffsByService[$catalogService['id']] ?? [] as $catalogTariff) {
                    $cartCatalog[(string) $catalogTariff['id']] = [
                        'service' => $catalogService['name'],
                        'description' => $catalogService['slug'] === 'ai' ? $catalogTariff['name'] : ($cartSummaries[$catalogService['slug']] ?? $catalogTariff['name']),
                        'icon' => tariffIcon($catalogService['slug']),
                    ];
                }
            }
            echo json_encode($cartCatalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        ?>;
        const cartFallbackIcon = <?= json_encode(tariffIcon('other'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Refresh this base offer after the cart DOM references are initialized.
        const aiBaseButton = document.querySelector('#ai .tariff-button');
        const selectedAi = aiBaseButton && cart.find(item => getTariffId(item) === aiBaseButton.dataset.id);
        if (selectedAi && Number(selectedAi.price) !== Number(aiBaseButton.dataset.price)) {
            selectedAi.price = Number(aiBaseButton.dataset.price);
            window.VegaCart.write(cart);
        }



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

            window.VegaCart.write(cart);

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
            checkoutButton.disabled = cart.length === 0;
            clearCartButton.disabled = cart.length === 0;



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
                    const catalog = cartCatalog[getTariffId(item)];
                    const itemIcon = document.createElement('span');
                    itemIcon.className = 'cart-item-icon';
                    itemIcon.setAttribute('aria-hidden', 'true');
                    // Icon markup comes only from the server's fixed SVG set.
                    itemIcon.innerHTML = catalog?.icon ?? cartFallbackIcon;
                    const itemInfo = document.createElement('div');
                    itemInfo.className = 'cart-item-info';

                    const service = document.createElement('span');
                    service.className = 'cart-item-service';
                    service.textContent = catalog?.service ?? item.service ?? '';

                    const title = document.createElement('strong');
                    title.textContent = catalog?.description ?? getTariffName(item);


                    const removeButton = document.createElement('button');
                    removeButton.className = 'cart-remove';
                    removeButton.dataset.id = getTariffId(item);
                    removeButton.type = 'button';
                    removeButton.innerHTML = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7m4-7v7"/></svg>';
                    removeButton.setAttribute('aria-label', `Удалить: ${item.service ?? ''} — ${getTariffName(item)}`);
                    removeButton.title = 'Удалить из корзины';

                    itemInfo.append(service, title);
                    cartItem.append(itemIcon, itemInfo, removeButton);
                    cartItems.appendChild(cartItem);

                });

            }



            /*
                Подсчёт общей стоимости
            */




            /*
                Меняем состояние кнопок
            */

            buttons.forEach(button => {
                const selected = tariffIsSelected(button.dataset.id);
                const initialized = button.dataset.selectionReady === 'true';
                const wasSelected = button.getAttribute('aria-pressed') === 'true';

                if (!initialized) {
                    const label = document.createElement('span');
                    label.className = 'offer-button-label';
                    label.textContent = 'Обсудить проект';
                    const icons = document.createElement('span');
                    icons.className = 'offer-button-icons';
                    icons.setAttribute('aria-hidden', 'true');
                    icons.innerHTML = '<svg class="offer-button-arrow" viewBox="0 0 24 24" fill="none"><path d="M6 18 18 6M6 6h12v12"/></svg><svg class="offer-button-check" viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6" pathLength="1" stroke-dasharray="1"/></svg>';
                    button.replaceChildren(label, icons);
                    button.dataset.selectionReady = 'true';
                    requestAnimationFrame(() => button.classList.add('selection-ready'));
                }

                button.classList.toggle('selected', selected);
                button.setAttribute('aria-pressed', String(selected));
                const check = button.querySelector('.offer-button-check path');
                const gsap = window.VegaMotion?.gsap;
                if (initialized && wasSelected !== selected) {
                    gsap?.killTweensOf(check);
                    if (selected && gsap && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        gsap.fromTo(check, {strokeDashoffset: 1}, {
                            strokeDashoffset: 0, duration: .32, delay: .08, ease: 'power2.out',
                        });
                    } else {
                        check.style.strokeDashoffset = '0';
                    }
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
    <script src="cursor-stars.js?v=<?= filemtime(__DIR__ . '/cursor-stars.js') ?>"></script>
    <?php if (is_file(__DIR__ . '/assets/alien/tariffs/idle-manifest.js')): ?>
        <script src="assets/alien/tariffs/idle-manifest.js?v=<?= filemtime(__DIR__ . '/assets/alien/tariffs/idle-manifest.js') ?>" defer></script>
        <script src="contact-alien-idle.js?v=<?= filemtime(__DIR__ . '/contact-alien-idle.js') ?>" defer></script>
    <?php endif; ?>
</body>

</html>
