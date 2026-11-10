<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../bd.php';
$db = $argv[1] ?? '';
$token = $argv[2] ?? '';
if (!preg_match('/^vega_chat_test_[a-f0-9]{12}$/D', $db) || !preg_match('/^[a-f0-9]{64}$/D', $token)) throw new RuntimeException('Disposable test database and token required');
$pdo->exec("USE `$db`");
$statement = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE request_token = ?');
$statement->execute([$token]);
echo (int) $statement->fetchColumn();
