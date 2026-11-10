<?php
// The base price remains authoritative in the catalog; this CTA opens a discussion.
$shopIcon = static function (string $name): string {
    $paths = [
        'cart' => '<path d="M3 5h6l5 23h19l5-17H10"/><circle cx="17" cy="36" r="2"/><circle cx="32" cy="36" r="2"/>',
        'layers' => '<path d="m3 12 17-8 17 8-17 8-17-8Zm0 9 17 8 17-8M3 30l17 8 17-8"/>',
        'bolt' => '<path d="m23 2-17 23h12l-2 15 18-25H22l1-13Z"/>',
        'gear' => '<path d="m16 3 8 0 1 5 4 2 5-2 4 7-4 3v5l4 3-4 7-5-2-4 2-1 5h-8l-1-5-4-2-5 2-4-7 4-3v-5l-4-3 4-7 5 2 4-2 1-5Z"/><circle cx="20" cy="20.5" r="7"/>',
        'card' => '<rect x="2" y="6" width="36" height="29" rx="3"/><path d="M2 16h36M8 27h4m4 0h6"/>',
        'truck' => '<path d="M3 9h22v25H3V9Zm22 7h8l6 9v9H25M1 23h10m16-3h5l4 7H25"/><circle cx="11" cy="34" r="5"/><circle cx="31" cy="34" r="5"/>',
        'controls' => '<path d="M2 9h9m8 0h19M2 21h22m8 0h6M2 33h6m8 0h22"/><circle cx="15" cy="9" r="4"/><circle cx="28" cy="21" r="4"/><circle cx="12" cy="33" r="4"/>',
    ];
    return '<svg viewBox="0 0 42 42" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
};
?>
<section class="service-tariffs landing-offer shop-offer" id="shop" aria-labelledby="shop-offer-title">
    <div class="landing-offer__hero">
        <div class="landing-offer__visual">
            <img src="assets/images/tariffs/shop-devices-v3.png" width="1536" height="1024" alt="Интернет-магазин на ноутбуке, корзина покупок и посылка" decoding="async" loading="lazy">
        </div>
        <div class="landing-offer__intro">
            <div class="landing-offer__title-row">
                <div>
                    <h2 id="shop-offer-title">Интернет-магазин</h2>
                    <p>Полноценный онлайн-магазин для продажи товаров и услуг с удобным управлением и приемом оплат.</p>
                </div>
            </div>
            <div class="landing-offer__badges">
                <span><?= $shopIcon('layers') ?>Готовое решение под ваш бизнес</span>
                <span><?= $shopIcon('bolt') ?>Интеграции и расширения</span>
            </div>
        </div>
    </div>
    <div class="landing-offer__body">
        <div class="landing-offer__benefits">
            <h3>Что вы получите</h3>
            <ul>
                <li>Современный дизайн и удобную структуру</li>
                <li>Каталог товаров с категориями и фильтрами</li>
                <li>Корзину и оформление заказа</li>
                <li>Онлайн-оплату и уведомления о заказах</li>
                <li>Личный кабинет покупателя и историю заказов</li>
            </ul>
        </div>
        <div class="landing-offer__included">
            <h3>В разработку входит</h3>
            <ul>
                <li><span><?= $shopIcon('gear') ?></span><div>Настройка каталога, карточек товаров<small>и остатков</small></div></li>
                <li><span><?= $shopIcon('card') ?></span><div>Подключение платежных систем</div></li>
                <li><span><?= $shopIcon('truck') ?></span><div>Базовые настройки доставки<small>и интеграции с сервисами</small></div></li>
                <li><span><?= $shopIcon('controls') ?></span><div>Админ-панель для управления заказами,<small>товарами и клиентами</small></div></li>
            </ul>
        </div>
    </div>
    <div class="landing-offer__footer">
        <p class="landing-offer__price">от <strong><?= number_format((int) $shopTariff['price'], 0, '', ' ') ?> ₽</strong></p>
        <p class="landing-offer__note">Итоговая стоимость зависит от объема каталога,<br class="landing-offer__desktop-break"> необходимых интеграций и дополнительных функций.</p>
        <button type="button" class="tariff-button landing-offer__button shop-offer__button"
            data-id="<?= htmlspecialchars((string) $shopTariff['id'], ENT_QUOTES, 'UTF-8') ?>"
            data-service="<?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-name="<?= htmlspecialchars($shopTariff['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-price="<?= (int) $shopTariff['price'] ?>">Обсудить проект</button>
    </div>
</section>
