<?php

declare(strict_types=1);

require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/OrderService.php';

$tokens = [bin2hex(random_bytes(32)), bin2hex(random_bytes(32))];

try {
    $service = $pdo->query('SELECT id FROM services WHERE active = 1 ORDER BY id LIMIT 1')->fetch();
    $tariff = $pdo->query('SELECT id, price FROM tariffs WHERE active = 1 ORDER BY id LIMIT 1')->fetch();
    if (!$service || !$tariff) {
        throw new RuntimeException('The test requires one active service and tariff.');
    }

    $customer = [
        'name' => 'P0 Integration Test',
        'phone' => '+79990000000',
        'email' => 'p0-test@example.invalid',
        'comment' => 'Temporary integration test record.',
    ];
    $orders = new OrderService($pdo);

    $lead = $orders->createLead($customer, (int) $service['id'], $tokens[0]);
    $leadRepeat = $orders->createLead($customer, (int) $service['id'], $tokens[0]);
    if (!$lead['created'] || $leadRepeat['created'] || $lead['id'] !== $leadRepeat['id'] || $lead['total'] !== 0) {
        throw new RuntimeException('Lead idempotency failed.');
    }

    $cart = $orders->createCartOrder($customer, [(int) $tariff['id']], $tokens[1]);
    $cartRepeat = $orders->createCartOrder($customer, [(int) $tariff['id']], $tokens[1]);
    if (!$cart['created'] || $cartRepeat['created'] || $cart['id'] !== $cartRepeat['id']
        || $cart['total'] !== (int) $tariff['price']) {
        throw new RuntimeException('Cart idempotency or server-side price failed.');
    }

    echo "PASS: lead and cart persistence, idempotency, and database price.\n";
} finally {
    $placeholders = implode(',', array_fill(0, count($tokens), '?'));
    $ids = $pdo->prepare("SELECT id FROM orders WHERE request_token IN ($placeholders)");
    $ids->execute($tokens);
    $orderIds = array_map(static fn(array $row): int => (int) $row['id'], $ids->fetchAll());
    if ($orderIds !== []) {
        $orderPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
        $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($orderPlaceholders)")->execute($orderIds);
        $pdo->prepare("DELETE FROM orders WHERE id IN ($orderPlaceholders)")->execute($orderIds);
    }
}
