<?php
// Presentation only: selection ID and price continue to come from the catalog.
$landingIcon = static function (string $name): string {
    $paths = [
        'monitor' => '<rect x="4" y="4" width="32" height="23" rx="2"/><path d="M20 27v9m-8 0h16"/>',
        'layers' => '<path d="m3 12 17-8 17 8-17 8-17-8Zm0 9 17 8 17-8M3 30l17 8 17-8"/>',
        'devices' => '<rect x="12" y="4" width="24" height="29" rx="2"/><path d="M9 10H4v26h14V22H4m20 15h8M29 1v5"/>',
        'controls' => '<path d="M2 9h9m8 0h19M2 21h22m8 0h6M2 33h6m8 0h22"/><circle cx="15" cy="9" r="4"/><circle cx="28" cy="21" r="4"/><circle cx="12" cy="33" r="4"/>',
        'cap' => '<path d="m2 14 18-9 18 9-18 9-18-9Zm7 5v11c7 6 15 6 22 0V19m6-4v14"/>',
    ];
    return '<svg viewBox="0 0 40 42" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
};
?>
<section class="service-tariffs landing-offer" id="landing" aria-labelledby="landing-offer-title">
    <div class="landing-offer__hero">
        <div class="landing-offer__visual">
            <img src="assets/images/tariffs/landing-devices-v3.png" width="1536" height="1024" alt="Лендинг на экранах ноутбука и смартфона" decoding="async">
        </div>
        <div class="landing-offer__intro">
            <div class="landing-offer__title-row">
                <div>
                    <h2 id="landing-offer-title">Лендинг</h2>
                    <p>Одностраничный сайт, разработанный под вашу задачу, аудиторию и фирменный стиль.</p>
                </div>
            </div>
            <div class="landing-offer__badges">
                <span><?= $landingIcon('layers') ?>Индивидуальная разработка</span>
                <span><?= $landingIcon('devices') ?>Адаптация под все устройства</span>
            </div>
        </div>
    </div>
    <div class="landing-offer__body">
        <div class="landing-offer__benefits">
            <h3>Что вы получите</h3>
            <ul>
                <li>Уникальную структуру и дизайн<br class="landing-offer__desktop-break"> под ваш проект</li>
                <li>Адаптивный дизайн для смартфонов,<br class="landing-offer__desktop-break"> планшетов и компьютеров</li>
                <li>Необходимые блоки: о компании,<br class="landing-offer__desktop-break"> преимущества, услуги, кейсы, контакты и др.</li>
                <li>Формы заявок и подключение способов связи</li>
            </ul>
        </div>
        <div class="landing-offer__included">
            <h3>В разработку входит</h3>
            <ul>
                <li><span><?= $landingIcon('controls') ?></span><div>Настройка отображения сайта в поиске.</div></li>
                <li><span><?= $landingIcon('layers') ?></span><div>2 итерации правок<small>после согласования результата</small></div></li>
                <li><span><?= $landingIcon('cap') ?></span><div>Обучение работе с CMS —<small>если она используется в проекте</small></div></li>
            </ul>
        </div>
    </div>
    <div class="landing-offer__footer">
        <p class="landing-offer__price">от <strong><?= number_format((int) $landingTariff['price'], 0, '', ' ') ?> ₽</strong></p>
        <p class="landing-offer__note">Итоговая стоимость зависит<br class="landing-offer__desktop-break"> от объёма, функциональности и сложности проекта.</p>
        <button type="button" class="tariff-button landing-offer__button"
            data-id="<?= htmlspecialchars((string) $landingTariff['id'], ENT_QUOTES, 'UTF-8') ?>"
            data-service="<?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-name="<?= htmlspecialchars($landingTariff['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-price="<?= (int) $landingTariff['price'] ?>">Обсудить проект</button>
    </div>
</section>
