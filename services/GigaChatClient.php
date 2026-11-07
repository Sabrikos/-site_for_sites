<?php

declare(strict_types=1);

require_once __DIR__ . '/../app.php';

/** Direct GigaChat V1 transport. Never falls back to another provider. */
final class GigaChatClient
{
    private ?string $accessToken = null;
    private int $expiresAt = 0;

    public function __construct(private ?Closure $http = null) {}

    public static function configured(): bool
    {
        return (bool) appEnv('GIGACHAT_AUTH_KEY') && in_array(appEnv('GIGACHAT_SCOPE'),
            ['GIGACHAT_API_PERS', 'GIGACHAT_API_B2B', 'GIGACHAT_API_CORP'], true);
    }

    public static function prepare(array $payload): array
    {
        $system = [];
        $messages = [];
        foreach ($payload['messages'] as $message) {
            if ($message['role'] === 'system') {
                $system[] = $message['content'];
            } else {
                $messages[] = $message;
            }
        }
        if ($system !== []) {
            array_unshift($messages, ['role' => 'system', 'content' => implode("\n\n", $system)]);
        }
        $payload['messages'] = $messages;
        $schema = $payload['response_format']['json_schema']['schema'] ?? null;
        if (!is_array($schema)) {
            throw new RuntimeException('GigaChat requires an explicit reply schema');
        }
        // Existing assistant contract: numeric facts are rendered by the server.
        if (isset($schema['properties']['answer'])) {
            $schema['properties']['answer']['description'] = 'Только пояснение услуги или уточняющий вопрос. Не включай цены, сроки, числа или цифры: приложение отдельно выведет эти факты из каталога.';
        }
        $payload['response_format'] = ['type' => 'json_schema', 'schema' => $schema, 'strict' => true];
        unset($payload['store']);
        return $payload;
    }

    public function complete(array $payload): array
    {
        if (!self::configured()) {
            throw new RuntimeException('GigaChat credentials or scope missing');
        }
        $base = 'https://api.giga.chat/v1/chat/completions';
        $payload = self::prepare($payload);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = $this->token();
            [$status, $body] = $this->send($base,
                ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            if ($status === 401 && $attempt === 0) {
                $this->accessToken = null;
                continue;
            }
            if ($status !== 200) {
                throw new RuntimeException('GigaChat generation unavailable');
            }
            return json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        }
        throw new RuntimeException('GigaChat authentication failed');
    }

    private function token(): string
    {
        if ($this->accessToken !== null && $this->expiresAt > time() + 30) {
            return $this->accessToken;
        }
        $key = trim((string) appEnv('GIGACHAT_AUTH_KEY'));
        if (preg_match('/[\r\n]/', $key)) {
            throw new RuntimeException('Invalid GigaChat credential');
        }
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        [$status, $body] = $this->send('https://ngw.devices.sberbank.ru:9443/api/v2/oauth',
            ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json',
                'RqUID: ' . $uuid, 'Authorization: Basic ' . $key],
            http_build_query(['scope' => appEnv('GIGACHAT_SCOPE')]));
        if ($status !== 200) {
            throw new RuntimeException('GigaChat token unavailable');
        }
        $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        if (!is_string($data['access_token'] ?? null) || $data['access_token'] === '' ||
            preg_match('/[\r\n]/', $data['access_token']) || !is_numeric($data['expires_at'] ?? null)) {
            throw new RuntimeException('Invalid GigaChat token response');
        }
        $this->expiresAt = (int) floor((float) $data['expires_at'] / 1000);
        if ($this->expiresAt <= time() + 30) {
            throw new RuntimeException('GigaChat token expired');
        }
        return $this->accessToken = $data['access_token'];
    }

    /** Returns [HTTP status, response body]; never logs payloads or credentials. */
    private function send(string $url, array $headers, string $body): array
    {
        if ($this->http !== null) {
            return ($this->http)($url, $headers, $body);
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension required');
        }
        $handle = curl_init($url);
        $response = '';
        $options = [CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => str_contains($url, '/oauth') ? 15 : 30,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 65536) return 0;
                $response .= $chunk;
                return strlen($chunk);
            }];
        $ca = appEnv('GIGACHAT_CA_BUNDLE');
        if ($ca) $options[CURLOPT_CAINFO] = $ca;
        curl_setopt_array($handle, $options);
        try {
            if (curl_exec($handle) === false) {
                throw new RuntimeException('GigaChat connection failed', curl_errno($handle));
            }
            return [(int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $response];
        } finally {
            curl_close($handle);
        }
    }
}
