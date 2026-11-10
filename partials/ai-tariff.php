<?php
// Keep the selected tariff ID and displayed price tied to the server catalog.
$aiIcon = static function (string $name): string {
    $paths = [
        'chip' => '<rect x="10" y="10" width="22" height="22" rx="3"/><path d="M15 3v7m12-7v7M15 32v7m12-7v7M3 15h7m-7 12h7m22-12h7m-7 12h7"/>',
        'bolt' => '<path d="m23 2-17 23h12l-2 15 18-25H22l1-13Z"/>',
        'layers' => '<path d="m3 12 17-8 17 8-17 8-17-8Zm0 9 17 8 17-8M3 30l17 8 17-8"/>',
        'chat' => '<path d="M7 4h28a5 5 0 0 1 5 5v17a5 5 0 0 1-5 5H18L8 39v-8H7a5 5 0 0 1-5-5V9a5 5 0 0 1 5-5Z"/><circle cx="12" cy="18" r="1.5"/><circle cx="21" cy="18" r="1.5"/><circle cx="30" cy="18" r="1.5"/>',
        'gear' => '<path d="m16 3 8 0 1 5 4 2 5-2 4 7-4 3v5l4 3-4 7-5-2-4 2-1 5h-8l-1-5-4-2-5 2-4-7 4-3v-5l-4-3 4-7 5 2 4-2 1-5Z"/><circle cx="20" cy="20.5" r="7"/>',
        'chart' => '<path d="M3 39V27h7v12H3Zm14 0V15h7v24h-7Zm14 0V3h7v36h-7Z"/>',
        'sparkles' => '<path d="M16 12c2 12 4 14 14 16-10 2-12 4-14 13C14 32 12 30 2 28c10-2 12-4 14-16Zm17-10c1 6 3 8 7 9-4 1-6 3-7 8-1-5-3-7-7-8 4-1 6-3 7-9Z"/>',
    ];
    return '<svg viewBox="0 0 42 42" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
};
?>
<section class="service-tariffs landing-offer ai-offer" id="ai" aria-labelledby="ai-offer-title">
    <div class="landing-offer__hero">
        <div class="landing-offer__visual">
            <img src="assets/images/tariffs/ai-devices-v2.png" width="1536" height="1024" alt="Ноутбук с символом AI, чат-панелями и виртуальным помощником" decoding="async" loading="lazy">
        </div>
        <div class="landing-offer__intro">
            <div class="landing-offer__title-row">
                <div>
                    <h2 id="ai-offer-title">ИИ-решения</h2>
                    <p>Интеграция искусственного интеллекта в ваш бизнес для автоматизации, коммуникации и роста эффективности.</p>
                </div>
            </div>
            <div class="landing-offer__badges">
                <span><?= $aiIcon('bolt') ?>Под ваши задачи</span>
                <span><?= $aiIcon('layers') ?>Готовая интеграция и настройка</span>
            </div>
        </div>
    </div>
    <div class="landing-offer__body">
        <div class="landing-offer__benefits">
            <h3>Что вы получите</h3>
            <ul>
                <li>Подбор AI-инструментов под вашу задачу</li>
                <li>Настройку бота, ассистента или инструментов для работы с контентом</li>
                <li>Подключение к сайту и сервисам, согласованным в проекте</li>
                <li>Проверку работы и понятную инструкцию по использованию</li>
            </ul>
        </div>
        <div class="landing-offer__included">
            <h3>Примеры решений</h3>
            <ul>
                <li><span><?= $aiIcon('chat') ?></span><div>Чат-боты и виртуальные ассистенты<small>(сайт, Telegram, соцсети)</small></div></li>
                <li><span><?= $aiIcon('gear') ?></span><div>AI-помощники для обработки заявок,<small>консультаций и поддержки</small></div></li>
                <li><span><?= $aiIcon('chart') ?></span><div>Автоматизация контента<small>(тексты, изображения, идеи)</small></div></li>
            </ul>
        </div>
    </div>
    <div class="landing-offer__footer">
        <p class="landing-offer__price">от <strong><?= number_format((int) $aiTariff['price'], 0, '', ' ') ?> ₽</strong></p>
        <p class="landing-offer__note">Стоимость зависит от задачи, объёма интеграции<br class="landing-offer__desktop-break"> и выбранных инструментов.</p>
        <button type="button" class="tariff-button landing-offer__button"
            data-id="<?= htmlspecialchars((string) $aiTariff['id'], ENT_QUOTES, 'UTF-8') ?>"
            data-service="<?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-name="<?= htmlspecialchars($aiTariff['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-price="<?= (int) $aiTariff['price'] ?>">Обсудить проект</button>
    </div>
</section>
