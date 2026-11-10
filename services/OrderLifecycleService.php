<?php
declare(strict_types=1);
require_once __DIR__ . '/RetentionPolicy.php';
require_once __DIR__ . '/ConsentService.php';

final class OrderLifecycleService
{
    public function __construct(private PDO $pdo) {}

    public static function contactFromForm(string $value): ?string
    {
        if ($value === '') return null;
        $zone = new DateTimeZone('Europe/Moscow');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $zone);
        if (!$date || $date->format('Y-m-d\TH:i') !== $value || $date > new DateTimeImmutable('now', $zone)) {
            throw new DomainException('Укажите корректную дату контакта, не позднее текущего времени.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function update(int $id, string $status, ?string $contact = null): void
    {
        if (!isset(RetentionPolicy::STATUSES[$status])) throw new DomainException('Неизвестный статус заявки.');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $statement->execute([$id]);
            $order = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['personal_data_erased_at']) throw new DomainException('Заявка не найдена или её данные уже удалены.');
            if ($contact !== null) {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $contact, new DateTimeZone('UTC'));
                if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $contact || $contact > gmdate('Y-m-d H:i:s')) throw new DomainException('Некорректная дата контакта.');
            }
            $now = gmdate('Y-m-d H:i:s');
            $order['last_contact_at'] = $contact ?? $order['last_contact_at'] ?? $order['created_at'];
            $order['completed_at'] = $status === 'completed' && $order['status'] !== 'completed' ? $now : $order['completed_at'];
            $order['closed_at'] = $status === 'closed' ? ($order['closed_at'] ?: $now) : null;
            $order['status'] = $status;
            $deadline = RetentionPolicy::orderDeadline($order);
            if ($deadline !== null) {
                $legacy = $this->pdo->prepare("SELECT consent_expires_at FROM personal_data_consents WHERE order_id = ? AND consent_version = 'project-request-v1' ORDER BY id DESC LIMIT 1");
                $legacy->execute([$id]);
                if ($limit = $legacy->fetchColumn()) $deadline = min($deadline, $limit);
            }
            $this->pdo->prepare('UPDATE orders SET status = ?, last_contact_at = ?, completed_at = ?, closed_at = ?, personal_data_expires_at = ? WHERE id = ?')
                ->execute([$status, $order['last_contact_at'], $order['completed_at'], $order['closed_at'], $deadline, $id]);
            ConsentService::sync($this->pdo, $id);
            // Linking never resets the consultation clock; changing order status only changes its protection.
            $this->pdo->prepare("UPDATE chat_conversations SET personal_data_expires_at = CASE WHEN ? IS NULL THEN NULL ELSE
                LEAST(DATE_ADD(COALESCE(last_contact_at, created_at), INTERVAL 3 MONTH),
                    COALESCE(DATE_ADD(closed_at, INTERVAL 30 DAY), '9999-12-31 23:59:59'),
                    COALESCE(?, '9999-12-31 23:59:59')) END WHERE linked_order_id = ?")
                ->execute([$deadline, $status === 'closed' ? $deadline : null, $id]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function linkChat(int $orderId, int $chatId): void
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT status, personal_data_erased_at, personal_data_expires_at FROM orders WHERE id = ? FOR UPDATE');
            $statement->execute([$orderId]);
            $order = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['personal_data_erased_at']) throw new DomainException('Заказ не найден или его данные уже удалены.');
            $statement = $this->pdo->prepare('SELECT linked_order_id, last_contact_at, created_at, closed_at FROM chat_conversations WHERE id = ? FOR UPDATE');
            $statement->execute([$chatId]);
            $chat = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$chat || ($chat['linked_order_id'] && (int) $chat['linked_order_id'] !== $orderId)) throw new DomainException('Диалог не найден или уже связан с другим заказом.');
            $deadline = RetentionPolicy::protected($order['status']) ? null : RetentionPolicy::addMonths($chat['last_contact_at'] ?: $chat['created_at'], 3);
            if ($deadline && $chat['closed_at']) $deadline = min($deadline, (new DateTimeImmutable($chat['closed_at'], new DateTimeZone('UTC')))->modify('+30 days')->format('Y-m-d H:i:s'));
            if ($deadline && $order['status'] === 'closed' && $order['personal_data_expires_at']) $deadline = min($deadline, $order['personal_data_expires_at']);
            $this->pdo->prepare('UPDATE chat_conversations SET linked_order_id = ?, personal_data_expires_at = ? WHERE id = ?')->execute([$orderId, $deadline, $chatId]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function unlinkChat(int $orderId, int $chatId): void
    {
        $this->pdo->prepare('UPDATE chat_conversations SET linked_order_id = NULL,
            personal_data_expires_at = LEAST(DATE_ADD(COALESCE(last_contact_at, created_at), INTERVAL 3 MONTH),
                COALESCE(DATE_ADD(closed_at, INTERVAL 30 DAY), \'9999-12-31 23:59:59\'))
            WHERE id = ? AND linked_order_id = ?')->execute([$chatId, $orderId]);
    }
}
