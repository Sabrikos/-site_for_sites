<?php
declare(strict_types=1);

final class OrderPricingService
{
    public function __construct(private PDO $pdo) {}

    public static function parseAmount(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') return null;
        if (!preg_match('/^(0|[1-9][0-9]{0,9})$/D', $value) || (int) $value > 4294967295) {
            throw new DomainException('Укажите сумму в целых рублях от 0 до 4 294 967 295 или оставьте поле пустым.');
        }
        return (int) $value;
    }

    public function save(int $orderId, string $value): void
    {
        $amount = self::parseAmount($value);
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare('SELECT id, personal_data_erased_at FROM orders WHERE id = ? FOR UPDATE');
            $query->execute([$orderId]);
            $order = $query->fetch(PDO::FETCH_ASSOC);
            if (!$order || $order['personal_data_erased_at'] !== null) {
                throw new DomainException('Заявка не найдена или её данные уже удалены.');
            }
            $this->pdo->prepare('UPDATE orders SET total = ?, price_agreed_at = ? WHERE id = ?')
                ->execute([$amount, $amount === null ? null : gmdate('Y-m-d H:i:s'), $orderId]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}
