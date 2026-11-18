<?php
declare(strict_types=1);

// Shared configuration: real process environment wins over a local .env file.
$envPath = dirname(__DIR__, 2) . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim(trim($value), "\"'");
    }
}
function appConfig(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value !== false ? $value : (string) ($_ENV[$key] ?? $default);
}
function authMode(): string
{
    $mode = appConfig('AUTH_MODE', 'firebase');
    if (!in_array($mode, ['legacy', 'firebase'], true)) throw new RuntimeException('Invalid AUTH_MODE.');
    return $mode;
}
function emailVerificationRequired(): bool
{
    return appConfig('FIREBASE_REQUIRE_VERIFIED_EMAIL', 'true') === 'true';
}
