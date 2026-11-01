<?php

declare(strict_types=1);

final class ConsentService
{
    public const PURPOSE_CODE = 'project_request';
    public const CONSENT_VERSION = 'project-request-v1';
    public const PRIVACY_POLICY_VERSION = '2026-10-31';
    public const RETENTION_DAYS = 365;
    public const CONSENT_TEXT = 'Я свободно, своей волей и в своем интересе даю оператору Полехиной Ксении Евгеньевне согласие на обработку моих имени, номера телефона, текста обращения и сведений о выбранной услуге с целью рассмотрения обращения, связи со мной, обсуждения проекта и подготовки предложения. Разрешенные действия: сбор, запись, систематизация, накопление, хранение, уточнение, извлечение, использование, блокирование, удаление и уничтожение с применением средств автоматизации или без них. Согласие действует до достижения цели обработки, отзыва согласия или истечения одного года с даты обращения, в зависимости от того, что наступит раньше. Отозвать согласие можно по адресу Ve9a.Studio@yandex.ru.';

    public static function evidence(string $source, string $nonce, ?string $acceptedAt = null): array
    {
        $acceptedAt = $acceptedAt ?: gmdate('Y-m-d H:i:s');
        $acceptedTimestamp = strtotime($acceptedAt . ' UTC');
        if ($acceptedTimestamp === false) {
            throw new InvalidArgumentException('Invalid consent acceptance time.');
        }
        $retentionUntil = gmdate('Y-m-d H:i:s', $acceptedTimestamp + self::RETENTION_DAYS * 86400);
        $fingerprint = implode('|', [
            self::CONSENT_VERSION,
            self::PRIVACY_POLICY_VERSION,
            self::CONSENT_TEXT,
            $source,
            $nonce,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            $acceptedAt,
        ]);

        return [
            'purpose_code' => self::PURPOSE_CODE,
            'consent_version' => self::CONSENT_VERSION,
            'privacy_policy_version' => self::PRIVACY_POLICY_VERSION,
            'consent_text' => self::CONSENT_TEXT,
            'source' => $source,
            'accepted_at' => $acceptedAt,
            'evidence_hash' => hash('sha256', $fingerprint),
            'retention_until' => $retentionUntil,
        ];
    }

    public static function record(PDO $pdo, int $orderId, array $evidence): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO personal_data_consents
                (order_id, purpose_code, consent_version, privacy_policy_version, consent_text,
                 source, accepted_at, evidence_hash, retention_until)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $orderId,
            $evidence['purpose_code'],
            $evidence['consent_version'],
            $evidence['privacy_policy_version'],
            $evidence['consent_text'],
            $evidence['source'],
            $evidence['accepted_at'],
            $evidence['evidence_hash'],
            $evidence['retention_until'],
        ]);
    }
}
