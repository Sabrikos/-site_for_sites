<?php
declare(strict_types=1);

final class ServiceCatalog
{
    public function __construct(private PDO $pdo) {}

    public function categories(): array
    {
        return $this->pdo->query("SELECT s.id, s.slug, s.name, s.description, s.deadline, s.sort_order, s.icon_key, MIN(t.price) AS price
            FROM services s JOIN tariffs t ON t.service_id = s.id AND t.active = 1 AND t.item_type = 'service'
            WHERE s.active = 1 GROUP BY s.id, s.slug, s.name, s.description, s.deadline, s.sort_order, s.icon_key ORDER BY s.sort_order, s.id")->fetchAll();
    }

    public function tariffs(): array
    {
        return $this->pdo->query("SELECT t.id, t.service_id, t.name, t.description, t.price, t.price_unit, t.item_type, t.sort_order, s.name AS service_name, s.slug AS service_slug
            FROM tariffs t JOIN services s ON s.id = t.service_id
            WHERE t.active = 1 AND s.active = 1 ORDER BY s.sort_order, s.id, t.sort_order, t.id")->fetchAll();
    }

    public function features(): array
    {
        $rows = $this->pdo->query("SELECT f.tariff_id, f.feature FROM tariff_features f JOIN tariffs t ON t.id=f.tariff_id JOIN services s ON s.id=t.service_id WHERE t.active=1 AND s.active=1 ORDER BY f.tariff_id, f.position")->fetchAll();
        $features = [];
        foreach ($rows as $row) $features[(int) $row['tariff_id']][] = $row['feature'];
        return $features;
    }

    public function browserTariffs(): array
    {
        return array_map(static fn($row) => ['tariff_id' => (string) $row['id'], 'service' => $row['service_name'], 'tariff' => $row['name'], 'price' => (int) $row['price']], $this->tariffs());
    }
}
