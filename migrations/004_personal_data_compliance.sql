-- @if-missing-column orders personal_data_expires_at
ALTER TABLE orders ADD COLUMN personal_data_expires_at DATETIME NULL AFTER request_token;

-- @if-missing-column orders personal_data_erased_at
ALTER TABLE orders ADD COLUMN personal_data_erased_at DATETIME NULL AFTER personal_data_expires_at;

-- @if-missing-index orders idx_orders_personal_data_expiry
ALTER TABLE orders ADD INDEX idx_orders_personal_data_expiry (personal_data_expires_at, personal_data_erased_at);

CREATE TABLE IF NOT EXISTS personal_data_consents (
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
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @if-missing-column telegram_sessions consent_accepted_at
ALTER TABLE telegram_sessions ADD COLUMN consent_accepted_at DATETIME NULL AFTER conversation_id;

-- @if-missing-column telegram_sessions consent_version
ALTER TABLE telegram_sessions ADD COLUMN consent_version VARCHAR(40) NULL AFTER consent_accepted_at;

-- @if-present-column orders email
ALTER TABLE orders DROP COLUMN email;
