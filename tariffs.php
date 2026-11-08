<?php
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/services/TariffPresentation.php';

$title = 'Тарифы | Vega Studio';

require_once __DIR__ . '/services/ServiceCatalog.php';
require_once __DIR__ . '/components/ServiceFeatures.php';
$catalog = new ServiceCatalog($pdo);
$services = $catalog->categories();
$tariffs = $catalog->tariffs();
$featuresByTariff = $catalog->features();
$browserTariffs = $catalog->browserTariffs();
$tariffsByService = [];
foreach ($tariffs as $tariff) $tariffsByService[$tariff['service_id']][] = $tariff;

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
                Услуги и стоимость
            </h1>


        </div>



        <aside class="tariffs-price-note" aria-labelledby="tariffs-price-note-title">
            <h2 id="tariffs-price-note-title">Каждый проект начинается с задачи</h2>
            <p>У каждой услуги есть базовая стоимость. Дополнительные функции обсудим отдельно и согласуем итоговую цену перед началом работ.</p>
        </aside>

        <div class="tariffs-layout">


            <!-- ============================
             ЛЕВАЯ ЧАСТЬ
             УСЛУГИ
        ============================ -->

            <div class="tariffs-content">


                <?php foreach ($services as $service): ?>
                    <?php
                    $serviceTariffs = $tariffsByService[$service['id']] ?? [];
                    if (!$serviceTariffs) continue;
                    usort($serviceTariffs, static fn($a, $b) => (int) $a['price'] <=> (int) $b['price'] ?: (int) $a['id'] <=> (int) $b['id']);
                    $base = $serviceTariffs[0];
                    $remaining = array_slice($serviceTariffs, 1);
                    $features = $featuresByTariff[(int) $base['id']] ?? [];
                    $outcomes = [];
                    $process = [];
                    foreach ($features as $feature) {
                        if (preg_match('/правок|правки|правка|обучени|согласован|seo|метатег/u', mb_strtolower($feature))) $process[] = $feature;
                        else $outcomes[] = $feature;
                    }
                    $illustrations = ['landing' => 'landing', 'shop' => 'shop', 'revision' => 'revision', 'ai' => 'ai', 'other' => 'other'];
                    $illustration = $illustrations[$service['slug']] ?? 'other';
                    ?>
                    <section class="service-showcase" id="<?= htmlspecialchars($service['slug'], ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="service-title-<?= (int) $service['id'] ?>">
                        <div class="showcase-header">
                            <div class="showcase-image"><img src="assets/images/services/<?= $illustration ?>.png" alt="" loading="lazy" decoding="async"></div>
                            <div class="showcase-heading">
                                <div class="showcase-title-row"><span class="showcase-icon" aria-hidden="true"><?= tariffIcon($service['icon_key'] ?: $service['slug']) ?></span><h2 id="service-title-<?= (int) $service['id'] ?>"><?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?></h2></div>
                                <p><?= htmlspecialchars($service['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="showcase-tags"><span>Разработка под задачу</span><span>Согласованные сроки</span></div>
                            </div>
                        </div>
                        <div class="showcase-content">
                            <div class="showcase-column"><h3>Что вы получите</h3><?php renderServiceFeatures($outcomes ?: [$base['description']]); ?></div>
                            <div class="showcase-column"><h3>В разработку входит</h3><?php renderServiceFeatures($process); ?><p class="showcase-support">Условия и срок сопровождения обсудим отдельно.</p></div>
                        </div>
                        <div class="showcase-footer">
                            <p class="showcase-price">от <strong><?= number_format((int) $base['price'], 0, '', ' ') ?> <?= htmlspecialchars($base['price_unit'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                            <p class="showcase-price-note">Итоговая стоимость зависит от объёма, функциональности и сложности проекта.</p>
                            <button type="button" class="tariff-button showcase-button" data-id="<?= (int) $base['id'] ?>" data-service="<?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($base['name'], ENT_QUOTES, 'UTF-8') ?>" data-price="<?= (int) $base['price'] ?>">Выбрать услугу <span aria-hidden="true">↗</span></button>
                        </div>
                        <?php if ($remaining): ?>
                            <details class="showcase-variants"><summary>Другие варианты: <?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?></summary>
                                <div class="showcase-variant-grid">
                                    <?php foreach ($remaining as $tariff): ?>
                                        <article class="showcase-variant"><h3><?= htmlspecialchars($tariff['name'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($tariff['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p><?php renderServiceFeatures($featuresByTariff[(int) $tariff['id']] ?? []); ?><p>от <strong><?= number_format((int) $tariff['price'], 0, '', ' ') ?> <?= htmlspecialchars($tariff['price_unit'], ENT_QUOTES, 'UTF-8') ?></strong></p><button type="button" class="tariff-button showcase-variant-button" data-id="<?= (int) $tariff['id'] ?>" data-service="<?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>" data-name="<?= htmlspecialchars($tariff['name'], ENT_QUOTES, 'UTF-8') ?>" data-price="<?= (int) $tariff['price'] ?>">Выбрать услугу</button></article>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </section>
                    <?php if ($service['slug'] === 'other' && is_file(__DIR__ . '/assets/alien/tariffs/idle-manifest.js')): ?>
                        <div class="showcase-mascot" data-contact-alien aria-hidden="true">
                            <img class="contact-alien__fallback" src="assets/alien/tariffs/idle-full-poster.png" width="320" height="326" alt="" loading="lazy" draggable="false">
                            <canvas class="contact-alien__canvas" width="320" height="326" hidden></canvas>
                        </div>
                    <?php endif; ?>
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

    <script>window.VEGA_TARIFFS = <?= json_encode($browserTariffs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="assets/js/service-cart.js"></script>
    <script>
        /*
        Получаем сохранённую корзину.

        Благодаря localStorage выбранные услуги
        останутся даже после обновления страницы.
    */

        let cart = VegaCart.load();
        if (VegaCart.removedCount()) {
            const notice = document.createElement('p');
            notice.className = 'catalog-cart-notice';
            notice.setAttribute('role', 'status');
            notice.textContent = 'Некоторые ранее выбранные услуги больше недоступны и убраны из корзины. Проверьте состав заявки.';
            document.querySelector('main').prepend(notice);
        }

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

            cart = VegaCart.save(cart);

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
                        'Услуга выбрана';

                } else {


                    button.classList.remove('selected');

                    button.textContent =
                        'Выбрать услугу';

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

        window.addEventListener('storage', event => {
            if (event.key === 'webstartCart') {
                cart = VegaCart.load();
                updateCart();
            }
        });
        if (typeof updateHeaderCartCounter === 'function') updateHeaderCartCounter();
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
    <?php if (is_file(__DIR__ . '/assets/alien/tariffs/idle-manifest.js')): ?>
        <script src="assets/alien/tariffs/idle-manifest.js?v=<?= filemtime(__DIR__ . '/assets/alien/tariffs/idle-manifest.js') ?>" defer></script>
        <script src="contact-alien-idle.js?v=<?= filemtime(__DIR__ . '/contact-alien-idle.js') ?>" defer></script>
    <?php endif; ?>
</body>

</html>
