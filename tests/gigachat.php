<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../services/AiAssistant.php';
// No database, network or real credentials: the HTTP boundary is replaced.
putenv('AI_PROVIDER=gigachat');
putenv('GIGACHAT_AUTH_KEY=synthetic-test-key');
putenv('GIGACHAT_SCOPE=GIGACHAT_API_B2B');
putenv('GIGACHAT_MODEL=GigaChat-2-Max');
putenv('GIGACHAT_CHAT_ENABLED=0');
function verify(bool $value, string $label): void {
    if (!$value) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ' . $label . PHP_EOL;
}
verify(!AiAssistant::configured(), 'visitor traffic disabled despite credentials');
putenv('GIGACHAT_CHAT_ENABLED=1');
verify(AiAssistant::configured(), 'explicit enabled provider readiness');
putenv('GIGACHAT_AUTH_KEY=');
putenv('AI_API_KEY=synthetic-old-provider-key');
verify(!AiAssistant::configured(), 'old provider key never enables GigaChat');
putenv('GIGACHAT_AUTH_KEY=synthetic-test-key');
$pdo = new class extends PDO { public function __construct() {} };
$payload = null;
$assistant = new AiAssistant($pdo, function (array $request) use (&$payload): array {
    $payload = $request;
    return ['choices' => [['finish_reason' => 'stop', 'message' => ['content' =>
        '{"answer":"Помогу разобраться в услугах.","tariff_ids":[],"handoff":false}']]]];
});
$generate = new ReflectionMethod(AiAssistant::class, 'generate');
$catalog = [['id'=>2, 'service_id'=>1, 'slug'=>'landing', 'service_name'=>'Лендинг',
    'tariff_name'=>'Бизнес', 'description'=>'Дополнительные блоки и формы', 'price'=>22000, 'deadline'=>'5–7 дней']];
$generate->invoke($assistant, '', 'Лендинг Бизнес', [], $catalog, []);
verify($payload['model'] === 'GigaChat-2-Max', 'GigaChat model selected');
verify(count(array_filter($payload['messages'], fn($m) => $m['role'] === 'system')) === 1 &&
    $payload['messages'][0]['role'] === 'system', 'single first system message');
verify(str_contains($payload['messages'][0]['content'], 'CONTEXT:'), 'catalog context retained');
verify(!str_contains($payload['messages'][0]['content'], '22000') &&
    !str_contains($payload['messages'][0]['content'], '5–7 дней'), 'numeric catalog facts stay on server');
verify($payload['response_format']['type'] === 'json_schema' &&
    isset($payload['response_format']['schema']) && !isset($payload['response_format']['json_schema']), 'native schema format');
verify(!isset($payload['store']) && !isset($payload['tools']), 'no storage flag or model tools');
$schemaPayload = $payload;
$schemaPayload['response_format'] = ['type' => 'json_schema', 'json_schema' => ['schema' => $payload['response_format']['schema']]];
$calls = [];
$generations = 0;
$client = new GigaChatClient(function ($url, $headers, $body) use (&$calls, &$generations): array {
    $calls[] = $url;
    if (str_contains($url, '/oauth')) {
        verify(in_array('Authorization: Basic synthetic-test-key', $headers, true), 'Basic key used only for OAuth');
        verify((bool) preg_grep('/^RqUID: [0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $headers), 'OAuth UUIDv4');
        verify($body === 'scope=GIGACHAT_API_B2B', 'account scope passed explicitly');
        return [200, json_encode(['access_token' => 'synthetic-access', 'expires_at' => (time()+1800)*1000])];
    }
    verify($url === 'https://api.giga.chat/v1/chat/completions', 'direct official endpoint only');
    verify(in_array('Authorization: Bearer synthetic-access', $headers, true), 'access token used for inference');
    return ++$generations === 1 ? [401, '{}'] : [200, '{"choices":[]}'];
});
$client->complete($schemaPayload);
verify(count($calls) === 4, 'unauthorized response refreshes token once');
$client->complete($schemaPayload);
verify(count($calls) === 5, 'valid token reused within client');
$failing = new GigaChatClient(fn($url) => str_contains($url, '/oauth')
    ? [200, json_encode(['access_token' => 'synthetic-access', 'expires_at' => (time()+1800)*1000])]
    : [503, '{}']);
try { $failing->complete($schemaPayload); throw new LogicException('failure was accepted'); }
catch (RuntimeException $e) { verify($e->getMessage() === 'GigaChat generation unavailable', 'provider failure never calls GPT'); }
putenv('AI_PROVIDER=unknown-provider');
verify(!AiAssistant::configured(), 'unknown provider fails closed');
echo "Offline checks passed. No database or external service was contacted.\n";
