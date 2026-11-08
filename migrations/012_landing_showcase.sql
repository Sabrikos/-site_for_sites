UPDATE tariff_features f JOIN tariffs t ON t.id=f.tariff_id JOIN services s ON s.id=t.service_id
SET f.feature='Уникальная структура и дизайн под ваш проект'
WHERE s.slug='landing' AND t.name='Старт' AND f.position=10
  AND f.feature='Одностраничный сайт с информацией о предложении, преимуществах и контактах';

INSERT IGNORE INTO tariff_features (tariff_id,position,feature)
SELECT t.id,25,'Необходимые блоки: о компании, преимущества, услуги, кейсы и контакты' FROM tariffs t JOIN services s ON s.id=t.service_id WHERE s.slug='landing' AND t.name='Старт';

INSERT IGNORE INTO tariff_features (tariff_id,position,feature)
SELECT t.id,27,'Формы заявок и подключение способов связи' FROM tariffs t JOIN services s ON s.id=t.service_id WHERE s.slug='landing' AND t.name='Старт';

INSERT IGNORE INTO tariff_features (tariff_id,position,feature)
SELECT t.id,35,'Базовая SEO-оптимизация: структура и метатеги' FROM tariffs t JOIN services s ON s.id=t.service_id WHERE s.slug='landing' AND t.name='Старт';
