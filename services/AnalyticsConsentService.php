<?php

declare(strict_types=1);

final class AnalyticsConsentService
{
    public const VERSION = '2026-11-05-v3';
    public const POLICY_VERSION = '2026-11-10-draft-1';
    public const COUNTER_ID = 114096795;
    public const CHOICE_MONTHS = 6;
    public const EVIDENCE_MONTHS = 12;
    public const TEXT = 'Разрешить Яндекс Метрике собирать статистику посещений? Для анализа работы сайта Яндексу передаются технические сведения о браузере, устройстве, IP-адресе и посещённых страницах. Используются аналитические cookie. Разрешение действует 6 месяцев. Подтверждение согласия хранится 12 месяцев после его отзыва или окончания действия. Запись об отказе хранится 12 месяцев с даты отказа. Выбор можно изменить в любой момент. После отзыва ранее выданного согласия страница перезагрузится.';

    public static function addMonths(string $utcDate, int $months): string
    {
        $date = new DateTimeImmutable($utcDate, new DateTimeZone('UTC'));
        $target = $date->modify('first day of this month')->modify('+' . $months . ' months');
        $day = min((int) $date->format('d'), (int) $target->format('t'));
        return $target->setDate((int) $target->format('Y'), (int) $target->format('m'), $day)->format('Y-m-d H:i:s');
    }

    public function __construct(private PDO $pdo)
    {
    }

    public function state(string $browserHash): array
    {
        $statement = $this->pdo->prepare(
            'SELECT request_id, decision, consent_version, choice_expires_at
             FROM analytics_consent_events WHERE browser_hash = ? ORDER BY id DESC LIMIT 1'
        );
        $statement->execute([$browserHash]);
        $event = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            return ['choice' => null, 'receipt' => null, 'expiresAt' => null];
        }
        $expires = $event['choice_expires_at'] === null ? 0 : strtotime($event['choice_expires_at'] . ' UTC');
        $valid = $event['consent_version'] === self::VERSION && $expires > time();
        return [
            'choice' => $valid ? ($event['decision'] === 'accepted' ? 'accepted' : 'rejected') : null,
            'receipt' => $event['request_id'],
            'expiresAt' => $valid ? $expires * 1000 : null,
        ];
    }

    public function record(string $browserHash, string $requestId, string $choice, ?string $expectedReceipt): array
    {
        // Serialize even requests from different PHP sessions sharing the preference cookie.
        $lockName = 'analytics-consent:' . substr($browserHash, 0, 40);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 3)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('Consent is busy.');
        }
        try {
            $existing = $this->pdo->prepare('SELECT decision FROM analytics_consent_events WHERE browser_hash = ? AND request_id = ?');
            $existing->execute([$browserHash, $requestId]);
            $previous = $existing->fetchColumn();
            if ($previous !== false) {
                if (($previous === 'accepted') !== ($choice === 'accepted')) {
                    throw new DomainException('Request ID already used for a different decision.');
                }
                return $this->state($browserHash);
            }
            $current = $this->state($browserHash);
            if ($choice === 'accepted' && $expectedReceipt !== $current['receipt']) {
                throw new DomainException('Consent changed in another request.');
            }
            $decision = $choice === 'rejected' && $current['choice'] === 'accepted' ? 'withdrawn' : $choice;
            $now = gmdate('Y-m-d H:i:s');
            $expires = self::addMonths($now, self::CHOICE_MONTHS);
            $retention = self::addMonths($choice === 'accepted' ? $expires : $now, self::EVIDENCE_MONTHS);
            $this->pdo->beginTransaction();
            try {
                // A new decision ends the preceding permission without rewriting its text or date.
                $previousConsent = $this->pdo->prepare(
                    "UPDATE analytics_consent_events
                     SET retention_until = DATE_ADD(LEAST(choice_expires_at, ?), INTERVAL 12 MONTH),
                         choice_expires_at = LEAST(choice_expires_at, ?)
                     WHERE browser_hash = ? AND request_id = ? AND decision = 'accepted'"
                );
                $previousConsent->execute([$now, $now, $browserHash, $current['receipt']]);
                $statement = $this->pdo->prepare(
                    'INSERT INTO analytics_consent_events
                        (browser_hash, request_id, decision, consent_version, consent_text,
                         privacy_policy_version, counter_id, created_at, choice_expires_at, retention_until)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE id = id'
                );
                $statement->execute([
                    $browserHash, $requestId, $decision, self::VERSION, self::TEXT,
                    self::POLICY_VERSION, self::COUNTER_ID, $now, $expires, $retention,
                ]);
                $this->pdo->commit();
            } catch (Throwable $error) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $error;
            }
            return $this->state($browserHash);
        } finally {
            $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
    }

    public function eraseExpired(): int
    {
        // Bounded batches keep the daily maintenance task from holding a large transaction.
        $total = 0;
        do {
            $statement = $this->pdo->prepare(
                'DELETE FROM analytics_consent_events
                 WHERE retention_until <= UTC_TIMESTAMP() ORDER BY retention_until, id LIMIT 500'
            );
            $statement->execute();
            $deleted = $statement->rowCount();
            $total += $deleted;
        } while ($deleted === 500);
        return $total;
    }
}
