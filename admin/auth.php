<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';
appStartSession();

function requireAdmin(): void
{
    if (!appAdminSessionActive()) {
        if (!headers_sent()) header('Cache-Control: no-store, private');
        header('Location: login.php');
        exit;
    }
    header('Cache-Control: no-store, private');
}
