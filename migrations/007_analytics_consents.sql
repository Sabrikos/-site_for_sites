CREATE TABLE IF NOT EXISTS analytics_consent_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    browser_hash CHAR(64) NOT NULL,
    request_id CHAR(36) NOT NULL,
    decision ENUM('accepted', 'rejected', 'withdrawn') NOT NULL,
    consent_version VARCHAR(50) NOT NULL,
    consent_text TEXT NOT NULL,
    privacy_policy_version VARCHAR(50) NOT NULL,
    counter_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY analytics_request (browser_hash, request_id),
    KEY analytics_browser (browser_hash, id),
    KEY analytics_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
