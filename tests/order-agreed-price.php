<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/OrderService.php';
require_once __DIR__ . '/../services/OrderPricingService.php';
foreach (['orders', 'order_items', 'personal_data_consents'] as $table) {
    $definition = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    $definition = preg_replace('/^\s*CONSTRAINT[^\n]*\n?/m', '', $definition);
    $definition = preg_replace('/,\s*\)/', "\n)", $definition);
    $pdo->exec(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $definition));
}
function checkPrice(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo 'PASS: ' . $label . PHP_EOL;
}
$id = (int) $pdo->query('SELECT t.id FROM tariffs t JOIN services s ON s.id=t.service_id WHERE t.active=1 AND s.active=1 LIMIT 1')->fetchColumn();
$service = (int) $pdo->query('SELECT id FROM services WHERE active=1 LIMIT 1')->fetchColumn();
$orders = new OrderService($pdo);
$pricing = new OrderPricingService($pdo);
$token = bin2hex(random_bytes(32));
$customer = ['name'=>'Synthetic','phone'=>'+70000000000','comment'=>'Isolated test'];
$consent = ConsentService::evidence('synthetic_test', $token);
$quote = $orders->createCartOrder($customer, [$id], $token, $consent);
$read = static function () use ($pdo, $quote): array {
    $q=$pdo->prepare('SELECT total,price_agreed_at FROM orders WHERE id=?');$q->execute([$quote['id']]);return $q->fetch(PDO::FETCH_ASSOC);
};
checkPrice($quote['total'] === null && $read()['total'] === null, 'new cart application has no total');
checkPrice($pdo->query('SELECT price FROM order_items LIMIT 1')->fetchColumn() === null, 'catalog prices are not stored in new items');
$lead=$orders->createLead($customer,$service,bin2hex(random_bytes(32)),$consent);
checkPrice($lead['total'] === null, 'consultation application also has no total');
$pricing->save($quote['id'], '45000');
checkPrice((int)$read()['total'] === 45000 && $read()['price_agreed_at'] !== null, 'admin can save an agreed price and timestamp');
$repeat=$orders->createCartOrder($customer,[$id],$token,$consent);
checkPrice(!$repeat['created'] && $repeat['total'] === 45000 && (int)$read()['total'] === 45000, 'duplicate submission preserves agreed price');
foreach (['-1','12.5','1e4','4294967296','<script>','1,000'] as $bad) {
    try { $pricing->save($quote['id'],$bad); throw new RuntimeException('Invalid price accepted'); }
    catch (DomainException) {}
}
checkPrice((int)$read()['total'] === 45000, 'invalid amounts never alter stored price');
$pricing->save($quote['id'],'0');checkPrice((int)$read()['total']===0,'explicit zero is distinct from no agreement');
$pricing->save($quote['id'],'');checkPrice($read()['total']===null && $read()['price_agreed_at']===null,'empty value clears agreement');
$pdo->prepare('UPDATE orders SET personal_data_erased_at=UTC_TIMESTAMP() WHERE id=?')->execute([$quote['id']]);
try { $pricing->save($quote['id'],'5000');throw new RuntimeException('Erased order changed'); } catch (DomainException) {}
checkPrice($read()['total']===null,'erased applications cannot be repriced');
echo "All writes used temporary tables. No notifications or real orders were created.\n";
