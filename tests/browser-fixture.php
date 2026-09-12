<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../bd.php';
$data = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
if (($data['action'] ?? '') === 'create') {
    $username = 'browser_test_' . bin2hex(random_bytes(8));
    $password = bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    echo json_encode(['username' => $username, 'password' => $password]);
} elseif (($data['action'] ?? '') === 'order') {
    $statement = $pdo->prepare('SELECT o.total, i.price, t.price AS current_price FROM orders o JOIN order_items i ON i.order_id = o.id JOIN tariffs t ON t.id = i.tariff_id WHERE o.project_comment = ?');
    $statement->execute([$data['marker']]);
    echo json_encode($statement->fetchAll());
} elseif (($data['action'] ?? '') === 'cleanup' && preg_match('/^browser_test_[a-f0-9]{16}$/', $data['username'] ?? '')) {
    foreach ($data['conversation_ids'] ?? [] as $id) {
        $pdo->prepare('DELETE FROM chat_conversations WHERE id = ?')->execute([(int) $id]);
    }
    $pdo->prepare('DELETE FROM orders WHERE project_comment = ?')->execute([$data['username']]);
    $pdo->prepare('DELETE FROM admins WHERE username = ?')->execute([$data['username']]);
    echo '{}';
}
