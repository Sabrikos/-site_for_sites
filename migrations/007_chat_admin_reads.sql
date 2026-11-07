CREATE TABLE IF NOT EXISTS chat_admin_reads (
    conversation_id BIGINT UNSIGNED NOT NULL,
    admin_id BIGINT UNSIGNED NOT NULL,
    last_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (conversation_id, admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
