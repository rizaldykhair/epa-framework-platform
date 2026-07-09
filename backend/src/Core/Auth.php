<?php

namespace App\Core;

class Auth
{
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function issueToken(array $claims): string
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $claims['iat'] = time();
        $claims['exp'] = time() + config('jwt.ttl');
        $payload = self::b64(json_encode($claims));
        $signature = self::sign("$header.$payload");
        return "$header.$payload.$signature";
    }

    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        if (!hash_equals(self::sign("$header.$payload"), $signature)) {
            return null;
        }
        $claims = json_decode(self::b64Decode($payload), true);
        if (!$claims || ($claims['exp'] ?? 0) < time()) {
            return null;
        }
        return $claims;
    }

    private static function sign(string $data): string
    {
        return self::b64(hash_hmac('sha256', $data, config('jwt.secret'), true));
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64Decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
