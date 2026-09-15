CREATE TABLE IF NOT EXISTS chat_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_key VARCHAR(128) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'bot',
    customer_name VARCHAR(150) NULL,
    customer_contact VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chat_session_key (session_key), INDEX idx_chat_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, conversation_id BIGINT UNSIGNED NOT NULL,
    sender VARCHAR(20) NOT NULL, message TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_messages_conversation (conversation_id, id),
    CONSTRAINT fk_chat_messages_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_assistant_state (
    conversation_id BIGINT UNSIGNED PRIMARY KEY, handoff_offered TINYINT(1) NOT NULL DEFAULT 0,
    generation_key CHAR(32) NULL, generation_started_at DATETIME NULL,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS chat_admin_presence (
    conversation_id BIGINT UNSIGNED NOT NULL, admin_id INT UNSIGNED NOT NULL, last_activity DATETIME NOT NULL,
    typing_until DATETIME NULL, PRIMARY KEY (conversation_id, admin_id),
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS faq (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, question VARCHAR(255) NOT NULL, answer TEXT NOT NULL,
    keywords VARCHAR(255) NOT NULL DEFAULT '', active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS assistant_limits (
    bucket CHAR(64) PRIMARY KEY, hits INT UNSIGNED NOT NULL DEFAULT 0, expires_at DATETIME NOT NULL,
    INDEX idx_assistant_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS telegram_sessions (
    chat_id BIGINT NOT NULL PRIMARY KEY, state VARCHAR(60) NULL, selected_tariff_id INT UNSIGNED NULL,
    draft_data JSON NULL, human_mode TINYINT(1) NOT NULL DEFAULT 0, conversation_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS telegram_profiles (
    chat_id BIGINT NOT NULL PRIMARY KEY, user_id BIGINT NOT NULL, username VARCHAR(255) NULL,
    first_name VARCHAR(255) NULL, last_name VARCHAR(255) NULL, language_code VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS telegram_assignments (
    conversation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, admin_user_id BIGINT NOT NULL,
    admin_name VARCHAR(255) NOT NULL, channel VARCHAR(20) NOT NULL DEFAULT 'telegram', connected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS telegram_processed_updates (
    update_id BIGINT NOT NULL PRIMARY KEY, processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_telegram_processed_updates_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS telegram_relays (
    admin_message_id BIGINT NOT NULL PRIMARY KEY, client_chat_id BIGINT NULL, admin_chat_id BIGINT NOT NULL,
    conversation_id BIGINT UNSIGNED NULL, channel VARCHAR(20) NOT NULL DEFAULT 'telegram', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
