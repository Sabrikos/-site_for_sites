<?php
$section = basename($_SERVER['SCRIPT_NAME']);
if ($section === 'order.php') {
    $section = 'orders.php';
}
$sections = [
    'index.php' => 'Обзор',
    'orders.php' => 'Заказы',
    'chats.php' => 'Чаты',
    'faq.php' => 'База знаний',
    'services.php' => 'Услуги',
    'tariffs.php' => 'Тарифы',
];
?>
<nav class="admin-sidebar" aria-label="Разделы админки">
    <?php foreach ($sections as $url => $label): ?>
        <a href="/admin/<?= $url ?>"<?= $section === $url ? ' aria-current="page"' : '' ?>><?= $label ?></a>
    <?php endforeach; ?>
    <form method="post" action="/admin/logout.php" class="admin-logout-form">
        <input type="hidden" name="csrf_token" value="<?= appEscape(appCsrfToken('admin_csrf')) ?>">
        <button type="submit" class="admin-logout-button">Выход</button>
    </form>
</nav>
