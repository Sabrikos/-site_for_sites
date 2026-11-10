<?php

declare(strict_types=1);
require_once __DIR__ . '/ConsentService.php';

final class PersonalDataService
{
    public static function withdrawAndErase(PDO $pdo, int $orderId, string $channel): bool
    {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id FROM orders WHERE id = ? FOR UPDATE');
            $lock->execute([$orderId]);
            $consent = $pdo->prepare(
                'UPDATE personal_data_consents
                 SET withdrawn_at = COALESCE(withdrawn_at, UTC_TIMESTAMP()), withdrawal_channel = ?
                 WHERE order_id = ?'
            );
            $consent->execute([mb_substr($channel, 0, 30, 'UTF-8'), $orderId]);
            ConsentService::sync($pdo, $orderId);

            $order = $pdo->prepare(
                "UPDATE orders
                 SET customer_name = 'Удалено по требованию субъекта', phone = '', project_comment = NULL,
                     personal_data_erased_at = COALESCE(personal_data_erased_at, UTC_TIMESTAMP())
                 WHERE id = ? AND personal_data_erased_at IS NULL"
            );
            $order->execute([$orderId]);
            $changed = $order->rowCount() === 1;
            $chats = $pdo->prepare('SELECT id FROM chat_conversations WHERE linked_order_id = ? FOR UPDATE');
            $chats->execute([$orderId]);
            self::eraseConversations($pdo, array_map('intval', $chats->fetchAll(PDO::FETCH_COLUMN)));
            $pdo->commit();
            return $changed;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    public static function eraseExpired(PDO $pdo): array
    {
        $pdo->beginTransaction();
        try {
            ConsentService::sync($pdo);
            $orders = $pdo->prepare(
                "UPDATE orders
                 SET customer_name = 'Удалено по истечении срока хранения', phone = '', project_comment = NULL,
                     personal_data_erased_at = UTC_TIMESTAMP()
                 WHERE personal_data_erased_at IS NULL
                   AND status IN ('new', 'discussion', 'closed')
                   AND personal_data_expires_at IS NOT NULL
                   AND personal_data_expires_at <= UTC_TIMESTAMP()"
            );
            $orders->execute();
            $consents = $pdo->prepare("DELETE FROM personal_data_consents WHERE purpose_code = 'project_request'
                AND consent_ended_at IS NOT NULL AND retention_until <= UTC_TIMESTAMP()");
            $consents->execute();

            $conversationIds = array_map('intval', $pdo->query(
                "SELECT c.id FROM chat_conversations c LEFT JOIN orders o ON o.id = c.linked_order_id
                 WHERE c.personal_data_expires_at IS NOT NULL AND c.personal_data_expires_at <= UTC_TIMESTAMP()
                   AND (o.id IS NULL OR o.status IN ('new', 'discussion', 'closed')) FOR UPDATE"
            )->fetchAll(PDO::FETCH_COLUMN));
            self::eraseConversations($pdo, $conversationIds);

            $telegramIds = array_map('intval', $pdo->query(
                'SELECT chat_id FROM telegram_profiles
                 WHERE personal_data_expires_at IS NOT NULL AND personal_data_expires_at <= UTC_TIMESTAMP()'
            )->fetchAll(PDO::FETCH_COLUMN));
            if ($telegramIds !== []) {
                $placeholders = implode(',', array_fill(0, count($telegramIds), '?'));
                $pdo->prepare("DELETE FROM telegram_relays WHERE client_chat_id IN ($placeholders)")
                    ->execute($telegramIds);
                $pdo->prepare("DELETE FROM telegram_sessions WHERE chat_id IN ($placeholders)")
                    ->execute($telegramIds);
                $pdo->prepare("DELETE FROM telegram_profiles WHERE chat_id IN ($placeholders)")
                    ->execute($telegramIds);
            }

            $result = [
                'orders' => $orders->rowCount(),
                'conversations' => count($conversationIds),
                'telegram_profiles' => count($telegramIds),
                'consent_evidence' => $consents->rowCount(),
            ];
            $pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function eraseConversations(PDO $pdo, array $ids): void
    {
        if ($ids === []) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE telegram_sessions SET conversation_id = NULL WHERE conversation_id IN ($placeholders)")->execute($ids);
        foreach (['telegram_assignments', 'chat_admin_reads', 'chat_admin_presence', 'chat_assistant_state', 'chat_messages'] as $table) {
            $pdo->prepare("DELETE FROM $table WHERE conversation_id IN ($placeholders)")->execute($ids);
        }
        $pdo->prepare("DELETE FROM chat_conversations WHERE id IN ($placeholders)")->execute($ids);
    }
}
