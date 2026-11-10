<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/ConsentService.php';
require_once __DIR__ . '/RetentionPolicy.php';

final class OrderService
{
    public const MAX_ITEMS = 12;
    public const LEAD_TOPICS = ['automation' => 'Автоматизация бизнеса'];

    public function __construct(private PDO $pdo) {}

    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    public static function validateCustomer(array $input): array
    {
        $name = trim((string) ($input['customer_name'] ?? ''));
        $phone = trim((string) ($input['customer_phone'] ?? ''));
        $comment = trim((string) ($input['project_comment'] ?? ''));
        $errors = [];

        if (self::length($name) < 2 || self::length($name) > 150) {
            $errors[] = 'Введите имя от 2 до 150 символов.';
        }
        if (!preg_match('/^[0-9+()\-\s]{7,30}$/u', $phone)) {
            $errors[] = 'Введите корректный номер телефона.';
        }
        if (self::length($comment) > 1000) {
            $errors[] = 'Комментарий не должен быть длиннее 1000 символов.';
        }

        return [$errors, compact('name', 'phone', 'comment')];
    }

    public static function validRequestToken(?string $token): bool
    {
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    public function createCartOrder(array $customer, array $tariffIds, string $requestToken, array $consent): array
    {
        $tariffIds = array_values(array_unique(array_map('intval', $tariffIds)));
        if ($tariffIds === [] || count($tariffIds) > self::MAX_ITEMS) {
            throw new DomainException('Корзина содержит недопустимое количество тарифов.', 422);
        }

        sort($tariffIds);
        $this->pdo->beginTransaction();
        try {
        $placeholders = implode(',', array_fill(0, count($tariffIds), '?'));
        $statement = $this->pdo->prepare("SELECT t.id, t.name AS tariff_name, s.name AS service_name
            FROM tariffs t JOIN services s ON s.id = t.service_id
            WHERE t.active = 1 AND s.active = 1 AND t.id IN ($placeholders) ORDER BY t.id FOR UPDATE");
        $statement->execute($tariffIds);
        $tariffs = $statement->fetchAll();
        $byId = [];
        foreach ($tariffs as $tariff) {
            $byId[(int) $tariff['id']] = $tariff;
        }
        if (count($byId) !== count($tariffIds)) {
            throw new DomainException('Один из выбранных тарифов больше недоступен. Обновите страницу тарифов.', 422);
        }

        $items = [];
        foreach ($tariffIds as $id) {
            $tariff = $byId[$id];
            $items[] = [
                'tariff_id' => $id,
                'service_name' => $tariff['service_name'],
                'tariff_name' => $tariff['tariff_name'],
                'price' => null,
            ];
        }

        return $this->persist($customer, $items, $requestToken, 'website_cart', $consent);
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createLead(array $customer, int|string|null $serviceId, string $requestToken, array $consent): array
    {
        if ($serviceId === null) {
            $serviceName = 'Без выбранной услуги';
        } elseif (is_string($serviceId) && isset(self::LEAD_TOPICS[$serviceId])) {
            $serviceName = self::LEAD_TOPICS[$serviceId];
        } elseif (is_int($serviceId) && $serviceId > 0) {
            $statement = $this->pdo->prepare('SELECT name FROM services WHERE id = ? AND active = 1');
            $statement->execute([$serviceId]);
            $service = $statement->fetch();
            if (!$service) {
                throw new DomainException('Выбранная услуга больше недоступна. Обновите страницу.', 422);
            }
            $serviceName = (string) $service['name'];
        } else {
            throw new DomainException('Выберите услугу из списка.', 422);
        }

        return $this->persist($customer, [[
            'tariff_id' => null,
            'service_name' => $serviceName,
            'tariff_name' => 'Заявка без выбранного тарифа',
            'price' => null,
        ]], $requestToken, 'website_lead', $consent);
    }

    private function persist(array $customer, array $items, string $requestToken, string $source, array $consent): array
    {
        $total = null;
        if (!$this->pdo->inTransaction()) $this->pdo->beginTransaction();
        try {
            $existing = $this->pdo->prepare('SELECT id, total FROM orders WHERE request_token = ? LIMIT 1');
            $existing->execute([$requestToken]);
            if ($order = $existing->fetch()) {
                $this->pdo->commit();
                return ['id' => (int) $order['id'], 'created' => false, 'total' => $order['total'] === null ? null : (int) $order['total']];
            }

            $order = $this->pdo->prepare('INSERT INTO orders
                (customer_name, phone, project_comment, total, status, source, request_token, personal_data_expires_at, last_contact_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $order->execute([$customer['name'], $customer['phone'],
                $customer['comment'] !== '' ? $customer['comment'] : null, $total, 'new', $source, $requestToken,
                RetentionPolicy::addMonths($consent['accepted_at'], RetentionPolicy::LEAD_MONTHS), $consent['accepted_at']]);
            $orderId = (int) $this->pdo->lastInsertId();
            $item = $this->pdo->prepare('INSERT INTO order_items
                (order_id, tariff_id, service_name, tariff_name, price) VALUES (?, ?, ?, ?, ?)');
            foreach ($items as $entry) {
                $item->execute([$orderId, $entry['tariff_id'], $entry['service_name'], $entry['tariff_name'], $entry['price']]);
            }
            ConsentService::record($this->pdo, $orderId, $consent);
            $this->pdo->commit();
            return ['id' => $orderId, 'created' => true, 'total' => $total];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($error instanceof PDOException && (string) $error->getCode() === '23000') {
                $existing = $this->pdo->prepare('SELECT id, total FROM orders WHERE request_token = ? LIMIT 1');
                $existing->execute([$requestToken]);
                if ($order = $existing->fetch()) {
                    return ['id' => (int) $order['id'], 'created' => false, 'total' => $order['total'] === null ? null : (int) $order['total']];
                }
            }
            throw $error;
        }
    }
}
