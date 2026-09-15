<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/auth.php';

requireAdmin();

$metrics = [
    'new_orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn(),
    'processing_orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn(),
    'waiting_chats' => 0,
    'today_orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURRENT_DATE')->fetchColumn(),
];

try {
    $metrics['waiting_chats'] = (int) $pdo->query("SELECT COUNT(*) FROM chat_conversations WHERE status = 'waiting_human'")->fetchColumn();
} catch (Throwable $error) {
    appLog('Dashboard chat metric skipped', ['error' => $error->getMessage()]);
}

?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Vega Studio</title>
    <link rel="stylesheet" href="../styles.css">
</head>

<body>
    <main class="admin-shell">
        <aside class="admin-sidebar">
            <a href="index.php">Dashboard</a>
            <a href="orders.php">Заказы</a>
            <a href="chats.php">Чаты</a>
            <a href="faq.php">База знаний</a>
            <a href="services.php">Услуги</a>
            <a href="tariffs.php">Тарифы</a>
            <a href="logout.php">Выход</a>
        </aside>
        <section class="admin-content">
            <h1>Dashboard</h1>
            <div class="admin-dashboard-grid">
                <article class="admin-metric"><span>Новых заказов</span><strong><?= $metrics['new_orders'] ?></strong></article>
                <article class="admin-metric"><span>В работе</span><strong><?= $metrics['processing_orders'] ?></strong></article>
                <article class="admin-metric"><span>Диалогов ждут оператора</span><strong><?= $metrics['waiting_chats'] ?></strong></article>
                <article class="admin-metric"><span>Заказов сегодня</span><strong><?= $metrics['today_orders'] ?></strong></article>
            </div>
        </section>
    </main>
</body>

</html>
