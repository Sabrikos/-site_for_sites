-- @if-missing-column personal_data_consents consent_expires_at
ALTER TABLE personal_data_consents ADD COLUMN consent_expires_at DATETIME NULL;

-- @if-missing-column personal_data_consents consent_ended_at
ALTER TABLE personal_data_consents ADD COLUMN consent_ended_at DATETIME NULL;

-- @if-missing-column personal_data_consents subject_hash
ALTER TABLE personal_data_consents ADD COLUMN subject_hash CHAR(64) NULL;

-- Preserve old v1 permission limits before changing evidence retention.
UPDATE personal_data_consents p JOIN orders o ON o.id = p.order_id
SET p.consent_expires_at = CASE WHEN p.consent_version = 'project-request-v1' THEN p.retention_until
    ELSE DATE_ADD(GREATEST(p.accepted_at, COALESCE(o.last_contact_at, o.created_at)), INTERVAL 6 MONTH) END,
    p.subject_hash = CASE WHEN o.phone <> '' THEN SHA2(CONCAT(p.evidence_hash, '|', REGEXP_REPLACE(o.phone, '[^0-9]', '')), 256) ELSE NULL END
WHERE p.purpose_code = 'project_request' AND p.consent_expires_at IS NULL;

UPDATE personal_data_consents p JOIN orders o ON o.id = p.order_id
SET p.consent_ended_at = LEAST(p.consent_expires_at,
    COALESCE(p.withdrawn_at, '9999-12-31 23:59:59'),
    COALESCE(o.personal_data_erased_at, '9999-12-31 23:59:59'),
    CASE WHEN o.status = 'closed' THEN COALESCE(o.closed_at, UTC_TIMESTAMP()) ELSE '9999-12-31 23:59:59' END,
    CASE WHEN o.status NOT IN ('new', 'discussion', 'closed') THEN COALESCE(o.completed_at, UTC_TIMESTAMP()) ELSE '9999-12-31 23:59:59' END)
WHERE p.purpose_code = 'project_request' AND p.consent_ended_at IS NULL
AND (p.consent_expires_at <= UTC_TIMESTAMP() OR p.withdrawn_at IS NOT NULL
    OR o.personal_data_erased_at IS NOT NULL OR o.status NOT IN ('new', 'discussion'));

UPDATE personal_data_consents SET retention_until = DATE_ADD(COALESCE(consent_ended_at, consent_expires_at), INTERVAL 3 YEAR)
WHERE purpose_code = 'project_request' AND consent_expires_at IS NOT NULL;
