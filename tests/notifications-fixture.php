<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../bd.php';
$name = $argv[1] ?? '';
if (!preg_match('/^vega_chat_test_[a-f0-9]{12}$/D', $name)) throw new RuntimeException('Invalid test database');
$pdo->exec("USE `$name`");
$pdo->exec(file_get_contents(__DIR__ . '/../migrations/007_chat_admin_reads.sql'));
$hash = password_hash('DisposableTestPassword!', PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute(['test-notifications', $hash]);
echo "Test administrator created.
";
