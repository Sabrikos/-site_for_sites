<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(410);
    exit;
}
fwrite(STDOUT, "Telegram incoming worker is disabled.\n");
