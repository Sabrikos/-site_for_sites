<?php

$title = 'Политика конфиденциальности | Vega Studio';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="styles.css">

    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=cursor-2">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=cursor-2">
    <link rel="apple-touch-icon" href="assets/images/favicon-512.png?v=cursor-2">
</head>
<body>
<main class="cart-page">
    <section class="cart-products">
        <h1>Политика конфиденциальности</h1>
        <p>На сайте собираются имя, телефон, email, комментарий к проекту и состав заявки.</p>
        <p>Данные используются для обработки заявки, связи с клиентом и подготовки предложения.</p>
        <p>Владелец сайта: укажите юридические данные владельца проекта.</p>
        <p>Контакт для вопросов по персональным данным: укажите email или другой официальный контакт.</p>
        <p>Данные не публикуются на сайте и доступны только администратору через защищённую админ-панель.</p>
        <a href="index.php" class="cart-back-button">На главную</a>
    </section>
</main>
</body>
</html>