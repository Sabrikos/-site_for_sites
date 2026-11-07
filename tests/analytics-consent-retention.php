<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/AnalyticsConsentService.php';

function checkAnalyticsRetention(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// The connection-local table shadows the real journal and disappears on disconnect.
$create = $pdo->query('SHOW CREATE TABLE analytics_consent_events')->fetch(PDO::FETCH_NUM)[1];
$pdo->exec(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create));
$service = new AnalyticsConsentService($pdo);

foreach ([
    ['2026-08-31 12:30:00', 6, '2027-02-28 12:30:00'],
    ['2023-08-31 12:30:00', 6, '2024-02-29 12:30:00'],
    ['2024-02-29 12:30:00', 12, '2025-02-28 12:30:00'],
    ['2026-01-31 12:30:00', 6, '2026-07-31 12:30:00'],
] as [$date, $months, $expected]) {
    checkAnalyticsRetention(AnalyticsConsentService::addMonths($date, $months) === $expected, 'Calendar month boundary');
}

$browser = hash('sha256', 'isolated-retention-test');
$accept = '00000000-0000-4000-8000-000000000001';
$withdraw = '00000000-0000-4000-8000-000000000002';
$replacement = '00000000-0000-4000-8000-000000000003';
$state = $service->record($browser, $accept, 'accepted', null);
$original = $pdo->query('SELECT * FROM analytics_consent_events ORDER BY id LIMIT 1')->fetch();
checkAnalyticsRetention($original['choice_expires_at'] === AnalyticsConsentService::addMonths($original['created_at'], 6), 'Consent duration');
checkAnalyticsRetention($original['retention_until'] === AnalyticsConsentService::addMonths($original['choice_expires_at'], 12), 'Evidence after expiry');
$service->record($browser, $accept, 'accepted', null);
checkAnalyticsRetention((int) $pdo->query('SELECT COUNT(*) FROM analytics_consent_events')->fetchColumn() === 1, 'Retry must not extend consent');
$service->record($browser, $withdraw, 'rejected', $accept);
$events = $pdo->query('SELECT * FROM analytics_consent_events ORDER BY id')->fetchAll();
checkAnalyticsRetention($events[1]['decision'] === 'withdrawn', 'Withdrawal event');
checkAnalyticsRetention($events[0]['choice_expires_at'] === $events[1]['created_at'], 'Consent ends at withdrawal');
checkAnalyticsRetention($events[0]['retention_until'] === AnalyticsConsentService::addMonths($events[1]['created_at'], 12), 'Evidence after withdrawal');
checkAnalyticsRetention($events[0]['consent_text'] === $original['consent_text'] && $events[0]['created_at'] === $original['created_at'], 'Original proof preserved');
try {
    $service->record($browser, $replacement, 'accepted', $accept);
    throw new RuntimeException('Stale acceptance allowed');
} catch (DomainException $expected) {
}
checkAnalyticsRetention($service->record($browser, $accept, 'accepted', null)['choice'] === 'rejected', 'Old retry cannot restore permission');
$service->record($browser, $replacement, 'accepted', $withdraw);
$pdo->exec("UPDATE analytics_consent_events SET choice_expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE request_id = '$replacement'");
checkAnalyticsRetention($service->state($browser)['choice'] === null, 'Expired permission requires new consent');

// Exercise the historical backfill against the isolated table, including a revoked record.
$insert = $pdo->prepare('INSERT INTO analytics_consent_events
    (browser_hash, request_id, decision, consent_version, consent_text, privacy_policy_version, counter_id, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$historyBrowser = hash('sha256', 'isolated-history-test');
foreach ([['accepted', '2024-01-31 12:00:00'], ['withdrawn', '2024-03-15 12:00:00']] as $i => [$decision, $created]) {
    $insert->execute([$historyBrowser, sprintf('10000000-0000-4000-8000-%012d', $i), $decision, 'old', 'old consent', 'old', 114096795, $created]);
}
// MySQL cannot reference one TEMPORARY table twice. Snapshot each read alias separately.
$pdo->exec('CREATE TEMPORARY TABLE analytics_consent_previous AS SELECT * FROM analytics_consent_events');
$pdo->exec('CREATE TEMPORARY TABLE analytics_consent_following AS SELECT * FROM analytics_consent_events');
foreach (explode(';', file_get_contents(__DIR__ . '/../migrations/008_analytics_retention.sql')) as $sql) {
    if (str_starts_with(trim($sql), 'UPDATE analytics_consent_events')) {
        $sql = str_replace(
            ['FROM analytics_consent_events AS previous', 'JOIN analytics_consent_events AS following'],
            ['FROM analytics_consent_previous AS previous', 'JOIN analytics_consent_following AS following'],
            $sql
        );
        $pdo->exec($sql);
    }
}
$history = $pdo->prepare('SELECT * FROM analytics_consent_events WHERE browser_hash = ? ORDER BY id');
$history->execute([$historyBrowser]);
$old = $history->fetchAll();
checkAnalyticsRetention($old[0]['choice_expires_at'] === '2024-03-15 12:00:00' && $old[0]['retention_until'] === '2025-03-15 12:00:00', 'Historical withdrawal backfill');

for ($i = 0; $i < 501; $i++) {
    $insert->execute([hash('sha256', 'expired-' . $i), sprintf('20000000-0000-4000-8000-%012d', $i), 'rejected', 'old', 'old', 'old', 114096795, '2020-01-01 00:00:00']);
}
$pdo->exec("UPDATE analytics_consent_events SET retention_until = '2021-01-01 00:00:00' WHERE retention_until IS NULL");
$expectedDeleted = (int) $pdo->query('SELECT COUNT(*) FROM analytics_consent_events WHERE retention_until <= UTC_TIMESTAMP()')->fetchColumn();
checkAnalyticsRetention($service->eraseExpired() === $expectedDeleted, 'Batched cleanup count');
checkAnalyticsRetention((int) $pdo->query('SELECT COUNT(*) FROM analytics_consent_events')->fetchColumn() === 3, 'Unexpired proof preserved');
checkAnalyticsRetention($service->eraseExpired() === 0, 'Cleanup is repeatable');

echo "PASS calendar months, evidence deadlines, withdrawal, retries, backfill and isolated cleanup\n";
