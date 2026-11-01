<?php

declare(strict_types=1);

require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/TelegramService.php';
require_once __DIR__ . '/ConsentService.php';

final class TelegramBot
{
	public function __construct(private PDO $pdo, private TelegramService $telegram) {}

	public function handle(array $update): void
	{
		if (isset($update['callback_query'])) {
			$this->handleCallback($update['callback_query']);
			return;
		}

		$message = $update['message'] ?? null;
		if (!is_array($message) || !isset($message['chat']['id'])) {
			return;
		}

		$chatId = (int) $message['chat']['id'];
		$text = trim((string) ($message['text'] ?? ''));
		if ($text === '' || mb_strlen($text) > 2000) {
			return;
		}

		$session = $this->session($chatId);
		if ($this->isAdmin($chatId) && $this->adminRelay($chatId, $text)) {
			return;
		}
		if (($session['human_mode'] ?? 0) && !in_array($text, ['/ai', '/start'], true)) {
			$this->forwardToAdmins($chatId, $message, $text);
			return;
		}

		$lower = mb_strtolower($text);
		if ($text === '/start' || $text === '/help') {
			$this->menu($chatId);
		} elseif ($this->containsAny($lower, ['администратор', 'менеджер', 'человеком', 'человек'])) {
			$this->askAdmin($chatId, $message, $text);
		} elseif ($text === '/order' || $this->containsAny($lower, ['хочу заказать', 'оформить заказ', 'заказать сайт'])) {
			$this->beginOrder($chatId);
		} elseif ($text === '/services' || $this->containsAny($lower, ['услуг', 'что вы делаете'])) {
			$this->sendServices($chatId);
		} elseif ($text === '/tariffs' || $this->containsAny($lower, ['тариф', 'стоимость', 'цена'])) {
			$this->sendTariffs($chatId);
		} elseif ($session['state'] !== 'idle') {
			$this->continueOrder($chatId, $message, $text, $session);
		} else {
			$this->answerQuestion($chatId, $text);
		}
	}

	private function menu(int $chatId): void
	{
		$this->telegram->sendMessage($chatId, 'Здравствуйте! Я виртуальный помощник <b>Vega Studio</b>. Могу рассказать об услугах, помочь подобрать тариф, оформить заказ или позвать администратора.', [
			[['text' => '🌐 Наши услуги', 'callback_data' => 'services'], ['text' => '💰 Тарифы', 'callback_data' => 'tariffs']],
			[['text' => '🛒 Оформить заказ', 'callback_data' => 'order'], ['text' => '👤 Позвать администратора', 'callback_data' => 'admin']],
		]);
	}

	private function sendServices(int $chatId): void
	{
		$rows = $this->pdo->query('SELECT name, description, deadline FROM services WHERE active = 1 ORDER BY id')->fetchAll();
		$text = '<b>Услуги Vega Studio</b>\n\n';
		foreach ($rows as $row) {
			$text .= '<b>' . $this->esc($row['name']) . '</b>: ' . $this->esc($row['description'] ?? '') . '\nСрок: ' . $this->esc($row['deadline'] ?? 'по договорённости') . "\n\n";
		}
		$this->telegram->sendMessage($chatId, $text);
	}

	private function sendTariffs(int $chatId): void
	{
		$rows = $this->pdo->query('SELECT s.name AS service_name, t.name, t.price, t.description FROM tariffs t INNER JOIN services s ON s.id = t.service_id WHERE t.active = 1 AND s.active = 1 ORDER BY s.id, t.id')->fetchAll();
		$text = '<b>Тарифы</b>\n\n';
		foreach ($rows as $row) {
			$text .= '<b>' . $this->esc($row['service_name'] . ' — ' . $row['name']) . '</b>: ' . number_format((int) $row['price'], 0, '', ' ') . ' ₽\n' . $this->esc($row['description'] ?? '') . "\n\n";
		}
		$this->telegram->sendMessage($chatId, $text);
	}

	private function beginOrder(int $chatId): void
	{
		$this->saveSession($chatId, 'consent', []);
		$this->telegram->sendMessage($chatId, 'Для оформления заявки мы обработаем имя, номер телефона, выбранную услугу и текст обращения. Срок хранения — не более одного года. Ознакомьтесь с согласием: ' . appUrl('consent.php') . ' и политикой: ' . appUrl('privacy.php') . '. Если согласны, отправьте отдельным сообщением: <b>Согласен</b>.');
	}

