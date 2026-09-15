<?php

require_once __DIR__ . '/app.php';

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
        <p>Сайт технически может обрабатывать имя, телефон, email, комментарий, состав заявки, сообщения web-чата, Telegram ID и username, сообщения Telegram, а также технические записи сервера.</p>
        <p>Эти данные используются для обработки обращения, связи с клиентом, продолжения диалога и работы админ-панели.</p>
        <p>При использовании Telegram данные обращения и уведомления могут передаваться в рабочую группу поддержки Vega Studio. При подключённом AI-провайдере ему передаются текст текущего сообщения, ограниченная история диалога и каталог услуг для подготовки ответа.</p>
        <p>Не отправляйте в формы и чат пароли, платёжные реквизиты или другие секретные данные.</p>
        <p><strong>TODO владельцу:</strong> указать владельца сайта, официальный контакт для вопросов о данных, основания и сроки хранения, перечень получателей данных и порядок отзыва согласия.</p>
        <p>Финальную редакцию политики и согласия необходимо проверить с профильным юристом до публикации сайта.</p>
        <a href="index.php" class="cart-back-button">На главную</a>
    </section>
</main>
</body>
</html>
