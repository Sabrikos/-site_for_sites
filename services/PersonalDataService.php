<?php

declare(strict_types=1);

final class PersonalDataService
{
    public static function withdrawAndErase(PDO $pdo, int $orderId, string $channel): bool
    {
        $pdo->beginTransaction();
        try {
            $consent = $pdo->prepare(
                'UPDATE personal_data_consents
                 SET withdrawn_at = COALESCE(withdrawn_at, UTC_TIMESTAMP()), withdrawal_channel = ?
                 WHERE order_id = ?'
            );
            $consent->execute([mb_substr($channel, 0, 30, 'UTF-8'), $orderId]);

            $order = $pdo->prepare(
                "UPDATE orders
                 SET customer_name = 'Удалено по требованию субъекта', phone = '', project_comment = NULL,
                     personal_data_erased_at = COALESCE(personal_data_erased_at, UTC_TIMESTAMP())
                 WHERE id = ? AND personal_data_erased_at IS NULL"
            );
            $order->execute([$orderId]);
            $changed = $order->rowCount() === 1;
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
            $orders = $pdo->prepare(
                "UPDATE orders
                 SET customer_name = 'Удалено по истечении срока хранения', phone = '', project_comment = NULL,
                     personal_data_erased_at = UTC_TIMESTAMP()
                 WHERE personal_data_erased_at IS NULL
                   AND personal_data_expires_at IS NOT NULL
                   AND personal_data_expires_at <= UTC_TIMESTAMP()"
            );
            $orders->execute();

            $conversationIds = array_map('intval', $pdo->query(
                'SELECT id FROM chat_conversations
                 WHERE personal_data_expires_at IS NOT NULL AND personal_data_expires_at <= UTC_TIMESTAMP()'
            )->fetchAll(PDO::FETCH_COLUMN));
            if ($conversationIds !== []) {
                $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
                $pdo->prepare("UPDATE telegram_sessions SET conversation_id = NULL WHERE conversation_id IN ($placeholders)")
                    ->execute($conversationIds);
                $pdo->prepare("DELETE FROM telegram_assignments WHERE conversation_id IN ($placeholders)")
                    ->execute($conversationIds);
                $pdo->prepare("DELETE FROM chat_conversations WHERE id IN ($placeholders)")
                    ->execute($conversationIds);
            }

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
}
