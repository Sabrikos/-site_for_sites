-- Superseded by 002_chat_telegram_schema.sql and intentionally not executed by migrate.php.
CREATE TABLE IF NOT EXISTS chat_assistant_state (
    conversation_id BIGINT UNSIGNED PRIMARY KEY,
    handoff_offered TINYINT(1) NOT NULL DEFAULT 0,
    generation_key CHAR(32) NULL,
    generation_started_at DATETIME NULL,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_admin_presence (
    conversation_id BIGINT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NOT NULL,
    last_activity DATETIME NOT NULL,
    typing_until DATETIME NULL,
    PRIMARY KEY (conversation_id, admin_id),
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faq (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    keywords VARCHAR(255) NOT NULL DEFAULT '',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assistant_limits (
    bucket CHAR(64) PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    INDEX idx_assistant_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
