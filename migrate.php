<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bd.php';

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$files = glob(__DIR__ . '/migrations/[0-9][0-9][0-9]_*.sql') ?: [];
sort($files, SORT_STRING);
foreach ($files as $file) {
    $version = basename($file);
    $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
    $check->execute([$version]);
    if ($check->fetchColumn()) {
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read migration: $version");
    }
    try {
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if (preg_match('/^--\s*@if-missing-(column|index)\s+([A-Za-z0-9_]+)\s+([A-Za-z0-9_]+)\s*\R(.*)$/s', $statement, $match)) {
                [, $kind, $table, $name, $statement] = $match;
                $field = $kind === 'column' ? 'COLUMN_NAME' : 'INDEX_NAME';
                $check = $pdo->prepare("SELECT 1 FROM information_schema." . ($kind === 'column' ? 'COLUMNS' : 'STATISTICS')
                    . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND $field = ? LIMIT 1");
                $check->execute([$table, $name]);
                if ($check->fetchColumn()) {
                    continue;
                }
            }
            $pdo->exec($statement);
        }
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
        echo "Applied $version\n";
    } catch (Throwable $error) {
        throw $error;
    }
}
