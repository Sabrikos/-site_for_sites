-- @if-missing-column chat_conversations personal_data_expires_at
ALTER TABLE chat_conversations ADD COLUMN personal_data_expires_at DATETIME NULL AFTER updated_at;

-- @if-missing-column chat_conversations personal_data_erased_at
ALTER TABLE chat_conversations ADD COLUMN personal_data_erased_at DATETIME NULL AFTER personal_data_expires_at;

-- @if-missing-index chat_conversations idx_chat_personal_data_expiry
ALTER TABLE chat_conversations ADD INDEX idx_chat_personal_data_expiry (personal_data_expires_at, personal_data_erased_at);

-- @if-missing-column telegram_profiles personal_data_expires_at
ALTER TABLE telegram_profiles ADD COLUMN personal_data_expires_at DATETIME NULL AFTER last_activity;

-- @if-missing-index telegram_profiles idx_telegram_profiles_expiry
ALTER TABLE telegram_profiles ADD INDEX idx_telegram_profiles_expiry (personal_data_expires_at);

UPDATE chat_conversations
SET personal_data_expires_at = DATE_ADD(updated_at, INTERVAL 1 YEAR)
WHERE personal_data_expires_at IS NULL;

UPDATE telegram_profiles
SET personal_data_expires_at = DATE_ADD(last_activity, INTERVAL 1 YEAR)
WHERE personal_data_expires_at IS NULL;
