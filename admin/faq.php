<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../bd.php';
require_once __DIR__ . '/../services/ChatService.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $question = is_string($_POST['question'] ?? null) ? trim($_POST['question']) : '';
    $answer = is_string($_POST['answer'] ?? null) ? trim($_POST['answer']) : '';
    $keywords = is_string($_POST['keywords'] ?? null) ? trim($_POST['keywords']) : '';
    $active = isset($_POST['active']) ? 1 : 0;
    if (!appCsrfValid('admin_csrf', is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        $error = 'Обновите страницу и повторите действие.';
    } elseif ($id === false || $question === '' || $answer === '' || mb_strlen($question) > 255 || mb_strlen($answer) > 2000 || mb_strlen($keywords) > 255) {
        $error = 'Проверьте заполнение и длину полей.';
    } else {
        if ($id) {
            $pdo->prepare('UPDATE faq SET question = ?, answer = ?, keywords = ?, active = ? WHERE id = ?')->execute([$question, $answer, $keywords, $active, $id]);
        } else {
            $pdo->prepare('INSERT INTO faq (question, answer, keywords, active) VALUES (?, ?, ?, ?)')->execute([$question, $answer, $keywords, $active]);
        }
        header('Location: faq.php', true, 303);
        exit;
    }
}
$csrf = appCsrfToken('admin_csrf');
$rows = $pdo->query('SELECT * FROM faq ORDER BY id DESC LIMIT 100')->fetchAll();
array_unshift($rows, ['id' => 0, 'question' => '', 'answer' => '', 'keywords' => '', 'active' => 1]);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>База знаний | Vega Studio</title>
    <link rel="stylesheet" href="../styles.css?v=<?= filemtime(__DIR__ . '/../styles.css') ?>">
    <link rel="stylesheet" href="/admin/admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
</head>

<body>
    <main class="admin-shell">
        <?php require __DIR__ . '/navigation.php'; ?>
        <section class="admin-content">
            <h1>База знаний</h1>
            <p>Добавляйте готовые ответы на частые вопросы. Ассистент использует включённые записи при совпадении с вопросом клиента.</p>
            <?php if ($error): ?><p role="alert"><?= appEscape($error) ?></p><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <form method="post" class="admin-edit-card">
                    <h2><?= $row['id'] ? 'Вопрос #' . (int) $row['id'] : 'Новый вопрос' ?></h2>
                    <input type="hidden" name="csrf_token" value="<?= appEscape($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <label>Вопрос <input name="question" maxlength="255" value="<?= appEscape($row['question']) ?>" required></label>
                    <label>Ответ <textarea name="answer" maxlength="2000" rows="4" required><?= appEscape($row['answer']) ?></textarea></label>
                    <label>Ключевые слова <input name="keywords" placeholder="Например: сроки, разработка" maxlength="255" value="<?= appEscape($row['keywords']) ?>"></label>
                    <label class="admin-check"><input type="checkbox" name="active" value="1" <?= $row['active'] ? 'checked' : '' ?>> Использовать в ответах ассистента</label>
                    <button type="submit"><?= $row['id'] ? 'Сохранить' : 'Добавить' ?></button>
                </form>
            <?php endforeach; ?>
        </section>
    </main>
</body>

</html>
