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
<?php require __DIR__ . '/analytics.php'; ?>
<main class="cart-page">
    <section class="cart-products legal-document">
        <h1>Согласие на обработку персональных данных</h1>
        <p><strong>Проект редакции от 10 ноября 2026 года. Версия <?= appEscape(ConsentService::CONSENT_VERSION) ?>.</strong></p>
        <p><strong>Документ не готов к окончательной публикации.</strong> Пробелы обозначены как <strong>????????????</strong>. До их заполнения этот проект не следует использовать для получения согласий реальных посетителей.</p>
        <p>Ниже приведён текст самостоятельного согласия для двух форм заявки. Принятие политики или условий заказа его не заменяет. Согласие на необязательную аналитику оформляется отдельно.</p>
        <?php foreach (ConsentService::CONSENT_SECTIONS as [$heading, $paragraph]): ?>
            <h2><?= appEscape($heading) ?></h2>
            <p><?= appEscape($paragraph) ?></p>
        <?php endforeach; ?>

        <p><a href="privacy.php">Политика обработки персональных данных</a></p>
        <a href="index.php" class="cart-back-button">На главную</a>
    </section>
</main>
</body>
</html>
