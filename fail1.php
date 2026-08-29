<?php
$title = 'WebStart Studio';
$subtitle = 'Продвигаем ваш бизнес в сети';
$author = 'Команда WebStart Studio';
$customerName = '';
$projectType = '';
$projectTheme = '';
$selectedService = null;
$errors = [];

$services = [
    [
        'name' => 'Лендинг/Одностраничный сайт',
        'slug' => 'landing',
        'price' => 15000,
        'deadline' => '7 дней',
        'available' => true,
    ],
    [
        'name' => 'Интернет магазин',
        'slug' => 'shop',
        'price' => 30000,
        'deadline' => '7 дней',
        'available' => true,
    ],
    [
        'name' => 'Доработка сайта',
        'slug' => 'revision',
        'price' => 5000,
        'deadline' => '7 дней',
        'available' => true,
    ],
    [
        'name' => 'Другое',
        'slug' => 'other',
        'price' => 10000,
        'deadline' => '7 дней',
        'available' => true,
    ],
];
#обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $projectType = trim($_POST['project_type'] ?? '');
    $projectTheme = trim($_POST['project_theme'] ?? '');

    if ($customerName === '') {
        $errors[] = 'Введите имя';
    }

    if ($projectType === '') {
        $errors[] = 'Выберите тип сайта';
    }

    if ($projectTheme === '') {
        $errors[] = 'Опишите тему проекта';
    }

    if (empty($errors)) {
        foreach ($services as $service) {
            if ($service['name'] === $projectType) {
                $selectedService = $service;
                break;
            }
        }
    }

    if ($selectedService === null) {
        $errors[] = 'Выбранная услуга не найдена';
    }
}

?>


<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header>
        <h1><?= $title ?></h1>
        <p><?= $subtitle ?></p>
    </header>

    <main>
        <section>
            <h2>О студии</h2>

            <p>
                Мы разрабатываем информационные сайты, интернет-магазины
                и страницы для продвижения услуг.
            </p>
        </section>
        <section>
            <h2>Наши услуги</h2>

            <div class="services-grid">

                <?php foreach ($services as $service): ?>
                    <article class="service-card">
                        <h3><?= $service['name'] ?></h3>
                        <p>Цена: от <?= $service['price'] ?> ₽</p>
                        <p>Срок: <?= $service['deadline'] ?></p>

                        <?php if ($service['price'] >= 30000): ?>
                            <p>Категория: крупный проект</p>
                        <?php else: ?>
                            <p>Категория: стартовый проект</p>
                        <?php endif; ?>

                        <?php if ($service['available']): ?>
                            <a
                                href="tariffs.php#<?= htmlspecialchars($service['slug']) ?>"
                                class="service-button">
                                Подробнее
                            </a>
                        <?php else: ?>
                            <p>Сейчас недоступно</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section>
            <h2>Оставить заявку</h2>

            <form method="post">
                <p>
                    <label for="customer_name">Ваше имя:</label>
                    <input
                        type="text"
                        id="customer_name"
                        name="customer_name"
                        value="<?= htmlspecialchars($customerName) ?>">
                </p>

                <p>
                    <label for="project_type">Тип сайта:</label>
                    <select
                        id="project_type"
                        name="project_type"
                        required>
                        <option value="">Выберите тип сайта</option>

                        <?php foreach ($services as $service): ?>
                            <?php if ($service['available']): ?>
                                <option
                                    value="<?= htmlspecialchars($service['name']) ?>"
                                    <?= $projectType === $service['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($service['name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label for="project_theme">Тема проекта:</label>
                    <textarea
                        id="project_theme"
                        name="project_theme"
                        required></textarea>
                </p>

                <button type="submit">Отправить заявку</button>
            </form>

            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <p>Пожалуйста, исправьте ошибки:</p>

                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)): ?>
                <div class="success">
                    <p>
                        Спасибо, <?= htmlspecialchars($customerName) ?>!
                        Мы получили вашу заявку.
                    </p>

                    <p>
                        Тип сайта: <?= htmlspecialchars($projectType) ?>
                    </p>

                    <?php if ($selectedService !== null): ?>
                        <p>
                            Ориентировочная цена: от <?= $selectedService['price'] ?> ₽
                        </p>
                    <?php endif; ?>

                    <p>
                        Тема проекта: <?= htmlspecialchars($projectTheme) ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p><?= $author ?></p>
    </footer>
</body>

</html>