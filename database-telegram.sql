-- Выполнить один раз после database.sql.
CREATE TABLE IF NOT EXISTS telegram_sessions (
    chat_id BIGINT NOT NULL PRIMARY KEY,
    state VARCHAR(40) NOT NULL DEFAULT 'idle',
    data JSON NOT NULL,
    human_mode TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS telegram_admin_links (
    admin_chat_id BIGINT NOT NULL PRIMARY KEY,
    client_chat_id BIGINT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'telegram_username');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE orders ADD COLUMN telegram_username VARCHAR(100) NULL AFTER email', 'SELECT 1');
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @column_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'telegram_user_id');
SET @sql := IF(@column_exists = 0, 'ALTER TABLE orders ADD COLUMN telegram_user_id BIGINT NULL AFTER telegram_username', 'SELECT 1');
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;
