<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/auth.php';

requireAdmin();
appCsrfToken('admin_csrf');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!appCsrfValid('admin_csrf', (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Срок действия формы истёк.';
    } else {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $deadline = trim((string) ($_POST['deadline'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($id === false || $name === '' || mb_strlen($name, 'UTF-8') > 100) {
            $error = 'Проверьте название услуги.';
        } else {
            $statement = $pdo->prepare('UPDATE services SET name = ?, description = ?, deadline = ?, active = ? WHERE id = ?');
            $statement->execute([$name, $description !== '' ? $description : null, $deadline !== '' ? $deadline : null, $active, (int) $id]);
            $message = 'Услуга обновлена.';
        }
        appRotateCsrf('admin_csrf');
    }
}

$rows = $pdo->query('SELECT id, name, description, deadline, active FROM services ORDER BY id')->fetchAll();

?>
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Услуги | WebStart Studio</title><link rel="stylesheet" href="../styles.css"></head>
<body>
<main class="admin-shell">
    <aside class="admin-sidebar"><a href="index.php">Dashboard</a><a href="orders.php">Заказы</a><a href="chats.php">Чаты</a><a href="services.php">Услуги</a><a href="tariffs.php">Тарифы</a><a href="logout.php">Выход</a></aside>
    <section class="admin-content">
        <h1>Услуги</h1>
        <?php if ($message !== ''): ?><div class="success"><?= appEscape($message) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="errors"><?= appEscape($error) ?></div><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <form method="post" class="admin-edit-card">
                <input type="hidden" name="csrf_token" value="<?= appEscape(appCsrfToken('admin_csrf')) ?>">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <label>Название <input name="name" maxlength="100" value="<?= appEscape($row['name']) ?>" required></label>
                <label>Описание <textarea name="description" maxlength="2000"><?= appEscape($row['description']) ?></textarea></label>
                <label>Срок <input name="deadline" maxlength="100" value="<?= appEscape($row['deadline']) ?>"></label>
                <label class="admin-check"><input type="checkbox" name="active" value="1" <?= (int) $row['active'] === 1 ? 'checked' : '' ?>> Активна</label>
                <button type="submit">Сохранить</button>
            </form>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
