<?php

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

loadEnv(__DIR__ . '/../.env');

function config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = [
            'db.host' => getenv('DB_HOST') ?: '127.0.0.1',
            'db.port' => getenv('DB_PORT') ?: '3306',
            'db.name' => getenv('DB_NAME') ?: 'epa_framework',
            'db.user' => getenv('DB_USER') ?: 'root',
            'db.pass' => getenv('DB_PASS') ?: '',
            'jwt.secret' => getenv('JWT_SECRET') ?: 'change-this-secret-in-production',
            'jwt.ttl' => (int) (getenv('JWT_TTL') ?: 28800),
            'cors.origin' => getenv('CORS_ORIGIN') ?: '*',
        ];
    }
    return $config[$key] ?? $default;
}
