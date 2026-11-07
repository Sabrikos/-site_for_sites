-- @if-missing-column analytics_consent_events choice_expires_at
ALTER TABLE analytics_consent_events ADD COLUMN choice_expires_at DATETIME NULL;

-- @if-missing-column analytics_consent_events retention_until
ALTER TABLE analytics_consent_events ADD COLUMN retention_until DATETIME NULL;

UPDATE analytics_consent_events AS event
LEFT JOIN (
    SELECT previous.id, MIN(following.created_at) AS next_at
    FROM analytics_consent_events AS previous
    LEFT JOIN analytics_consent_events AS following
      ON following.browser_hash = previous.browser_hash AND following.id > previous.id
    GROUP BY previous.id
) AS history ON history.id = event.id
SET event.choice_expires_at = CASE
        WHEN event.decision = 'accepted' AND history.next_at IS NOT NULL
          THEN LEAST(DATE_ADD(event.created_at, INTERVAL 6 MONTH), history.next_at)
        ELSE DATE_ADD(event.created_at, INTERVAL 6 MONTH)
    END,
    event.retention_until = CASE
        WHEN event.decision = 'accepted' THEN DATE_ADD(
            LEAST(DATE_ADD(event.created_at, INTERVAL 6 MONTH), COALESCE(history.next_at, DATE_ADD(event.created_at, INTERVAL 6 MONTH))),
            INTERVAL 12 MONTH)
        ELSE DATE_ADD(event.created_at, INTERVAL 12 MONTH)
    END
WHERE event.choice_expires_at IS NULL OR event.retention_until IS NULL;

-- @if-missing-index analytics_consent_events analytics_retention
ALTER TABLE analytics_consent_events ADD INDEX analytics_retention (retention_until, id);
