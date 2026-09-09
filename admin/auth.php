<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
appStartSession();

function requireAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}
