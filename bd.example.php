<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

$dbHost = appEnv('DB_HOST', '127.0.0.1');
$dbPort = appEnv('DB_PORT', '3306');
$dbName = appEnv('DB_NAME', 'webstart');
$dbUser = appEnv('DB_USER', 'root');
$dbPassword = appEnv('DB_PASSWORD', '');

$dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
} catch (PDOException $error) {
    appLog('Database connection failed', ['error' => $error->getMessage()]);
    http_response_code(500);
    exit('Произошла ошибка. Попробуйте ещё раз.');
}