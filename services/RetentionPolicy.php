<?php
declare(strict_types=1);

final class RetentionPolicy
{
    public const LEAD_MONTHS = 6;
    public const CHAT_MONTHS = 3;
    public const STATUSES = [
        'new' => 'Новое обращение', 'discussion' => 'Обсуждение',
        'processing' => 'В работе', 'completed' => 'Выполнен', 'closed' => 'Закрыто без заказа',
    ];

    public static function addMonths(string $utc, int $months): string
    {
        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $target = $date->modify('first day of this month')->modify('+' . $months . ' months');
        $day = min((int) $date->format('d'), (int) $target->format('t'));
        return $target->setDate((int) $target->format('Y'), (int) $target->format('m'), $day)->format('Y-m-d H:i:s');
    }

    public static function protected(string $status): bool
    {
        return !in_array($status, ['new', 'discussion', 'closed'], true);
    }

    public static function orderDeadline(array $order): ?string
    {
        if (self::protected($order['status'])) return null;
        $deadline = self::addMonths($order['last_contact_at'] ?: $order['created_at'], self::LEAD_MONTHS);
        if ($order['status'] === 'closed' && !empty($order['closed_at'])) {
            $deadline = min($deadline, (new DateTimeImmutable($order['closed_at'], new DateTimeZone('UTC')))->modify('+30 days')->format('Y-m-d H:i:s'));
        }
        return $deadline;
    }
}
