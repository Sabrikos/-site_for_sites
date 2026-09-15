<?php

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/layout.php';
$title = 'WebStart Studio';
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
    <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">

    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png?v=cursor-3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png?v=cursor-3">
    <link rel="apple-touch-icon" href="assets/images/favicon-512.png?v=cursor-3">
</head>

<body class="home-page">
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

            <h1 class="hero-title">
                <span class="hero-title-line">Разрабатываем</span>
                <span class="hero-title-line">сайты, которые</span>
                <span class="hero-title-line"><span class="hero-title-accent">помогают</span> продавать</span>
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
                        <text><textPath href="#sphere-code-path-7" startOffset="0">class WebStartProject { public function launch(): bool {} }</textPath></text>
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
        <section class="about-studio" id="about" aria-labelledby="about-title">
            <div class="about-studio__space" aria-hidden="true"><div class="about-studio__planet"></div></div>
            <div class="about-studio__content">
                <span class="about-studio__eyebrow">VEGA STUDIO</span>
                <h2 id="about-title">О <span>студии</span></h2>
                <p class="about-studio__description">Мы разрабатываем информационные сайты, интернет-магазины и страницы для продвижения услуг. Помогаем бизнесу расти в онлайн-пространстве, создавая современные и эффективные решения.</p>
                <ul class="about-studio__benefits">
                    <li><span class="about-studio__icon"><svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M5 11 10 5h12l5 6-11 16L5 11Zm0 0h22M10 5l6 22L22 5M10 5l6 6 6-6"/></svg></span><span>Современные<br>технологии</span></li>
                    <li><span class="about-studio__icon"><svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><circle cx="16" cy="10" r="4"/><path d="M8 27v-5c0-7 16-7 16 0v5H8ZM7 8c-5 1-5 7 0 8M25 8c5 1 5 7 0 8M5 20c-3 1-3 4-3 6h3M27 20c3 1 3 4 3 6h-3"/></svg></span><span>Индивидуальный<br>подход</span></li>
                    <li><span class="about-studio__icon"><svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M5 27h23M8 24v-8M16 24V10M24 24V4"/></svg></span><span>Результат,<br>а не шаблоны</span></li>
                </ul>
            </div>
            <div class="about-studio__note">
                <span>Идеи становятся<br>сайтами</span>
                <i aria-hidden="true"></i>
                <p>Ваши задачи.<br>Наши решения.<br>Больше возможностей.</p>
            </div>
        </section>

        <!-- услуги -->

        <section id="services" class="studio-services" aria-labelledby="services-title">
            <div class="studio-services__heading">
                <div><span class="studio-services__label">Услуги</span><h2 id="services-title">Наши <span>услуги</span></h2></div>
                <p>Полный цикл разработки — от идеи до запуска.<br>Выберите подходящее решение или свяжитесь с нами для консультации.</p>
            </div>

            <div class='services-grid'>

                <?php foreach ($services as $service): ?>
                    <article class='service-card'>
                        <div class="service-card__intro">
                            <span class="service-card__icon" aria-hidden="true">
                                <svg viewBox="0 0 32 32" fill="none">
                                <?php switch ($service['slug']): case 'landing': ?>
                                    <rect x="4" y="5" width="24" height="22" rx="2"/><path d="M4 11h24M8 8h1m3 0h1"/>
                                <?php break; case 'shop': ?>
                                    <path d="M3 5h4l4 17h14l4-12H9"/><circle cx="13" cy="27" r="1.5"/><circle cx="24" cy="27" r="1.5"/>
                                <?php break; case 'revision': ?>
                                    <path d="M19 5a8 8 0 0 0-9 10L3 23a4 4 0 0 0 6 6l8-8a8 8 0 0 0 10-10l-6 6-6-6 4-6Z"/>
                                <?php break; case 'ai': ?>
                                    <rect x="8" y="8" width="16" height="16" rx="4"/><path d="M12 3v5m8-5v5M12 24v5m8-5v5M3 12h5m-5 8h5m16-8h5m-5 8h5M12 16h8m-4-4v8"/>
                                <?php break; default: ?>
                                    <circle cx="7" cy="16" r="2"/><circle cx="16" cy="16" r="2"/><circle cx="25" cy="16" r="2"/>
                                <?php endswitch; ?>
                                </svg>
                            </span>
                            <div><h3><?= htmlspecialchars($service['name']) ?></h3><p class="service-card__description"><?= htmlspecialchars($service['description'] ?? '') ?></p></div>
                        </div>
                        <span class="service-card__category"><?= (int) $service['price'] >= 30000 ? 'Крупный проект' : 'Стартовый проект' ?></span>
                        <div class="service-card__bottom">
                            <div class="service-card__detail"><span class="service-card__mini-icon" aria-hidden="true">₽</span><span><small>Цена:</small>от <?= number_format((int) $service['price'], 0, '', ' ') ?> ₽</span></div>
                            <div class="service-card__detail"><span class="service-card__mini-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg></span><span><small>Срок:</small><?= htmlspecialchars($service['deadline'] ?: 'По договорённости') ?></span></div>
                            <a class="button service-button" href="tariffs.php#<?= htmlspecialchars($service['slug']) ?>">Подробнее <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
                <div class="studio-services__message"><span>Больше возможностей<br>начинаются с хорошего сайта</span><i aria-hidden="true"></i></div>
            </div>
        </section>

        <!-- наши технологии -->

        <section class="technologies-section studio-stack" id="technologies" aria-labelledby="stack-title">

            <div class="studio-stack__circuit" aria-hidden="true">
                <svg viewBox="0 0 440 200" fill="none">
                    <g stroke="currentColor" stroke-width="1">
                        <path d="M184 70H135L100 35H25M184 90H110L80 60H0M184 110H90L60 140H10M184 130H140L95 185H30M250 70H290L335 20H420M250 90H320L350 60H440M250 110H300L345 155H430M250 130H275L320 195H405M204 55V30L180 5M230 55V15M205 145V180L180 200M230 145V200"/>
                        <rect x="179" y="53" width="78" height="96" rx="14"/>
                    </g>
                    <rect x="185" y="59" width="66" height="84" rx="11" fill="#242066" stroke="#8d7cff"/>
                    <path d="m208 86-12 15 12 15m20-30 12 15-12 15m-6-34-8 38" stroke="#c9bcff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <g fill="#9785ff"><circle cx="100" cy="35" r="3"/><circle cx="60" cy="140" r="3"/><circle cx="335" cy="20" r="3"/><circle cx="345" cy="155" r="3"/><circle cx="320" cy="90" r="3"/><circle cx="110" cy="90" r="3"/></g>
                </svg>
            </div>

            <div class="section-heading">

                <span class="section-label">
                    Технологии
                </span>

                <h2 id="stack-title">
                    Наш <span>технологический стек</span>
                </h2>

                <p>
                    Используем современные инструменты для создания
                    быстрых, удобных и масштабируемых проектов.
                </p>

            </div>


            <div class="technology-groups">


                <div class="technology-group">

                    <div class="studio-stack__intro">
                        <span class="studio-stack__icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><rect x="3" y="5" width="26" height="18" rx="2"/><path d="M11 28h10M16 23v5M7 19h18"/></svg></span>
                        <div><h3>Frontend</h3><p>Современный и интерактивный интерфейс</p></div>
                    </div>

                    <div class="technology-list">

                        <span class="technology-item">HTML5</span>

                        <span class="technology-item">CSS3</span>

                        <span class="technology-item">JavaScript</span>

                        <span class="technology-item">React</span>

                        <span class="technology-item">TypeScript</span>

                    </div>

                </div>


                <div class="technology-group">

                    <div class="studio-stack__intro">
                        <span class="studio-stack__icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><rect x="4" y="3" width="24" height="7" rx="2"/><rect x="4" y="13" width="24" height="7" rx="2"/><rect x="4" y="23" width="24" height="7" rx="2"/><path d="M8 6.5h2m3 0h1M8 16.5h2m3 0h1M8 26.5h2m3 0h1"/></svg></span>
                        <div><h3>Backend</h3><p>Надёжная серверная часть и работа с данными</p></div>
                    </div>

                    <div class="technology-list">

                        <span class="technology-item">PHP</span>

                        <span class="technology-item">Laravel</span>

                        <span class="technology-item">Python</span>

                        <span class="technology-item">Node.js</span>

                        <span class="technology-item">MySQL</span>

                    </div>

                </div>


                <div class="technology-group">

                    <div class="studio-stack__intro">
                        <span class="studio-stack__icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M16 7c-1-6-9-4-9 1-5 0-6 7-3 9-3 5 1 10 5 9 2 5 7 3 7-1V7Zm0 0c1-6 9-4 9 1 5 0 6 7 3 9 3 5-1 10-5 9-2 5-7 3-7-1M7 8l3 3m-6 6h5m0 9v-5m16-14-3 4m6 5h-5m0 10v-5"/></svg></span>
                        <div><h3>AI и интеграции</h3><p>Искусственный интеллект и внешние сервисы</p></div>
                    </div>

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

        <div class="planet-panels">
        <div class="planet-panels__backdrop" aria-hidden="true"><canvas class="planet-panels__orb"></canvas></div>
        <section class="cms-section planet-panel">

            <div class="section-heading">

                <span class="section-label">
                    CMS и платформы
                </span>

                <h2>
                    Работаем с <span>популярными CMS</span>
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

        <section class="projects-section planet-panel"
            id="projects">
            <div class="section-heading">

                <span class="section-label">
                    Портфолио
                </span>

                <h2>
                    Наши <span>проекты</span>
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
        </div>
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

                    <div class="form-consent">
                        <input type="checkbox" id="home_personal_data_consent" name="personal_data_consent" value="1" required>
                        <label for="home_personal_data_consent">Я согласен на обработку персональных данных и ознакомлен с <a href="privacy.php" target="_blank">политикой конфиденциальности</a>.</label>
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

    <?php renderFooter(); ?>
<script src="cursor-stars.js?v=left-star-colors-3"></script>
<script src="planet-panels.js?v=6"></script>
<script src="sphere-code-loop.js?v=canvas-render-2"></script>
</body>

</html>



