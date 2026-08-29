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

    <header class="main-header">

        <div class="container">

            <a
                href="index.php"
                class="logo">
                WebStart Studio
            </a>


            <nav>

                <a href="index.php">
                    Главная
                </a>

                <a href="#services">
                    Услуги
                </a>

                <a href="tariffs.php">
                    Тарифы
                </a>

                <a href="#technologies">
                    Технологии
                </a>

                <a href="#projects">
                    Проекты
                </a>

                <a href="#application">
                    Контакты
                </a>


                <a
                    href="cart.php"
                    class="cart-menu-link"
                    aria-label="Корзина">

                    <svg
                        class="cart-icon"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg">

                        <path
                            d="M3 3H5L7.2 14.2C7.4 15.2 8.3 16 9.4 16H17.5C18.5 16 19.4 15.3 19.7 14.3L21 8H6"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round" />

                        <circle
                            cx="10"
                            cy="20"
                            r="1.5"
                            fill="currentColor" />

                        <circle
                            cx="18"
                            cy="20"
                            r="1.5"
                            fill="currentColor" />

                    </svg>


                    <span
                        class="cart-counter"
                        id="cartCounter">
                        0
                    </span>

                </a>

            </nav>

        </div>

    </header>

    <section class="hero-section">

        <h1>
            <?= htmlspecialchars($title) ?>
        </h1>

        <p>
            <?= htmlspecialchars($subtitle) ?>
        </p>

        <div class="hero-buttons">

            <a
                href="#services"
                class="hero-main-button">
                Наши услуги
            </a>

            <a
                href="tariffs.php"
                class="hero-secondary-button">
                Посмотреть тарифы
            </a>

        </div>

    </section>

    <main>
        <section>
            <h2>О студии</h2>

            <p>
                Мы разрабатываем информационные сайты, интернет-магазины
                и страницы для продвижения услуг.
            </p>
        </section>

        <!-- услуги -->

        <section id="services">

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
                            <a class="button"
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

        <!-- наши технологии -->

        <section class="technologies-section" id="technologies">

            <div class="section-heading">

                <span class="section-label">
                    Технологии
                </span>

                <h2>
                    Наш технологический стек
                </h2>

                <p>
                    Используем современные инструменты для создания
                    быстрых, удобных и масштабируемых проектов.
                </p>

            </div>


            <div class="technology-groups">


                <div class="technology-group">

                    <h3>Frontend</h3>

                    <div class="technology-list">

                        <span class="technology-item">HTML5</span>

                        <span class="technology-item">CSS3</span>

                        <span class="technology-item">JavaScript</span>

                        <span class="technology-item">React</span>

                        <span class="technology-item">TypeScript</span>

                    </div>

                </div>


                <div class="technology-group">

                    <h3>Backend</h3>

                    <div class="technology-list">

                        <span class="technology-item">PHP</span>

                        <span class="technology-item">Laravel</span>

                        <span class="technology-item">Python</span>

                        <span class="technology-item">Node.js</span>

                        <span class="technology-item">MySQL</span>

                    </div>

                </div>


                <div class="technology-group">

                    <h3>AI и интеграции</h3>

                    <div class="technology-list">

                        <span class="technology-item">OpenAI API</span>

                        <span class="technology-item">GigaChat</span>

                        <span class="technology-item">Telegram API</span>

                        <span class="technology-item">AI-ассистенты</span>

                    </div>

                </div>


            </div>

        </section>

        <!-- блок cms -->

        <section class="cms-section">

            <div class="section-heading">

                <span class="section-label">
                    CMS и платформы
                </span>

                <h2>
                    Работаем с популярными CMS
                </h2>

                <p>
                    Подбираем платформу под задачи проекта,
                    бюджет и дальнейшее развитие сайта.
                </p>

            </div>


            <div class="cms-grid">


                <article class="cms-card">

                    <div class="cms-logo">
                        WP
                    </div>

                    <h3>
                        WordPress
                    </h3>

                    <p>
                        Для корпоративных сайтов,
                        блогов и небольших проектов.
                    </p>

                </article>


                <article class="cms-card">

                    <div class="cms-logo">
                        B24
                    </div>

                    <h3>
                        1С-Битрикс
                    </h3>

                    <p>
                        Для интернет-магазинов,
                        каталогов и крупных бизнес-проектов.
                    </p>

                </article>


                <article class="cms-card">

                    <div class="cms-logo">
                        T
                    </div>

                    <h3>
                        Tilda
                    </h3>

                    <p>
                        Для быстрых лендингов,
                        презентационных сайтов и тестирования идей.
                    </p>

                </article>


                <article class="cms-card">

                    <div class="cms-logo">
                        W
                    </div>

                    <h3>
                        Webflow
                    </h3>

                    <p>
                        Для современных сайтов
                        с необычным дизайном и анимациями.
                    </p>

                </article>


                <article class="cms-card">

                    <div class="cms-logo">
                        PHP
                    </div>

                    <h3>
                        Собственная разработка
                    </h3>

                    <p>
                        Создаём проекты без CMS,
                        когда требуется индивидуальная логика.
                    </p>

                </article>


                <article class="cms-card">

                    <div class="cms-logo">
                        AI
                    </div>

                    <h3>
                        AI-интеграции
                    </h3>

                    <p>
                        Добавляем AI-ассистентов,
                        чат-ботов и автоматизацию.
                    </p>

                </article>


            </div>

        </section>

        <!-- наши проекты -->

        <section class="projects-section"
            id="projects">
            <div class="section-heading">

                <span class="section-label">
                    Портфолио
                </span>

                <h2>
                    Наши проекты
                </h2>

                <p>
                    Несколько примеров сайтов и решений,
                    над которыми мы работали.
                </p>

            </div>


            <div class="projects-grid">


                <article class="project-card">

                    <div class="project-preview project-preview-one">

                        <span>
                            Интернет-магазин
                        </span>

                    </div>


                    <div class="project-content">

                        <span class="project-type">
                            Интернет-магазин
                        </span>

                        <h3>
                            Aura Store
                        </h3>

                        <p>
                            Интернет-магазин с каталогом,
                            карточками товаров и корзиной.
                        </p>


                        <div class="project-tags">

                            <span>PHP</span>

                            <span>JavaScript</span>

                            <span>MySQL</span>

                        </div>

                    </div>

                </article>


                <article class="project-card">

                    <div class="project-preview project-preview-two">

                        <span>
                            Сайт услуг
                        </span>

                    </div>


                    <div class="project-content">

                        <span class="project-type">
                            Корпоративный сайт
                        </span>

                        <h3>
                            Beauty Studio
                        </h3>

                        <p>
                            Сайт салона с услугами,
                            формой обратной связи и адаптивным дизайном.
                        </p>


                        <div class="project-tags">

                            <span>HTML</span>

                            <span>CSS</span>

                            <span>PHP</span>

                        </div>

                    </div>

                </article>


                <article class="project-card project-coming">

                    <div class="coming-content">

                        <span>
                            Новый проект
                        </span>

                        <h3>
                            Скоро здесь будет ещё один проект
                        </h3>

                        <p>
                            Мы постоянно пополняем портфолио новыми работами.
                        </p>

                    </div>

                </article>


            </div>

        </section>
        <!-- контакты -->

        <section
            class="contact-section"
            id="application">

            <div class="contact-info">

                <span class="section-label">
                    Связаться с нами
                </span>

                <h2>
                    Есть идея проекта?
                </h2>

                <p class="contact-description">
                    Расскажите нам о своей задаче.
                    Мы обсудим проект, предложим подходящее решение
                    и предварительно оценим стоимость.
                </p>


                <div class="contact-items">

                    <div class="contact-item">

                        <span class="contact-icon">
                            @
                        </span>

                        <div>

                            <span>
                                Email
                            </span>

                            <strong>
                                webstart@example.ru
                            </strong>

                        </div>

                    </div>


                    <div class="contact-item">

                        <span class="contact-icon">
                            TG
                        </span>

                        <div>

                            <span>
                                Telegram
                            </span>

                            <strong>
                                @webstart
                            </strong>

                        </div>

                    </div>


                    <div class="contact-item">

                        <span class="contact-icon">
                            VK
                        </span>

                        <div>

                            <span>
                                ВКонтакте
                            </span>

                            <strong>
                                WebStart Studio
                            </strong>

                        </div>

                    </div>

                </div>


                <div class="contact-benefits">

                    <p>
                        ✓ Бесплатно обсудим проект
                    </p>

                    <p>
                        ✓ Рассчитаем предварительную стоимость
                    </p>

                    <p>
                        ✓ Предложим подходящие технологии
                    </p>

                </div>

            </div>

            <!-- заявка -->

            <div class="contact-form-wrapper">

                <h3>
                    Оставить заявку
                </h3>


                <form method="post">

                    <div class="form-group">

                        <label for="customer_name">
                            Ваше имя
                        </label>

                        <input
                            type="text"
                            id="customer_name"
                            name="customer_name"
                            placeholder="Как к вам обращаться?"
                            value="<?= htmlspecialchars($customerName) ?>">

                    </div>


                    <div class="form-group">

                        <label for="project_type">
                            Что вас интересует?
                        </label>

                        <select
                            id="project_type"
                            name="project_type"
                            required>

                            <option value="">
                                Выберите услугу
                            </option>


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

                    </div>


                    <div class="form-group">

                        <label for="project_theme">
                            Расскажите о проекте
                        </label>

                        <textarea
                            id="project_theme"
                            name="project_theme"
                            placeholder="Например: нужен лендинг для строительной компании..."
                            required><?= htmlspecialchars($projectTheme) ?></textarea>

                    </div>


                    <button
                        type="submit"
                        class="contact-submit">
                        Отправить заявку
                    </button>

                </form>


                <?php if (!empty($errors)): ?>

                    <div class="errors">

                        <ul>

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    <?= htmlspecialchars($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <?php if (
                    $_SERVER['REQUEST_METHOD'] === 'POST'
                    && empty($errors)
                ): ?>

                    <div class="success">

                        Спасибо,
                        <?= htmlspecialchars($customerName) ?>!

                        Мы получили вашу заявку.

                    </div>

                <?php endif; ?>


            </div>

        </section>
    </main>

    <footer class="main-footer">

        <div class="footer-grid">


            <div class="footer-brand">

                <a
                    href="index.php"
                    class="footer-logo">
                    WebStart Studio
                </a>

                <p>
                    Создаём сайты, интернет-магазины,
                    Telegram-ботов и AI-решения для бизнеса.
                </p>

            </div>



            <div class="footer-column">

                <h3>
                    Услуги
                </h3>

                <a href="tariffs.php#landing">
                    Лендинги
                </a>

                <a href="tariffs.php#shop">
                    Интернет-магазины
                </a>

                <a href="tariffs.php#revision">
                    Доработка сайтов
                </a>

                <a href="tariffs.php#ai">
                    AI-решения
                </a>

            </div>



            <div class="footer-column">

                <h3>
                    Студия
                </h3>

                <a href="#technologies">
                    Технологии
                </a>

                <a href="#projects">
                    Наши проекты
                </a>

                <a href="#application">
                    Контакты
                </a>

            </div>



            <div class="footer-column">

                <h3>
                    Связаться
                </h3>

                <a href="#">
                    Telegram
                </a>

                <a href="#">
                    ВКонтакте
                </a>

                <a href="mailto:webstart@example.ru">
                    webstart@example.ru
                </a>

            </div>


        </div>



        <div class="footer-bottom">

            <span>
                © <?= date('Y') ?> WebStart Studio
            </span>

            <span>
                Разработка цифровых решений
            </span>

        </div>

    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            function updateHeaderCartCounter() {

                const cartCounter =
                    document.getElementById('cartCounter');

                if (!cartCounter) {
                    return;
                }

                let cart = [];

                try {
                    cart =
                        JSON.parse(
                            localStorage.getItem('webstartCart')
                        ) || [];
                } catch (error) {
                    cart = [];
                }

                cartCounter.textContent = cart.length;

            }

            updateHeaderCartCounter();

        });
    </script>

</body>

</html>