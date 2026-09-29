UPDATE tariffs t
JOIN services s ON s.id = t.service_id
SET t.price = 5000
WHERE s.slug = 'other' AND t.name = 'Индивидуальный проект';
