-- @if-missing-column orders last_contact_at
ALTER TABLE orders ADD COLUMN last_contact_at DATETIME NULL;

-- @if-missing-column orders completed_at
ALTER TABLE orders ADD COLUMN completed_at DATETIME NULL;

-- @if-missing-column orders closed_at
ALTER TABLE orders ADD COLUMN closed_at DATETIME NULL;

-- @if-missing-column chat_conversations last_contact_at
ALTER TABLE chat_conversations ADD COLUMN last_contact_at DATETIME NULL;

-- @if-missing-column chat_conversations linked_order_id
ALTER TABLE chat_conversations ADD COLUMN linked_order_id BIGINT UNSIGNED NULL;

-- @if-missing-column chat_conversations closed_at
ALTER TABLE chat_conversations ADD COLUMN closed_at DATETIME NULL;

-- @if-missing-index chat_conversations idx_chat_linked_order
ALTER TABLE chat_conversations ADD INDEX idx_chat_linked_order (linked_order_id);

-- Backfill only once. Historical user menu clicks cannot be distinguished from text,
-- so the last user/admin message is used conservatively. Bot updates are excluded.
UPDATE orders SET last_contact_at = created_at,
    closed_at = CASE WHEN status = 'closed' THEN updated_at ELSE closed_at END,
    personal_data_expires_at = CASE WHEN status IN ('new', 'discussion') THEN DATE_ADD(created_at, INTERVAL 6 MONTH)
        WHEN status = 'closed' THEN LEAST(DATE_ADD(created_at, INTERVAL 6 MONTH), DATE_ADD(updated_at, INTERVAL 30 DAY)) ELSE NULL END
WHERE last_contact_at IS NULL;

UPDATE chat_conversations c LEFT JOIN (
    SELECT conversation_id, MAX(created_at) AS contact_at FROM chat_messages WHERE sender IN ('user', 'admin') GROUP BY conversation_id
) m ON m.conversation_id = c.id
SET c.last_contact_at = COALESCE(m.contact_at, c.created_at),
    c.closed_at = CASE WHEN c.status = 'closed' THEN c.updated_at ELSE NULL END,
    c.personal_data_expires_at = CASE WHEN c.status = 'closed'
        THEN LEAST(DATE_ADD(COALESCE(m.contact_at, c.created_at), INTERVAL 3 MONTH), DATE_ADD(c.updated_at, INTERVAL 30 DAY))
        ELSE DATE_ADD(COALESCE(m.contact_at, c.created_at), INTERVAL 3 MONTH) END
WHERE c.last_contact_at IS NULL;
