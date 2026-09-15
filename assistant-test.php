<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/services/AiAssistant.php';
if (!in_array('--live', $argv, true)) {
    echo "Use --live for one real AI request with a synthetic question and public catalog.\n";
    exit(2);
}
if (!appEnv('AI_API_KEY', appEnv('OPENAI_API_KEY'))) {
    echo "AI key is missing. Configure .env first. No request sent.\n";
    exit(2);
}
$reply = (new AiAssistant($pdo))->reply(0, 'Расскажи подробнее про Лендинг Бизнес и AI-ассистента.');
echo 'Mode: ' . $reply['mode'] . PHP_EOL . $reply['text'] . PHP_EOL;
exit($reply['mode'] === 'llm' ? 0 : 1);
