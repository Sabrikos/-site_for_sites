<?php

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/bd.php';
$title = 'Vega Studio';
$subtitle = 'Продвигаем ваш бизнес в сети';
$customerName = '';
$customerPhone = '';
$customerEmail = '';
$projectType = '';
$projectTheme = '';
$selectedService = null;
$errors = [];

$sql = "

    SELECT

        s.id,
        s.name,
        s.slug,
        s.description,
        s.deadline,

        MIN(t.price) AS price

    FROM services s

    JOIN tariffs t
        ON t.service_id = s.id

    WHERE
        s.active = 1
        AND t.active = 1

    GROUP BY
        s.id,
        s.name,
        s.slug,
        s.description,
        s.deadline

    ORDER BY s.id

";


$stmt = $pdo->query($sql);

$services = $stmt->fetchAll();



#обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerPhone = trim($_POST['customer_phone'] ?? '');
    $customerEmail = trim($_POST['customer_email'] ?? '');
    $projectType = trim($_POST['project_type'] ?? '');
    $projectTheme = trim($_POST['project_theme'] ?? '');
    $personalDataConsent = isset($_POST['personal_data_consent']);

    if ($customerName === '') {
        $errors[] = 'Введите имя';
    }

    if ($customerPhone === '') {
        $errors[] = 'Введите номер телефона';
    }

    if ($customerEmail === '') {
        $errors[] = 'Введите электронную почту';
    }

    if (
        $customerEmail !== ''
        && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Введите корректный адрес электронной почты';
    }

    if ($projectType === '') {
        $errors[] = 'Выберите тип сайта';
    }

    if ($projectTheme === '') {
        $errors[] = 'Опишите тему проекта';
    }

    if (!$personalDataConsent) {
        $errors[] = 'Подтвердите согласие на обработку персональных данных';
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
    <link rel="stylesheet" href="styles.css?v=brand-vega-1">
</head>

<body>
    <?php renderHeader(); ?>

    <section class="hero-section">

        <svg class="hero-background-lines" viewBox="0 0 1440 720" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path class="hero-bg-line hero-bg-line-main" d="M-120 196 C110 104 260 250 440 168 C650 72 770 244 960 158 C1138 78 1268 148 1560 92" />
            <path class="hero-bg-line hero-bg-line-soft" d="M-90 328 C116 252 316 374 500 288 C684 204 816 330 1010 256 C1168 196 1308 268 1530 220" />
            <path class="hero-bg-line hero-bg-line-low" d="M-130 508 C126 406 312 574 548 462 C754 364 902 524 1110 430 C1270 356 1380 452 1534 386" />
            <path class="hero-bg-line hero-bg-line-orbit hero-bg-line-orbit-one" d="M820 360 C930 262 1110 248 1236 320 C1364 394 1286 500 1108 502 C926 506 770 458 820 360" />
            <path class="hero-bg-line hero-bg-line-orbit hero-bg-line-orbit-two" d="M908 240 C1018 198 1166 204 1278 272 C1376 332 1332 420 1190 438 C1038 456 878 398 852 318 C838 276 864 256 908 240" />
        </svg>

        <div class="hero-content">

            <span class="hero-label">
                Веб-студия для бизнеса
            </span>

            <h1>
                Разрабатываем сайты, которые помогают продавать
            </h1>

            <p>
               Продумываем дизайн, функционал и путь клиента с фокусом на результат.
            </p>

            <div class="hero-buttons">

                <a
                    href="#application"
                    class="hero-main-button">
                    Обсудить проект
                </a>

                <a
                    href="tariffs.php"
                    class="hero-secondary-button">
                    Смотреть тарифы
                </a>

            </div>

        </div>

        <div class="hero-visual" aria-hidden="true">
            <div class="hero-orbit hero-orbit-one"></div>
            <div class="hero-orbit hero-orbit-two"></div>

            <div class="hero-planet">
                <svg class="sphere-code-map" viewBox="0 0 260 260" aria-hidden="true" focusable="false">
                    <defs>
                        <path id="sphere-code-path-1" d="M18 20 C76 38 184 38 242 20" />
                        <path id="sphere-code-path-2" d="M10 36 C72 60 188 60 250 36" />
                        <path id="sphere-code-path-3" d="M6 54 C70 84 190 84 254 54" />
                        <path id="sphere-code-path-4" d="M4 76 C68 110 192 110 256 76" />
                        <path id="sphere-code-path-5" d="M2 100 C66 136 194 136 258 100" />
                        <path id="sphere-code-path-6" d="M0 126 C65 164 195 164 260 126" />
                        <path id="sphere-code-path-7" d="M2 150 C66 186 194 186 258 150" />
                        <path id="sphere-code-path-8" d="M4 174 C68 208 192 208 256 174" />
                        <path id="sphere-code-path-9" d="M6 196 C70 226 190 226 254 196" />
                        <path id="sphere-code-path-10" d="M10 216 C72 240 188 240 250 216" />
                        <path id="sphere-code-path-11" d="M18 236 C76 252 184 252 242 236" />
                    </defs>

                    <g class="sphere-code-lines">
                        <text><textPath href="#sphere-code-path-1" startOffset="0">deploy(); require_once 'layout.php'; renderHeader();</textPath></text>
                        <text><textPath href="#sphere-code-path-2" startOffset="0">const page = createLanding(); validateForm(); sendRequest();</textPath></text>
                        <text><textPath href="#sphere-code-path-3" startOffset="0">function buildSite($service, $tariff){ return $orderId; }</textPath></text>
                        <text><textPath href="#sphere-code-path-4" startOffset="0">SELECT name, price FROM tariffs WHERE active = 1;</textPath></text>
                        <text><textPath href="#sphere-code-path-5" startOffset="0">if ($errors === []) { createOrder($customer, $project); }</textPath></text>
                        <text><textPath href="#sphere-code-path-6" startOffset="0">$pdo-&gt;prepare($sql); $stmt-&gt;execute(); fetchAll();</textPath></text>
                        <text><textPath href="#sphere-code-path-7" startOffset="0">class VegaProject { public function launch(): bool {} }</textPath></text>
                        <text><textPath href="#sphere-code-path-8" startOffset="0">addToCart(serviceId); localStorage.setItem('webstartCart', cart);</textPath></text>
                        <text><textPath href="#sphere-code-path-9" startOffset="0">try { connectDatabase(); } catch (PDOException $error) {}</textPath></text>
                        <text><textPath href="#sphere-code-path-10" startOffset="0">foreach ($services as $service) { renderCard($service); }</textPath></text>
                        <text><textPath href="#sphere-code-path-11" startOffset="0">git commit -m 'ship feature'; git push origin Kseha;</textPath></text>
                    </g>
                </svg>
            </div>
            <div class="hero-rocket">
                🚀
            </div>

            <div class="hero-tech hero-tech-html">HTML</div>
            <div class="hero-tech hero-tech-css">CSS</div>
            <div class="hero-tech hero-tech-php">PHP</div>
            <div class="hero-tech hero-tech-js">JS</div>
            <div class="hero-tech hero-tech-cpp">C++</div>
            <div class="hero-tech hero-tech-ts">TypeScript</div>

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

            <div class='services-grid'>

                <?php foreach ($services as $service): ?>
                    <article class='service-card'>
                        <h3><?= htmlspecialchars($service['name']) ?></h3>
                        <p>&#1062;&#1077;&#1085;&#1072;: &#1086;&#1090; <?= number_format((int) $service['price'], 0, '', ' ') ?> &#8381;</p>
                        <p>&#1057;&#1088;&#1086;&#1082;: <?= htmlspecialchars($service['deadline'] ?? '') ?></p>

                        <?php if ((int) $service['price'] >= 30000): ?>
                            <p>&#1050;&#1072;&#1090;&#1077;&#1075;&#1086;&#1088;&#1080;&#1103;: &#1082;&#1088;&#1091;&#1087;&#1085;&#1099;&#1081; &#1087;&#1088;&#1086;&#1077;&#1082;&#1090;</p>
                        <?php else: ?>
                            <p>&#1050;&#1072;&#1090;&#1077;&#1075;&#1086;&#1088;&#1080;&#1103;: &#1089;&#1090;&#1072;&#1088;&#1090;&#1086;&#1074;&#1099;&#1081; &#1087;&#1088;&#1086;&#1077;&#1082;&#1090;</p>
                        <?php endif; ?>

                        <a class='button service-button'
                            href='tariffs.php#<?= htmlspecialchars($service['slug']) ?>'>
                            &#1055;&#1086;&#1076;&#1088;&#1086;&#1073;&#1085;&#1077;&#1077;
                        </a>
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
                                vega@example.ru
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
                                @vegastudio
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
                                Vega Studio
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

                        <label for="customer_phone">
                            Номер телефона
                        </label>

                        <input
                            type="tel"
                            id="customer_phone"
                            name="customer_phone"
                            placeholder="+7 (___) ___-__-__"
                            autocomplete="tel"
                            value="<?= htmlspecialchars($customerPhone) ?>"
                            required>

                    </div>

                    <div class="form-group">

                        <label for="customer_email">
                            Электронная почта
                        </label>

                        <input
                            type="email"
                            id="customer_email"
                            name="customer_email"
                            placeholder="example@mail.ru"
                            autocomplete="email"
                            value="<?= htmlspecialchars($customerEmail) ?>"
                            required>

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

                                <option
                                    value="<?= htmlspecialchars($service['name']) ?>"
                                    <?= $projectType === $service['name'] ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($service['name']) ?>

                                </option>

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

                    <label class="form-consent">
                        <input type="checkbox" name="personal_data_consent" value="1" required>
                        <span>Я согласен на обработку персональных данных и ознакомлен с <a href="privacy.php" target="_blank">политикой конфиденциальности</a>.</span>
                    </label>

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

    <?php renderFooter(); ?>
<script src="cursor-stars.js?v=footer-mask-1"></script>
<script src="sphere-code-loop.js?v=canvas-render-2"></script>
</body>

</html>