	private function continueOrder(int $chatId, array $message, string $text, array $session): void
	{
		$data = json_decode((string) ($session['draft_data'] ?? '{}'), true) ?: [];
		switch ($session['state']) {
			case 'consent':
				if (!in_array(mb_strtolower($text, 'UTF-8'), ['согласен', 'даю согласие'], true)) {
					$this->telegram->sendMessage($chatId, 'Чтобы продолжить, отправьте отдельным сообщением: <b>Согласен</b>.');
					return;
				}
				$data['consent_accepted_at'] = gmdate('Y-m-d H:i:s');
				$data['consent_version'] = ConsentService::CONSENT_VERSION;
				$this->saveSession($chatId, 'service', $data);
				$rows = $this->pdo->query('SELECT id, name FROM services WHERE active = 1 ORDER BY id')->fetchAll();
				$keyboard = [];
				foreach ($rows as $row) {
					$keyboard[] = [['text' => $row['name'], 'callback_data' => 'service:' . (int) $row['id']]];
				}
				$this->telegram->sendMessage($chatId, 'Выберите услугу:', $keyboard);
				break;
			case 'comment':
				$data['comment'] = $text;
				$this->saveSession($chatId, 'name', $data);
				$this->telegram->sendMessage($chatId, 'Как вас зовут?');
				break;
			case 'name':
				$data['name'] = mb_substr($text, 0, 150);
				$this->saveSession($chatId, 'phone', $data);
				$this->telegram->sendMessage($chatId, 'Укажите номер телефона.');
				break;
			case 'phone':
				if (!preg_match('/^[0-9+()\-\s]{7,30}$/u', $text)) {
					$this->telegram->sendMessage($chatId, 'Нужен корректный номер телефона.');
					return;
				}
				$data['phone'] = $text;
				$data['username'] = $message['from']['username'] ?? '';
				$data['user_id'] = $chatId;
				$this->saveSession($chatId, 'confirm', $data);
				$this->telegram->sendMessage($chatId, $this->summary($data), [[['text' => '✅ Оформить заказ', 'callback_data' => 'confirm'], ['text' => '❌ Отмена', 'callback_data' => 'cancel']]]);
				break;
		}
	}

	private function handleCallback(array $callback): void
	{
		$chatId = (int) ($callback['message']['chat']['id'] ?? 0);
		$data = (string) ($callback['data'] ?? '');
		$this->telegram->answerCallback((string) ($callback['id'] ?? ''));
		if ($chatId === 0) return;
		if (str_starts_with($data, 'status:') || str_starts_with($data, 'resume:')) {
			if (!$this->isAdmin($chatId)) {
				return;
			}
			if (str_starts_with($data, 'status:')) {
				$parts = explode(':', $data);
				if (count($parts) === 3 && filter_var($parts[1], FILTER_VALIDATE_INT) !== false && in_array($parts[2], ['new', 'processing', 'completed'], true)) {
					$statement = $this->pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
					$statement->execute([$parts[2], (int) $parts[1]]);
					$this->telegram->sendMessage($chatId, 'Статус заказа #' . (int) $parts[1] . ' обновлён.');
				}
				return;
			}
			$clientId = (int) substr($data, strpos($data, ':') + 1);
			if ($clientId > 0) {
				$this->pdo->prepare('REPLACE INTO telegram_admin_links (admin_chat_id, client_chat_id) VALUES (?, ?)')->execute([$chatId, $clientId]);
				if (str_starts_with($data, 'resume:')) {
					$this->saveSession($clientId, 'idle', []);
					$this->telegram->sendMessage($clientId, 'Администратор завершил диалог. AI снова подключён.');
				}
			}
			return;
		}
		if ($data === 'services') $this->sendServices($chatId);
		elseif ($data === 'tariffs') $this->sendTariffs($chatId);
		elseif ($data === 'order') $this->beginOrder($chatId);
		elseif ($data === 'admin') $this->askAdmin($chatId, $callback['message'], 'Клиент нажал кнопку вызова администратора');
		elseif (str_starts_with($data, 'service:')) $this->chooseService($chatId, (int) substr($data, 8));
		elseif (str_starts_with($data, 'tariff:')) $this->chooseTariff($chatId, (int) substr($data, 7));
		elseif ($data === 'confirm') $this->createOrder($chatId);
		elseif ($data === 'cancel') {
			$this->saveSession($chatId, 'idle', []);
			$this->telegram->sendMessage($chatId, 'Оформление отменено.');
		} elseif ($data === 'resume_ai') {
			$this->saveSession($chatId, 'idle', []);
			$this->telegram->sendMessage($chatId, 'AI снова подключён.');
		}
	}

