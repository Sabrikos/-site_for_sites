UPDATE orders
SET personal_data_expires_at = DATE_ADD(created_at, INTERVAL 1 YEAR)
WHERE personal_data_expires_at IS NULL;
