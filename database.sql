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

    email VARCHAR(255) NOT NULL,

    project_comment TEXT NULL,

    total INT UNSIGNED NOT NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'new',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_orders_status (status),

    INDEX idx_orders_created_at (created_at)

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

    price INT UNSIGNED NOT NULL,

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
    10000
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

    INDEX idx_chat_session_key (session_key),

    INDEX idx_chat_status (status)

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

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;