	private function chooseService(int $chatId, int $serviceId): void
	{
		$statement = $this->pdo->prepare('SELECT id, name FROM tariffs WHERE service_id = ? AND active = 1 ORDER BY id');
		$statement->execute([$serviceId]);
		$rows = $statement->fetchAll();
		$keyboard = [];
		foreach ($rows as $row) $keyboard[] = [['text' => $row['name'], 'callback_data' => 'tariff:' . (int) $row['id']]];
		$session = $this->session($chatId);
		$data = json_decode((string) ($session['draft_data'] ?? '{}'), true) ?: [];
		if (($data['consent_version'] ?? '') !== ConsentService::CONSENT_VERSION || empty($data['consent_accepted_at'])) {
			$this->telegram->sendMessage($chatId, 'Согласие на обработку данных не зафиксировано. Начните оформление заново.');
			return;
		}
		$data['service_id'] = $serviceId;
		$this->saveSession($chatId, 'tariff', $data);
		$this->telegram->sendMessage($chatId, 'Выберите тариф:', $keyboard);
	}

	private function chooseTariff(int $chatId, int $tariffId): void
	{
		$session = $this->session($chatId);
		$data = json_decode((string) ($session['draft_data'] ?? '{}'), true) ?: [];
		$data['tariff_id'] = $tariffId;
		$this->saveSession($chatId, 'comment', $data);
		$this->telegram->sendMessage($chatId, 'Коротко опишите задачу или пожелания к проекту.');
	}

