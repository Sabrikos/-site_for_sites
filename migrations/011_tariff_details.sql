INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT t.id, 15, CASE
 WHEN s.slug='landing' AND t.name='Бизнес' THEN 'Дополнительные блоки и формы'
 WHEN s.slug='landing' AND t.name='Премиум' THEN 'Индивидуальный дизайн и расширенная функциональность'
 WHEN s.slug='shop' AND t.name='Бизнес' THEN 'Онлайн-оплата'
 WHEN s.slug='shop' AND t.name='Премиум' THEN 'Дополнительные интеграции'
 WHEN s.slug='revision' AND t.name='Расширенная доработка' THEN 'Добавление страниц, блоков и функций'
 WHEN s.slug='revision' AND t.name='Комплексная доработка' THEN 'Комплексное изменение сайта и его функциональности'
 ELSE NULL END
FROM tariffs t JOIN services s ON s.id=t.service_id
WHERE (s.slug='landing' AND t.name IN ('Бизнес','Премиум'))
 OR (s.slug='shop' AND t.name IN ('Бизнес','Премиум'))
 OR (s.slug='revision' AND t.name IN ('Расширенная доработка','Комплексная доработка'));
