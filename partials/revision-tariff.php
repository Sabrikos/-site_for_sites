<?php
// Display copy follows the reference; ID and price remain server-catalog values.
$revisionIcon = static function (string $name): string {
    $paths = [
        'wrench' => '<path d="M25 4a11 11 0 0 0-13 14L3 29a5 5 0 0 0 7 7l11-11A11 11 0 0 0 35 12l-8 8-7-7 5-9Z"/>',
        'bolt' => '<path d="m23 2-17 23h12l-2 15 18-25H22l1-13Z"/>',
        'layers' => '<path d="m3 12 17-8 17 8-17 8-17-8Zm0 9 17 8 17-8M3 30l17 8 17-8"/>',
        'gear' => '<path d="m16 3 8 0 1 5 4 2 5-2 4 7-4 3v5l4 3-4 7-5-2-4 2-1 5h-8l-1-5-4-2-5 2-4-7 4-3v-5l-4-3 4-7 5 2 4-2 1-5Z"/><circle cx="20" cy="20.5" r="7"/>',
        'code' => '<rect x="3" y="3" width="36" height="35" rx="4"/><path d="M3 13h36M9 8h1m4 0h1m-1 12-5 5 5 5m14-10 5 5-5 5m-6-12-3 14"/>',
        'controls' => '<path d="M2 9h9m8 0h19M2 21h22m8 0h6M2 33h6m8 0h22"/><circle cx="15" cy="9" r="4"/><circle cx="28" cy="21" r="4"/><circle cx="12" cy="33" r="4"/>',
    ];
    return '<svg viewBox="0 0 42 42" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
};
?>
<section class="service-tariffs landing-offer revision-offer" id="revision" aria-labelledby="revision-offer-title">
    <div class="landing-offer__hero">
        <div class="landing-offer__visual">
            <img src="assets/images/tariffs/revision-devices-v2.png" width="1536" height="1024" alt="Ноутбук с кодом, панелями настроек и гаечным ключом" decoding="async" loading="lazy">
        </div>
        <div class="landing-offer__intro">
            <div class="landing-offer__title-row">
                <div>
                    <h2 id="revision-offer-title">Доработка сайта</h2>
                    <p>Внесем изменения, исправим ошибки и добавим новый функционал на ваш сайт.</p>
                </div>
            </div>
            <div class="landing-offer__badges">
                <span><?= $revisionIcon('bolt') ?>Быстро и без потери данных</span>
                <span><?= $revisionIcon('layers') ?>Под любую CMS и технологию</span>
            </div>
        </div>
    </div>
    <div class="landing-offer__body">
        <div class="landing-offer__benefits">
            <h3>Что вы получите</h3>
            <ul>
                <li>Внедрение нового функционала</li>
                <li>Исправление ошибок и доработка логики</li>
                <li>Улучшение дизайна и пользовательского опыта</li>
                <li>Оптимизацию скорости и стабильности</li>
                <li>Сохранение всех данных и текущего контента</li>
            </ul>
        </div>
        <div class="landing-offer__included">
            <h3>Примеры доработок</h3>
            <ul>
                <li><span><?= $revisionIcon('gear') ?></span><div>Добавление разделов, форм,<small>интеграций и модулей</small></div></li>
                <li><span><?= $revisionIcon('code') ?></span><div>Настройка существующих функций<small>и внешних сервисов</small></div></li>
                <li><span><?= $revisionIcon('controls') ?></span><div>Оптимизация, улучшение верстки<small>и адаптивности</small></div></li>
            </ul>
        </div>
    </div>
    <div class="landing-offer__footer">
        <p class="landing-offer__price">от <strong><?= number_format((int) $revisionTariff['price'], 0, '', ' ') ?> ₽</strong></p>
        <p class="landing-offer__note">Стоимость зависит от объема работ<br class="landing-offer__desktop-break"> и сложности задачи.</p>
        <button type="button" class="tariff-button landing-offer__button"
            data-id="<?= htmlspecialchars((string) $revisionTariff['id'], ENT_QUOTES, 'UTF-8') ?>"
            data-service="<?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-name="<?= htmlspecialchars($revisionTariff['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-price="<?= (int) $revisionTariff['price'] ?>">Обсудить проект</button>
    </div>
</section>
