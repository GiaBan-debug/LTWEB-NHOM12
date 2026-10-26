<?php
declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message,
        public readonly array $errors = []
    ) {
        parent::__construct($message);
    }
}

function loadEnvironment(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function envValue(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = envValue('DB_HOST', '127.0.0.1');
    $port = envValue('DB_PORT', '3306');
    $name = envValue('DB_NAME', 'ltweb_nhom12');
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $connection = new PDO($dsn, envValue('DB_USER', 'root'), envValue('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function base64UrlDecode(string $value): ?string
{
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function createToken(array $claims): string
{
    $secret = envValue('JWT_SECRET');
    if (strlen($secret) < 32) {
        throw new RuntimeException('JWT_SECRET must contain at least 32 characters.');
    }
    $now = time();
    $payload = $claims + [
        'iat' => $now,
        'exp' => $now + max(60, (int) envValue('JWT_EXPIRE_SECONDS', '86400')),
    ];
    $header = base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
    $body = base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
    $signature = base64UrlEncode(hash_hmac('sha256', "$header.$body", $secret, true));
    return "$header.$body.$signature";
}

function decodeToken(string $token): ?array
{
    $secret = envValue('JWT_SECRET');
    $parts = explode('.', $token);
    if (strlen($secret) < 32 || count($parts) !== 3) {
        return null;
    }
    [$header, $payload, $signature] = $parts;
    $expected = base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));
    if (!hash_equals($expected, $signature)) {
        return null;
    }
    $headerJson = base64UrlDecode($header);
    $payloadJson = base64UrlDecode($payload);
    if ($headerJson === null || $payloadJson === null) {
        return null;
    }
    $decodedHeader = json_decode($headerJson, true);
    $decodedPayload = json_decode($payloadJson, true);
    if (!is_array($decodedHeader) || ($decodedHeader['alg'] ?? null) !== 'HS256' ||
        !is_array($decodedPayload) || !isset($decodedPayload['sub'], $decodedPayload['exp']) ||
        (int) $decodedPayload['exp'] <= time()) {
        return null;
    }
    return $decodedPayload;
}

function sendJson(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function fail(int $statusCode, string $message, array $errors = []): never
{
    throw new ApiException($statusCode, $message, $errors);
}

function requestBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        fail(400, 'Request body must contain valid JSON.');
    }
    if (!is_array($data) || (array_is_list($data) && trim($raw) !== '{}')) {
        fail(422, 'Request body must be a JSON object.');
    }
    return $data;
}

function validate(bool $condition, string $field, string $message): void
{
    if (!$condition) {
        fail(422, 'Dữ liệu gửi lên không hợp lệ', [['field' => $field, 'message' => $message]]);
    }
}

function requiredString(array $data, string $key, int $min, int $max): string
{
    $value = $data[$key] ?? null;
    validate(is_string($value) && mb_strlen(trim($value)) >= $min && mb_strlen(trim($value)) <= $max, $key, "Độ dài phải từ $min đến $max ký tự.");
    return trim($value);
}

function requiredPassword(array $data, string $key, int $min, int $max): string
{
    $value = $data[$key] ?? null;
    validate(is_string($value) && mb_strlen($value) >= $min && mb_strlen($value) <= $max, $key, "Độ dài phải từ $min đến $max ký tự.");
    return $value;
}

function optionalString(array $data, string $key, int $max): ?string
{
    if (!array_key_exists($key, $data) || $data[$key] === null) {
        return null;
    }
    validate(is_string($data[$key]) && mb_strlen($data[$key]) <= $max, $key, "Tối đa $max ký tự.");
    return trim($data[$key]);
}

function positiveInt(mixed $value, string $field, int $min = 1): int
{
    $validType = is_int($value) || (is_string($value) && preg_match('/^\d+$/D', $value) === 1);
    validate($validType, $field, "Giá trị phải là số nguyên từ $min trở lên.");
    $parsed = (int) $value;
    validate($parsed >= $min, $field, "Giá trị phải là số nguyên từ $min trở lên.");
    return $parsed;
}

function publicUser(array $user): array
{
    unset($user['hashed_password']);
    $user['is_active'] = (bool) $user['is_active'];
    return $user;
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function bearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return null;
    }
    return trim($matches[1]);
}
