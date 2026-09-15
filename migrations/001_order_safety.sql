-- @if-missing-column orders source
ALTER TABLE orders ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'website_cart' AFTER updated_at;

-- @if-missing-column orders request_token
ALTER TABLE orders ADD COLUMN request_token CHAR(64) NULL AFTER source;

-- @if-missing-index orders uq_orders_request_token
CREATE UNIQUE INDEX uq_orders_request_token ON orders (request_token);

CREATE TABLE IF NOT EXISTS app_rate_limits (
    bucket CHAR(64) PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    INDEX idx_app_rate_limits_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
