-- Only the existing base AI tariff changes. Historical order_items are untouched.
UPDATE tariffs t
JOIN services s ON s.id = t.service_id
SET t.price = 5000
WHERE s.slug = 'ai' AND t.name = 'Telegram-бот' AND t.price = 10000;