	private function answerQuestion(int $chatId, string $text): void
	{
		$this->sendServices($chatId);
		$this->telegram->sendMessage($chatId, 'Я отвечаю по актуальным данным сайта. Напишите название услуги или тарифа, либо попросите оформить заказ. Для нестандартного вопроса можно позвать администратора.');
	}
	private function askAdmin(int $chatId, array $message, string $text): void
	{
		$this->saveSession($chatId, 'idle', ['human_mode' => 1], true);
		$this->telegram->sendMessage((string) appEnv('TELEGRAM_SUPPORT_CHAT_ID'), '<b>Клиент вызывает администратора</b>\nИсточник: Telegram\nКлиент: ' . $this->esc($message['from']['first_name'] ?? '') . '\nTelegram: @' . $this->esc($message['from']['username'] ?? '') . '\nUser ID: ' . $chatId . '\nПоследнее сообщение: ' . $this->esc($text));
		$this->telegram->sendMessage($chatId, 'Я передал запрос администратору. Он ответит здесь, как только подключится.');
	}
	private function forwardToAdmins(int $chatId, array $message, string $text): void
	{
		$this->telegram->sendMessage((string) appEnv('TELEGRAM_SUPPORT_CHAT_ID'), '<b>Сообщение клиента</b>\nИсточник: Telegram\nUser ID: ' . $chatId . '\n' . $this->esc($text));
	}
	private function adminRelay(int $adminId, string $text): bool
	{
		if ($text === '/ai') return false;
		$statement = $this->pdo->prepare('SELECT client_chat_id FROM telegram_admin_links WHERE admin_chat_id = ?');
		$statement->execute([$adminId]);
		$clientId = $statement->fetchColumn();
		if ($clientId === false) return false;
		if (str_starts_with($text, '/reply ')) $text = substr($text, 7);
		return $this->telegram->sendMessage((int) $clientId, '<b>Ответ администратора:</b>\n' . $this->esc($text));
	}
	private function createOrder(int $chatId): void
	{
		$session = $this->session($chatId);
		$data = json_decode((string) ($session['draft_data'] ?? '{}'), true) ?: [];
		if (($data['consent_version'] ?? '') !== ConsentService::CONSENT_VERSION || empty($data['consent_accepted_at'])) {
			$this->telegram->sendMessage($chatId, 'Согласие на обработку данных не зафиксировано. Начните оформление заново.');
			return;
		}
		$consent = ConsentService::evidence('telegram_order', 'telegram:' . $chatId, (string) $data['consent_accepted_at']);
		$statement = $this->pdo->prepare('SELECT t.id, t.name AS tariff_name, t.price, s.name AS service_name FROM tariffs t INNER JOIN services s ON s.id = t.service_id WHERE t.id = ? AND t.active = 1 AND s.active = 1');
		$statement->execute([(int) $data['tariff_id']]);
		$tariff = $statement->fetch();
		if (!$tariff) {
			$this->telegram->sendMessage($chatId, 'Выбранный тариф больше недоступен.');
			return;
		}
		$this->pdo->beginTransaction();
		try {
			$order = $this->pdo->prepare('INSERT INTO orders (customer_name, phone, project_comment, total, status, source, personal_data_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
			$order->execute([$data['name'], $data['phone'], $data['comment'], $tariff['price'], 'new', 'telegram', $consent['retention_until']]);
			$orderId = (int) $this->pdo->lastInsertId();
			$item = $this->pdo->prepare('INSERT INTO order_items (order_id, tariff_id, service_name, tariff_name, price) VALUES (?, ?, ?, ?, ?)');
			$item->execute([$orderId, $tariff['id'], $tariff['service_name'], $tariff['tariff_name'], $tariff['price']]);
			ConsentService::record($this->pdo, $orderId, $consent);
			$this->pdo->commit();
			$this->saveSession($chatId, 'idle', []);
			$this->telegram->sendMessage((string) appEnv('TELEGRAM_SUPPORT_CHAT_ID'), '<b>Новый заказ Vega Studio</b>\nЗаказ: #' . $orderId . '\nУслуга: ' . $this->esc($tariff['service_name']) . '\nТариф: ' . $this->esc($tariff['tariff_name']));
			$this->telegram->sendMessage($chatId, 'Заказ #' . $orderId . ' создан. Администратор свяжется с вами.');
		} catch (Throwable $error) {
			if ($this->pdo->inTransaction()) $this->pdo->rollBack();
			appLog('Telegram order creation failed', ['chat_id' => $chatId]);
			$this->telegram->sendMessage($chatId, 'Не удалось сохранить заказ. Попробуйте ещё раз или позовите администратора.');
		}
	}
	private function session(int $chatId): array
	{
		$statement = $this->pdo->prepare('SELECT * FROM telegram_sessions WHERE chat_id = ?');
		$statement->execute([$chatId]);
		return $statement->fetch() ?: ['state' => 'idle', 'draft_data' => '{}', 'human_mode' => 0];
	}
	private function saveSession(int $chatId, string $state, array $data, bool $human = false): void
	{
		$statement = $this->pdo->prepare('INSERT INTO telegram_sessions (chat_id, state, draft_data, human_mode)
			VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE state = VALUES(state), draft_data = VALUES(draft_data), human_mode = VALUES(human_mode)');
		$statement->execute([$chatId, $state, json_encode($data, JSON_UNESCAPED_UNICODE), $human ? 1 : 0]);
	}
	private function summary(array $data): string
	{
		return '<b>Проверьте данные заказа</b>\nУслуга: ' . $this->esc((string) $data['service_id']) . '\nТариф ID: ' . $this->esc((string) $data['tariff_id']) . '\nОписание: ' . $this->esc($data['comment']) . '\nИмя: ' . $this->esc($data['name']) . '\nТелефон: ' . $this->esc($data['phone']);
	}
	private function containsAny(string $text, array $needles): bool
	{
		foreach ($needles as $needle) if (str_contains($text, $needle)) return true;
		return false;
	}
	private function isAdmin(int $chatId): bool
	{
		return in_array((string) $chatId, $this->telegram->adminChatIds(), true);
	}
	private function esc(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}
