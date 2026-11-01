<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/services/ConsentService.php';

$title = 'Согласие на обработку персональных данных | Vega Studio';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= appEscape($title) ?></title>
    <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=cursor-3">
</head>
<body>
<main class="cart-page">
    <section class="cart-products legal-document">
        <h1>Согласие на обработку персональных данных</h1>
        <p><strong>Версия:</strong> <?= appEscape(ConsentService::CONSENT_VERSION) ?> от 31 октября 2026 года.</p>

        <p><?= appEscape(ConsentService::CONSENT_TEXT) ?></p>

        <h2>Какие данные обрабатываются</h2>
        <p>Имя, номер телефона, текст обращения, сведения о выбранной услуге или тарифе.</p>

        <h2>Цель обработки</h2>
        <p>Ответ на обращение, обсуждение задачи, подготовка предложения и связь с пользователем по его инициативе.</p>

        <h2>Действия с данными</h2>
        <p>Сбор, запись, систематизация, накопление, хранение, уточнение, извлечение, использование, блокирование, удаление и уничтожение с применением средств автоматизации или без них.</p>

        <h2>Срок и отзыв согласия</h2>
        <p>Согласие действует до достижения цели обработки, его отзыва или истечения одного года с даты обращения — в зависимости от того, что наступит раньше. Отзыв можно направить на <a href="mailto:Ve9a.Studio@yandex.ru">Ve9a.Studio@yandex.ru</a>, указав имя, номер телефона и дату обращения, чтобы оператор смог найти запись.</p>

        <p>Отзыв не влияет на законность обработки, выполненной до его получения. Если закон требует сохранить отдельные сведения после отзыва, они обрабатываются только в пределах такого требования.</p>

        <p><a href="privacy.php">Политика обработки персональных данных</a></p>
        <a href="index.php" class="cart-back-button">На главную</a>
    </section>
</main>
</body>
</html>
