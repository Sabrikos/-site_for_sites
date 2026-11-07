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
        'comment' => 'Temporary integration test record.',
    ];
    $orders = new OrderService($pdo);

    $leadConsent = ConsentService::evidence('test_lead', $tokens[0]);
    $cartConsent = ConsentService::evidence('test_cart', $tokens[1]);

    $lead = $orders->createLead($customer, (int) $service['id'], $tokens[0], $leadConsent);
    $leadRepeat = $orders->createLead($customer, (int) $service['id'], $tokens[0], $leadConsent);
    if (!$lead['created'] || $leadRepeat['created'] || $lead['id'] !== $leadRepeat['id'] || $lead['total'] !== 0) {
        throw new RuntimeException('Lead idempotency failed.');
    }

    $cart = $orders->createCartOrder($customer, [(int) $tariff['id']], $tokens[1], $cartConsent);
    $cartRepeat = $orders->createCartOrder($customer, [(int) $tariff['id']], $tokens[1], $cartConsent);
    if (!$cart['created'] || $cartRepeat['created'] || $cart['id'] !== $cartRepeat['id']
        || $cart['total'] !== (int) $tariff['price']) {
        throw new RuntimeException('Cart idempotency or server-side price failed.');
    }

    $consentCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM personal_data_consents
         WHERE order_id IN (?, ?) AND consent_version = ? AND privacy_policy_version = ?'
    );
    $consentCheck->execute([
        $lead['id'],
        $cart['id'],
        ConsentService::CONSENT_VERSION,
        ConsentService::PRIVACY_POLICY_VERSION,
    ]);
    if ((int) $consentCheck->fetchColumn() !== 2) {
        throw new RuntimeException('Consent evidence persistence failed.');
    }

    echo "PASS: orders, idempotency, database price, and consent evidence.\n";
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
