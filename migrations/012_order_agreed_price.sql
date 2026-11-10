-- Preserve historical amounts. New applications may have no agreed price.
ALTER TABLE orders MODIFY COLUMN total INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE order_items MODIFY COLUMN price INT UNSIGNED NULL DEFAULT NULL;

-- @if-missing-column orders price_agreed_at
ALTER TABLE orders ADD COLUMN price_agreed_at DATETIME NULL DEFAULT NULL;
