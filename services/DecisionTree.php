<?php

declare(strict_types=1);

final class DecisionTree
{
    public function __construct(private array $catalog) {}

    public function resolve(string $choice): array
    {
        $menu = ['id' => 'root', 'label' => 'Все услуги'];
        $human = ['id' => 'human', 'label' => 'Позвать специалиста'];
        if ($choice === 'human') {
            return ['label' => $human['label'], 'text' => '', 'choices' => []];
        }
        if ($choice === 'root') {
            $services = [];
            foreach ($this->catalog as $row) {
                $id = (int) $row['service_id'];
                $services[$id] = ['id' => 'service:' . $id, 'label' => $row['service_name']];
            }
            $services = array_reverse(array_values($services));
            return [
                'label' => $menu['label'],
                'text' => $services ? 'Выберите услугу, чтобы посмотреть тарифы.' : 'Список услуг сейчас обновляется.',
                'choices' => [...$services, $menu, $human],
            ];
        }
        if (!preg_match('/^(service|tariff):([1-9][0-9]*)$/D', $choice, $match)) {
            throw new InvalidArgumentException('Выберите доступный пункт меню.');
        }
        $isService = $match[1] === 'service';
        $key = $isService ? 'service_id' : 'id';
        $rows = array_values(array_filter($this->catalog, static fn (array $row) => (string) $row[$key] === $match[2]));
        if (!$rows) {
            throw new InvalidArgumentException('Этот пункт больше недоступен. Выберите другую услугу.');
        }

        $row = $rows[0];
        if ($isService) {
            $choices = array_map(static fn (array $tariff) => [
                'id' => 'tariff:' . $tariff['id'],
                'label' => $tariff['tariff_name'],
            ], $rows);
            return [
                'label' => $row['service_name'],
                'text' => trim($row['service_name'] . "\n" . ($row['service_description'] ?? '')),
                'choices' => [...$choices, $menu, $human],
            ];
        }
        $price = ($row['slug'] === 'other' || $row['price'] === null || $row['price'] === '')
            ? 'по договорённости'
            : 'от ' . number_format((int) $row['price'], 0, '', ' ') . ' ₽';
        $deadline = $row['deadline'] ?: 'по договорённости';
        return [
            'label' => $row['tariff_name'],
            'text' => $row['service_name'] . ' — ' . $row['tariff_name'] . "\n" . $row['description']
                . "\nСтоимость: " . $price . "\nСрок: " . $deadline,
            'choices' => [['id' => 'service:' . $row['service_id'], 'label' => 'Другие тарифы услуги'], $menu, $human],
        ];
    }
}
