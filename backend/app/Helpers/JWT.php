<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Env;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    private static string $algorithm = 'HS256';

    private static function secret(): string
    {
        return Env::require('JWT_SECRET');
    }

    private static function expiry(): int
    {
        return (int) Env::get('JWT_EXPIRY', 86400); // 24 hours default
    }

    public static function encode(array $payload): string
    {
        $now = time();

        $payload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + self::expiry(),
        ]);

        return FirebaseJWT::encode($payload, self::secret(), self::$algorithm);
    }

    /**
     * Decode a JWT token. Returns payload array or null if invalid.
     */
    public static function decode(string $token): ?array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::secret(), self::$algorithm));
            return (array) $decoded;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getAuthUser(): ?array
    {
        return $GLOBALS['auth_user'] ?? null;
    }
}
