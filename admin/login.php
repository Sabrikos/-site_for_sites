<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: orders.php');
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$username = trim((string) ($_POST['username'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!appRateLimit('admin_login', 10, 300)) {
        $error = 'Слишком много попыток входа. Попробуйте чуть позже.';
    }

    $token = (string) ($_POST['csrf_token'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($error === '' && !hash_equals($_SESSION['login_csrf'], $token)) {
        $error = 'Срок действия формы истёк. Обновите страницу и попробуйте снова.';
    } elseif ($error === '') {
        $statement = $pdo->prepare(
            'SELECT id, username, password_hash
             FROM admins
             WHERE username = ?
             LIMIT 1'
        );
        $statement->execute([$username]);
        $admin = $statement->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_csrf']);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            header('Location: orders.php');
            exit;
        }

        $error = 'Неверный логин или пароль.';
    }

    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

function adminEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админ-панель</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
<main class="cart-page">
    <div class="cart-summary contact-form-wrapper">
        <h1>Админ-панель</h1>

        <?php if ($error !== ''): ?>
            <div class="errors"><p><?= adminEscape($error) ?></p></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= adminEscape($_SESSION['login_csrf']) ?>">
            <div class="form-group">
                <label for="username">Логин</label>
                <input id="username" name="username" value="<?= adminEscape($username) ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="contact-submit" type="submit">Войти</button>
        </form>
    </div>
</main>
</body>
</html>