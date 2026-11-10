<?php
$otherIcon = static function (string $name): string {
    $paths = [
        'dots' => '<circle cx="8" cy="20" r="2"/><circle cx="20" cy="20" r="2"/><circle cx="32" cy="20" r="2"/>',
        'layers' => '<path d="m3 12 17-8 17 8-17 8-17-8Zm0 9 17 8 17-8M3 30l17 8 17-8"/>',
        'bolt' => '<path d="m23 2-17 23h12l-2 15 18-25H22l1-13Z"/>',
        'telegram' => '<path d="M3 18 38 4l-7 33-12-10-7 6 1-12L32 10 17 24l2 3"/>',
        'chip' => '<rect x="10" y="10" width="22" height="22" rx="3"/><path d="M15 3v7m12-7v7M15 32v7m12-7v7M3 15h7m-7 12h7m22-12h7m-7 12h7"/>',
        'users' => '<circle cx="15" cy="12" r="7"/><circle cx="30" cy="15" r="5"/><path d="M2 37v-4a10 10 0 0 1 10-10h6a10 10 0 0 1 10 10v4m1-11a9 9 0 0 1 10 9v2"/>',
        'database' => '<ellipse cx="21" cy="8" rx="14" ry="6"/><path d="M7 8v24c0 8 28 8 28 0V8M7 20c0 8 28 8 28 0"/>',
        'screen' => '<rect x="3" y="4" width="36" height="25" rx="2"/><path d="M15 29v8m12-8v8M10 38h22"/>',
        'code' => '<path d="m12 9-10 12 10 12m18-24 10 12-10 12M25 4 17 38"/>',
    ];
    return '<svg viewBox="0 0 42 42" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
};
?>
<section class="service-tariffs landing-offer other-offer" id="other" aria-labelledby="other-offer-title">
    <div class="other-offer__header">
        <div class="landing-offer__title-row">
            <div>
                <h2 id="other-offer-title">Другое</h2>
                <p>Индивидуальные задачи, нестандартные решения и разовые работы.</p>
            </div>
        </div>
        <div class="landing-offer__badges">
            <span><?= $otherIcon('layers') ?>Гибкий формат сотрудничества</span>
            <span><?= $otherIcon('bolt') ?>Решения под вашу задачу</span>
        </div>
    </div>
    <div class="other-offer__body">
        <div class="other-offer__catalog">
            <h3>Что можно заказать</h3>
            <ul>
                <?php foreach (['telegram' => 'Telegram-боты', 'chip' => 'AI-интеграции', 'users' => 'CRM-доработки', 'database' => 'Парсеры', 'screen' => 'Личный кабинет', 'code' => 'Нестандартный функционал'] as $icon => $label): ?>
                    <li><?= $otherIcon($icon) ?><span><?= $label ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="other-offer__process">
            <h3>Как мы работаем</h3>
            <ol>
                <li><span aria-hidden="true">1</span><p>Обсуждаем задачу<br>и уточняем детали</p></li>
                <li><span aria-hidden="true">2</span><p>Подбираем оптимальное решение и оцениваем сроки</p></li>
                <li><span aria-hidden="true">3</span><p>Реализуем и поддерживаем при необходимости</p></li>
            </ol>
        </div>
    </div>
    <div class="landing-offer__footer">
        <p class="landing-offer__price">от <strong><?= number_format((int) $otherTariff['price'], 0, '', ' ') ?> ₽</strong></p>
        <p class="landing-offer__note">Итоговая стоимость зависит<br class="landing-offer__desktop-break"> от объёма и сложности задачи.</p>
        <button type="button" class="tariff-button landing-offer__button"
            data-id="<?= htmlspecialchars((string) $otherTariff['id'], ENT_QUOTES, 'UTF-8') ?>"
            data-service="<?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-name="<?= htmlspecialchars($otherTariff['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-price="<?= (int) $otherTariff['price'] ?>">Обсудить проект</button>
    </div>
    <?php if (is_file(__DIR__ . '/../assets/alien/tariffs/idle-manifest.js')): ?>
        <div class="tariffs-alien" data-contact-alien aria-hidden="true">
            <img class="contact-alien__fallback" src="assets/alien/tariffs/idle-full-poster.png" width="320" height="326" alt="" loading="lazy" draggable="false">
            <canvas class="contact-alien__canvas" width="320" height="326" hidden></canvas>
        </div>
    <?php else: ?>
        <img class="tariffs-alien" src="assets/images/tariffs-alien.png" width="1244" height="1264" alt="" aria-hidden="true" loading="lazy" draggable="false">
    <?php endif; ?>
</section>
