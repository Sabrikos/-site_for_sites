<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';

function appEnsureAssistantTables(PDO $pdo): void
{
    appEnsureChatTables($pdo);
    $pdo->exec(file_get_contents(__DIR__ . '/../migrations/20260907_assistant.sql'));
}

function assistantLimit(PDO $pdo, string $scope, int $maximum, int $seconds): bool
{
    $window = intdiv(time(), $seconds);
    $bucket = hash('sha256', $scope . ':' . $window);
    $pdo->prepare('INSERT IGNORE INTO assistant_limits (bucket, expires_at) VALUES (?, ?)')
        ->execute([$bucket, date('Y-m-d H:i:s', ($window + 1) * $seconds)]);
    $statement = $pdo->prepare('UPDATE assistant_limits SET hits = hits + 1 WHERE bucket = ? AND hits < ?');
    $statement->execute([$bucket, $maximum]);
    return $statement->rowCount() === 1;
}

function assistantInt(string $key, int $default, int $minimum, int $maximum): int
{
    return max($minimum, min($maximum, (int) appEnv($key, (string) $default)));
}

final class ChatService
{
    public function __construct(private PDO $pdo) {}

    public function conversation(string $sessionKey): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM chat_conversations WHERE session_key = ? ORDER BY id DESC LIMIT 1');
        $statement->execute([$sessionKey]);
        $conversation = $statement->fetch();
        if (!$conversation) {
            $this->pdo->prepare('INSERT INTO chat_conversations (session_key) VALUES (?)')->execute([$sessionKey]);
            $conversation = ['id' => (int) $this->pdo->lastInsertId(), 'status' => 'bot'];
            $this->add((int) $conversation['id'], 'bot', 'Здравствуйте! Я виртуальный помощник WebStart Studio. Расскажите, какой проект вы планируете.');
        }
        $this->pdo->prepare('INSERT IGNORE INTO chat_assistant_state (conversation_id) VALUES (?)')->execute([$conversation['id']]);
        return $conversation;
    }

    public function add(int $id, string $sender, string $text): int
    {
        $this->pdo->prepare('INSERT INTO chat_messages (conversation_id, sender, message) VALUES (?, ?, ?)')->execute([$id, $sender, $text]);
        $messageId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE chat_conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
        return $messageId;
    }

    public function snapshot(int $id, int $afterId = 0): array
    {
        $statement = $this->pdo->prepare('SELECT c.status, s.handoff_offered,
            (s.generation_key IS NOT NULL AND s.generation_started_at > DATE_SUB(NOW(), INTERVAL 45 SECOND)) AS assistant_typing,
            EXISTS(SELECT 1 FROM chat_admin_presence p WHERE p.conversation_id = c.id AND p.last_activity > DATE_SUB(NOW(), INTERVAL 35 SECOND)) AS admin_online,
            EXISTS(SELECT 1 FROM chat_admin_presence p WHERE p.conversation_id = c.id AND p.typing_until > NOW()) AS admin_typing
            FROM chat_conversations c LEFT JOIN chat_assistant_state s ON s.conversation_id = c.id WHERE c.id = ?');
        $statement->execute([$id]);
        $state = $statement->fetch();
        $messages = $this->pdo->prepare('SELECT id, sender, message, created_at FROM chat_messages WHERE conversation_id = ? AND id > ? ORDER BY id LIMIT 100');
        $messages->execute([$id, $afterId]);
        return ['conversation_id' => $id, 'status' => $state['status'],
            'assistant_typing' => $state['status'] === 'bot' && (bool) $state['assistant_typing'],
            'admin_online' => (bool) $state['admin_online'],
            'admin_typing' => $state['status'] === 'human' && (bool) $state['admin_typing'],
            'messages' => $messages->fetchAll()];
    }

    public static function needsHuman(string $message, bool $offered): bool
    {
        $text = mb_strtolower(trim($message), 'UTF-8');
        if (preg_match('/\bне\s+(?:надо|нужно|хочу|зови|вызывай|подключай)|без\s+(?:человека|оператора|менеджера)/u', $text)) {
            return false;
        }
        if ($offered && preg_match('/^(?:да|давай|давайте|ага|ок|окей|хорошо|согласен|согласна|можно|конечно)[.!\s]*$/u', $text)) {
            return true;
        }
        return (bool) preg_match('/^(?:оператор|менеджер|администратор|живой человек)[.!\s]*$|(?:позов|позва|подключ|соедин|давай|поговор|общаться|нужен|нужна|хочу).{0,60}(?:человек|человеком|оператор|менеджер|администратор|специалист|консультант)/u', $text);
    }

    private function lock(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT c.status, s.handoff_offered, s.generation_key,
            (s.generation_started_at > DATE_SUB(NOW(), INTERVAL 45 SECOND)) AS pending
            FROM chat_conversations c JOIN chat_assistant_state s ON s.conversation_id = c.id WHERE c.id = ? FOR UPDATE');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) {
            throw new RuntimeException('Conversation missing');
        }
        return $row;
    }

    private function handoff(int $id): void
    {
        $this->pdo->prepare("UPDATE chat_conversations SET status = 'waiting_human' WHERE id = ?")->execute([$id]);
        $this->clearGeneration($id);
        $this->add($id, 'bot', 'Конечно. Я передал диалог администратору WebStart Studio. Как только он подключится, вы сможете продолжить разговор здесь.');
    }

    private function clearGeneration(int $id): void
    {
        $this->pdo->prepare('UPDATE chat_assistant_state SET generation_key = NULL, generation_started_at = NULL, handoff_offered = 0 WHERE conversation_id = ?')->execute([$id]);
    }

    public function beginMessage(int $id, string $text): array
    {
        $this->pdo->beginTransaction();
        try {
            $state = $this->lock($id);
            if ($state['status'] === 'closed') {
                throw new DomainException('Диалог завершён.', 409);
            }
            $human = $state['status'] === 'bot' && self::needsHuman($text, (bool) $state['handoff_offered']);
            if (!$human && $state['status'] === 'bot' && $state['generation_key'] && $state['pending']) {
                throw new DomainException('Дождитесь ответа на предыдущее сообщение.', 409);
            }
            $this->add($id, 'user', $text);
            $key = null;
            if ($human) {
                $this->handoff($id);
            } elseif ($state['status'] === 'bot') {
                $key = bin2hex(random_bytes(16));
                $this->pdo->prepare('UPDATE chat_assistant_state SET generation_key = ?, generation_started_at = NOW(), handoff_offered = 0 WHERE conversation_id = ?')->execute([$key, $id]);
            }
            $this->pdo->commit();
            return ['key' => $key, 'handoff' => $human];
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function finishMessage(int $id, string $key, array $reply): bool
    {
        // Never hold a database lock during an LLM request; reject stale replies here.
        $this->pdo->beginTransaction();
        try {
            $state = $this->lock($id);
            $handoff = false;
            if ($state['status'] === 'bot' && $state['generation_key'] === $key) {
                $handoff = (bool) ($reply['handoff'] ?? false);
                if ($handoff) {
                    $this->handoff($id);
                } else {
                    $this->add($id, 'bot', $reply['text']);
                    $this->clearGeneration($id);
                    $this->pdo->prepare('UPDATE chat_assistant_state SET handoff_offered = ? WHERE conversation_id = ?')->execute([(int) ($reply['offered'] ?? false), $id]);
                }
            }
            $this->pdo->commit();
            return $handoff;
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function adminAction(int $id, int $adminId, string $action, string $value = ''): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT IGNORE INTO chat_assistant_state (conversation_id) SELECT id FROM chat_conversations WHERE id = ?')->execute([$id]);
            $this->lock($id);
            if ($action === 'reply' || $action === 'status') {
                $status = $action === 'reply' ? 'human' : $value;
                if (!in_array($status, ['bot', 'waiting_human', 'human', 'closed'], true)) {
                    throw new DomainException('Некорректный статус.', 422);
                }
                if ($action === 'reply') {
                    if (trim($value) === '' || mb_strlen($value) > 4000) {
                        throw new DomainException('Ответ должен содержать от 1 до 4000 символов.', 422);
                    }
                    $this->add($id, 'admin', trim($value));
                }
                $this->pdo->prepare('UPDATE chat_conversations SET status = ? WHERE id = ?')->execute([$status, $id]);
                $this->clearGeneration($id);
                $this->pdo->prepare('UPDATE chat_admin_presence SET typing_until = NULL WHERE conversation_id = ?')->execute([$id]);
            }
            $typing = $action === 'typing' && $value === '1';
            $this->pdo->prepare('INSERT INTO chat_admin_presence (conversation_id, admin_id, last_activity, typing_until)
                VALUES (?, ?, NOW(), IF(? = 1, DATE_ADD(NOW(), INTERVAL 7 SECOND), NULL))
                ON DUPLICATE KEY UPDATE last_activity = NOW(), typing_until = VALUES(typing_until)')
                ->execute([$id, $adminId, (int) $typing]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }
}
