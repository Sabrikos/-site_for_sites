<?php
declare(strict_types=1);
// Incoming Telegram updates are permanently disabled for this website.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Incoming Telegram updates are disabled.';
