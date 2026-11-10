CREATE DATABASE IF NOT EXISTS webstart
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE webstart;


/* ==============================================
   УСЛУГИ
============================================== */

CREATE TABLE services (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    slug VARCHAR(100) NOT NULL UNIQUE,

    sort_order INT NOT NULL DEFAULT 0,

    icon_key VARCHAR(100) NULL,

    description TEXT NULL,

    deadline VARCHAR(100) NULL,

    active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;


/* ==============================================
   ТАРИФЫ
============================================== */

CREATE TABLE tariffs (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    service_id INT UNSIGNED NOT NULL,

    name VARCHAR(100) NOT NULL,

    description TEXT NULL,

    price INT UNSIGNED NOT NULL,

    sort_order INT NOT NULL DEFAULT 0,

    price_unit VARCHAR(30) NOT NULL DEFAULT '₽',

    item_type VARCHAR(20) NOT NULL DEFAULT 'service',

    active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_tariffs_service

        FOREIGN KEY (service_id)

        REFERENCES services(id)

        ON DELETE CASCADE

) ENGINE=InnoDB;


/* ==============================================
   ЗАКАЗЫ
============================================== */

CREATE TABLE orders (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    customer_name VARCHAR(150) NOT NULL,

    phone VARCHAR(30) NOT NULL,

    project_comment TEXT NULL,

    total INT UNSIGNED NULL DEFAULT NULL,

    price_agreed_at DATETIME NULL DEFAULT NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'new',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    source VARCHAR(20) NOT NULL DEFAULT 'website_cart',

    request_token CHAR(64) NULL,

    personal_data_expires_at DATETIME NULL,

    personal_data_erased_at DATETIME NULL,

    INDEX idx_orders_status (status),

    INDEX idx_orders_created_at (created_at),

    UNIQUE KEY uq_orders_request_token (request_token),

    INDEX idx_orders_personal_data_expiry (personal_data_expires_at, personal_data_erased_at)

) ENGINE=InnoDB;


/* ==============================================
   СОГЛАСИЯ НА ОБРАБОТКУ ПЕРСОНАЛЬНЫХ ДАННЫХ
============================================== */

CREATE TABLE personal_data_consents (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id BIGINT UNSIGNED NOT NULL,

    purpose_code VARCHAR(64) NOT NULL,

    consent_version VARCHAR(40) NOT NULL,

    privacy_policy_version VARCHAR(40) NOT NULL,

    consent_text TEXT NOT NULL,

    source VARCHAR(30) NOT NULL,

    accepted_at DATETIME NOT NULL,

    evidence_hash CHAR(64) NOT NULL,

    retention_until DATETIME NOT NULL,

    withdrawn_at DATETIME NULL,

    withdrawal_channel VARCHAR(30) NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_personal_data_consents_order_purpose (order_id, purpose_code),

    INDEX idx_personal_data_consents_retention (retention_until, withdrawn_at),

    CONSTRAINT fk_personal_data_consents_order

        FOREIGN KEY (order_id)

        REFERENCES orders(id)

        ON DELETE CASCADE

) ENGINE=InnoDB;


/* ==============================================
   ПОЗИЦИИ ЗАКАЗА
============================================== */

CREATE TABLE order_items (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    order_id BIGINT UNSIGNED NOT NULL,

    tariff_id INT UNSIGNED NULL,

    service_name VARCHAR(100) NOT NULL,

    tariff_name VARCHAR(100) NOT NULL,

    price INT UNSIGNED NULL DEFAULT NULL,

    CONSTRAINT fk_order_items_order

        FOREIGN KEY (order_id)

        REFERENCES orders(id)

        ON DELETE CASCADE,

    CONSTRAINT fk_order_items_tariff

        FOREIGN KEY (tariff_id)

        REFERENCES tariffs(id)

        ON DELETE SET NULL

) ENGINE=InnoDB;


/* ==============================================
   АДМИНИСТРАТОРЫ
============================================== */

CREATE TABLE admins (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(100) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB;



/* ==============================================
   НАЧАЛЬНЫЕ УСЛУГИ
============================================== */

INSERT INTO services
(name, slug, description, deadline)
VALUES

(
    'Лендинг',
    'landing',
    'Одностраничный сайт для продвижения товара, услуги или компании.',
    '5–7 дней'
),

(
    'Интернет-магазин',
    'shop',
    'Интернет-магазин с каталогом, корзиной и оформлением заказов.',
    '10–14 дней'
),

(
    'Доработка сайта',
    'revision',
    'Исправление ошибок и добавление новой функциональности.',
    'от 1 дня'
),

(
    'ИИ-решения',
    'ai',
    'Telegram-боты, AI-ассистенты и интеграции искусственного интеллекта.',
    'от 5 дней'
),

(
    'Другое',
    'other',
    'Индивидуальные задачи разработки.',
    'по договорённости'
);



/* ==============================================
   ТАРИФЫ ЛЕНДИНГА
============================================== */

INSERT INTO tariffs
(service_id, name, description, price)
VALUES

(
    (SELECT id FROM services WHERE slug = 'landing'),
    'Старт',
    'Простой лендинг для небольшой услуги.',
    15000
),

(
    (SELECT id FROM services WHERE slug = 'landing'),
    'Бизнес',
    'Расширенный лендинг с дополнительными блоками и формами.',
    22000
),

(
    (SELECT id FROM services WHERE slug = 'landing'),
    'Премиум',
    'Индивидуальный дизайн и расширенная функциональность.',
    30000
);



/* ==============================================
   ИНТЕРНЕТ-МАГАЗИН
============================================== */

INSERT INTO tariffs
(service_id, name, description, price)
VALUES

(
    (SELECT id FROM services WHERE slug = 'shop'),
    'Старт',
    'Небольшой каталог товаров и корзина.',
    30000
),

(
    (SELECT id FROM services WHERE slug = 'shop'),
    'Бизнес',
    'Каталог, корзина и онлайн-оплата.',
    45000
),

(
    (SELECT id FROM services WHERE slug = 'shop'),
    'Премиум',
    'Большой интернет-магазин с дополнительными интеграциями.',
    65000
);



/* ==============================================
   ДОРАБОТКА
============================================== */

INSERT INTO tariffs
(service_id, name, description, price)
VALUES

(
    (SELECT id FROM services WHERE slug = 'revision'),
    'Мелкая доработка',
    'Изменение отдельных элементов сайта.',
    5000
),

(
    (SELECT id FROM services WHERE slug = 'revision'),
    'Расширенная доработка',
    'Добавление страниц, блоков и функций.',
    10000
),

(
    (SELECT id FROM services WHERE slug = 'revision'),
    'Комплексная доработка',
    'Большое изменение сайта и его функциональности.',
    20000
);



/* ==============================================
   AI
============================================== */

INSERT INTO tariffs
(service_id, name, description, price)
VALUES

(
    (SELECT id FROM services WHERE slug = 'ai'),
    'Telegram-бот',
    'Бот для заявок, уведомлений и автоматизации.',
    10000
),

(
    (SELECT id FROM services WHERE slug = 'ai'),
    'AI-ассистент',
    'AI-помощник для общения с клиентами.',
    20000
),

(
    (SELECT id FROM services WHERE slug = 'ai'),
    'AI для бизнеса',
    'Комплексная интеграция искусственного интеллекта.',
    30000
);



INSERT INTO tariffs
(service_id, name, description, price)
VALUES

(
    (SELECT id FROM services WHERE slug = 'other'),
    'Индивидуальный проект',
    'Стоимость рассчитывается после обсуждения.',
    5000
);

/* ==============================================
   ЧАТЫ
============================================== */

CREATE TABLE chat_conversations (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    session_key VARCHAR(128) NOT NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'bot',

    customer_name VARCHAR(150) NULL,

    customer_contact VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    personal_data_expires_at DATETIME NULL,

    personal_data_erased_at DATETIME NULL,

    INDEX idx_chat_session_key (session_key),

    INDEX idx_chat_status (status),

    INDEX idx_chat_personal_data_expiry (personal_data_expires_at, personal_data_erased_at)

) ENGINE=InnoDB;


CREATE TABLE chat_messages (

    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    conversation_id BIGINT UNSIGNED NOT NULL,

    sender VARCHAR(20) NOT NULL,

    message TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_chat_messages_conversation (conversation_id, id),

    CONSTRAINT fk_chat_messages_conversation

        FOREIGN KEY (conversation_id)

        REFERENCES chat_conversations(id)

        ON DELETE CASCADE

) ENGINE=InnoDB;
/* ==============================================
   TELEGRAM SESSIONS
============================================== */

CREATE TABLE telegram_sessions (

    chat_id BIGINT NOT NULL PRIMARY KEY,

    state VARCHAR(60) NULL,

    selected_tariff_id INT UNSIGNED NULL,

    draft_data JSON NULL,

    human_mode TINYINT(1) NOT NULL DEFAULT 0,

    conversation_id BIGINT UNSIGNED NULL,

    consent_accepted_at DATETIME NULL,

    consent_version VARCHAR(40) NULL,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS tariff_features (
    tariff_id INT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL,
    feature VARCHAR(500) NOT NULL,
    PRIMARY KEY (tariff_id, position),
    FOREIGN KEY (tariff_id) REFERENCES tariffs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT t.id, 10, CASE s.slug
 WHEN 'landing' THEN 'Одностраничный сайт с информацией о предложении, преимуществах и контактах'
 WHEN 'shop' THEN 'Каталог товаров и корзина'
 WHEN 'revision' THEN 'Работы в согласованном объёме'
 WHEN 'ai' THEN 'Настройка выбранного решения под согласованную задачу'
 ELSE 'Состав работ определим после обсуждения задачи' END
FROM tariffs t JOIN services s ON s.id=t.service_id;

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT t.id, 20, CASE s.slug
 WHEN 'landing' THEN 'Адаптация для телефона, планшета и компьютера'
 WHEN 'shop' THEN 'Состав и настройки магазина согласуются перед работой'
 ELSE 'Две правки после реализации' END
FROM tariffs t JOIN services s ON s.id=t.service_id;

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT t.id, 30, 'Две правки после реализации' FROM tariffs t JOIN services s ON s.id=t.service_id WHERE s.slug IN ('landing','shop');

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT id, 40, 'Обучение работе с панелью управления, если она предусмотрена проектом' FROM tariffs;

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT t.id, 15, CASE
 WHEN s.slug='landing' AND t.name='Бизнес' THEN 'Дополнительные блоки и формы'
 WHEN s.slug='landing' AND t.name='Премиум' THEN 'Индивидуальный дизайн и расширенная функциональность'
 WHEN s.slug='shop' AND t.name='Бизнес' THEN 'Онлайн-оплата'
 WHEN s.slug='shop' AND t.name='Премиум' THEN 'Дополнительные интеграции'
 WHEN s.slug='revision' AND t.name='Расширенная доработка' THEN 'Добавление страниц, блоков и функций'
 WHEN s.slug='revision' AND t.name='Комплексная доработка' THEN 'Комплексное изменение сайта и его функциональности'
 ELSE NULL END
FROM tariffs t JOIN services s ON s.id=t.service_id
WHERE (s.slug='landing' AND t.name IN ('Бизнес','Премиум'))
 OR (s.slug='shop' AND t.name IN ('Бизнес','Премиум'))
 OR (s.slug='revision' AND t.name IN ('Расширенная доработка','Комплексная доработка'));

UPDATE tariff_features f JOIN tariffs t ON t.id=f.tariff_id JOIN services s ON s.id=t.service_id
SET f.feature='Уникальная структура и дизайн под ваш проект'
WHERE s.slug='landing' AND t.name='Старт' AND f.position=10
  AND f.feature='Одностраничный сайт с информацией о предложении, преимуществах и контактах';

INSERT IGNORE INTO tariff_features (tariff_id,position,feature)
SELECT t.id,25,'Необходимые блоки: о компании, преимущества, услуги, кейсы и контакты' FROM tariffs t JOIN services s ON s.id=t.service_id WHERE s.slug='landing' AND t.name='Старт';

INSERT IGNORE INTO tariff_features (tariff_id,position,feature)
SELECT t.id,27,'Формы заявок и подключение способов связи' FROM tariffs t JOIN services s ON s.id=t.service_id WHERE s.slug='landing' AND t.name='Старт';

INSERT IGNORE INTO tariff_features (tariff_id,position,feature)
SELECT t.id,35,'Базовая SEO-оптимизация: структура и метатеги' FROM tariffs t JOIN services s ON s.id=t.service_id WHERE s.slug='landing' AND t.name='Старт';
