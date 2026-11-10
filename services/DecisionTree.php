<?php

declare(strict_types=1);

require_once __DIR__ . '/TariffPresentation.php';

final class DecisionTree
{
    public function __construct(private array $catalog)
    {
        // Match the base services shown on tariffs.php while retaining real database IDs.
        $base = [];
        foreach ($catalog as $row) {
            if (in_array($row['slug'], ['landing', 'shop', 'revision'], true)) {
                $serviceId = (int) $row['service_id'];
                if (!isset($base[$serviceId]) || (int) $row['price'] < (int) $base[$serviceId]['price']) {
                    $base[$serviceId] = $row;
                }
            }
        }
        $this->catalog = array_values(array_filter($catalog, static fn(array $row): bool =>
            !isset($base[(int) $row['service_id']]) || (int) $row['id'] === (int) $base[(int) $row['service_id']]['id']
        ));
    }

    private function serviceReply(array $row): string
    {
        return match ($row['slug']) {
            'landing' => "Какой сайт Вам нужен?\n\n• Старт — сайт для одной услуги или товара.\n• Бизнес — сайт для подробного рассказа о компании.\n• Премиум — сайт с индивидуальным дизайном и функциями.\n\nВыберите подходящий тариф ниже.",
            'shop' => "Какой интернет-магазин Вам нужен?\n\n• Старт — небольшой каталог и корзина.\n• Бизнес — магазин с онлайн-оплатой.\n• Премиум — магазин с подключением внешних сервисов.\n\nВыберите подходящий тариф ниже.",
            'revision' => "Что нужно изменить на сайте?\n\n• Мелкая доработка — исправить отдельный элемент или ошибку.\n• Расширенная доработка — добавить страницы, блоки или функции.\n• Комплексная доработка — переработать несколько разделов.\n\nВыберите подходящий вариант ниже.",
            'ai' => "Что именно Вас интересует?\n\n• Telegram-бот — приём заявок, уведомления и автоматические действия в Telegram.\n• AI-ассистент — ответы на вопросы клиентов о Ваших услугах.\n• AI для бизнеса — автоматизация рабочих задач с помощью ИИ и подключение сервисов.\n\nВыберите подходящий вариант ниже.",
            'other' => "Нужна помощь с другой задачей?\n\n• Индивидуальный проект — решение под Вашу задачу. Состав работ, стоимость и сроки обсудим отдельно.\n\nЕсли Вас интересует это решение, можете добавить его в корзину или позвать специалиста.",
            default => trim((string) ($row['service_description'] ?? '')) . "\n\nВыберите подходящий вариант ниже.",
        };
    }

    public function resolve(string $choice): array
    {
        $extras = [
            'automation' => ['Автоматизация задач', 'Поможем сократить ручной ввод, собрать данные и настроить повторяющиеся процессы.'],
            'reports' => ['Таблицы и отчёты', 'Приведём данные в порядок, настроим расчёты и понятные отчёты для ежедневной работы.'],
            'internal' => ['Внутренние инструменты', 'Разработаем небольшую панель, каталог или личный кабинет для задач Вашей команды.'],
            'integrations' => ['Связь между сервисами', 'Поможем передавать данные между сайтом, таблицами и другими системами, к которым у Вас есть доступ.'],
        ];
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
                'text' => $services ? 'Что Вы хотите заказать? Выберите услугу ниже.' : 'Список услуг сейчас обновляется. Нажмите «Позвать специалиста», чтобы обсудить задачу.',
                'choices' => [...$services, ['id' => 'extras', 'label' => 'Другие задачи и вопросы'], $human],
            ];
        }
        if ($choice === 'extras') {
            $choices = [];
            foreach ($extras as $id => $extra) {
                $choices[] = ['id' => 'extra:' . $id, 'label' => $extra[0]];
            }
            return [
                'label' => 'Другие задачи и вопросы',
                'text' => 'Что Вас интересует? Выберите задачу или вопрос ниже.',
                'choices' => [...$choices, ['id' => 'terms', 'label' => 'Оплата, договор и правки'], $menu, $human],
            ];
        }
        if (str_starts_with($choice, 'extra:') && isset($extras[substr($choice, 6)])) {
            [$label, $description] = $extras[substr($choice, 6)];
            return [
                'label' => $label,
                'text' => $description . "\n\nСтоимость и сроки обсудим после знакомства с Вашей задачей.\nНажмите «Позвать специалиста», чтобы обсудить детали.",
                'choices' => [['id' => 'extras', 'label' => 'Другие задачи и вопросы'], $menu, $human],
            ];
        }
        if ($choice === 'terms') {
            return [
                'label' => 'Условия работы',
                'text' => "Специалист расскажет:\n\n• Как проходит оплата.\n• Как заключается договор.\n• Как согласовываются правки и поддержка.\n\nНажмите «Позвать специалиста», чтобы уточнить условия для Вашего проекта.",
                'choices' => [['id' => 'extras', 'label' => 'Другие задачи и вопросы'], $menu, $human],
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
            if (count($rows) === 1) {
                $reply = $this->resolve('tariff:' . $row['id']);
                $reply['label'] = $row['service_name'];
                return $reply;
            }
            $choices = array_map(static fn (array $tariff) => [
                'id' => 'tariff:' . $tariff['id'],
                'label' => $tariff['tariff_name'],
            ], $rows);
            return [
                'label' => $row['service_name'],
                'text' => $this->serviceReply($row),
                'choices' => [...$choices, $menu, $human],
            ];
        }
        $price = ($row['slug'] === 'other' || $row['price'] === null || $row['price'] === '')
            ? 'по договорённости'
            : 'от ' . number_format((int) $row['price'], 0, '', ' ') . ' ₽';
        $deadline = $row['deadline'] ?: 'по договорённости';
        $description = tariffPresentation($row['slug'], $row['tariff_name']);
        $text = $description
            ? implode("\n", array_map(static fn (string $item): string => count($description) > 1 ? '• ' . $item : $item, $description))
            : trim((string) $row['description']);
        $cartChoice = [
            'id' => 'cart:' . $row['id'],
            'label' => 'Добавить в корзину',
            'cart_item' => [
                'tariff_id' => (string) $row['id'],
                'service' => $row['service_name'],
                'tariff' => $row['tariff_name'],
                'price' => $row['price'] === null || $row['price'] === '' ? 0 : (int) $row['price'],
            ],
        ];
        $siblings = array_filter($this->catalog, static fn (array $item): bool => (string) $item['service_id'] === (string) $row['service_id']);
        $back = count($siblings) > 1 ? [['id' => 'service:' . $row['service_id'], 'label' => 'Другие варианты']] : [];
        return [
            'label' => $row['tariff_name'],
            'text' => $text . ($row['slug'] === 'other' ? '' : "\n\nСтоимость: " . $price . "\nСрок: " . $deadline)
                . "\n\nЕсли Вас интересует это решение, можете добавить его в корзину или позвать специалиста.",
            'choices' => [...$back, $menu, $human, $cartChoice],
        ];
    }
}
