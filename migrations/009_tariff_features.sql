CREATE TABLE IF NOT EXISTS tariff_features (
    tariff_id INT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL,
    feature VARCHAR(500) NOT NULL,
    PRIMARY KEY (tariff_id, position),
    FOREIGN KEY (tariff_id) REFERENCES tariffs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT t.id, 10, CASE s.slug
 WHEN 'landing' THEN 'Одностраничный сайт с информацией о предложении, преимуществах и контактах'
 WHEN 'shop' THEN 'Каталог товаров и корзина'
 WHEN 'revision' THEN 'Работы в согласованном объёме'
 WHEN 'ai' THEN 'Настройка выбранного решения под согласованную задачу'
 ELSE 'Состав работ определим после обсуждения задачи' END
FROM tariffs t JOIN services s ON s.id=t.service_id;

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT t.id, 20, CASE s.slug
 WHEN 'landing' THEN 'Адаптация для телефона, планшета и компьютера'
 WHEN 'shop' THEN 'Состав и настройки магазина согласуются перед работой'
 ELSE 'Две правки после реализации' END
FROM tariffs t JOIN services s ON s.id=t.service_id;

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT t.id, 30, 'Две правки после реализации' FROM tariffs t JOIN services s ON s.id=t.service_id WHERE s.slug IN ('landing','shop');

INSERT IGNORE INTO tariff_features (tariff_id, position, feature)
SELECT id, 40, 'Обучение работе с панелью управления, если она предусмотрена проектом' FROM tariffs;
