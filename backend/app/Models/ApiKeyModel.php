<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ApiKeyModel
{
    public static function findByKey(string $keyValue): ?array
    {
        return Database::selectOne(
            "SELECT * FROM api_keys WHERE key_value = ? AND is_active = 1 LIMIT 1",
            [$keyValue]
        );
    }

    public static function listAll(): array
    {
        return Database::select(
            "SELECT id, name, type, is_active, last_used_at, created_at,
                    CONCAT(SUBSTRING(key_value,1,8), '...') AS key_preview
             FROM api_keys ORDER BY created_at DESC",
            []
        );
    }

    public static function create(array $data): array
    {
        $keyValue = bin2hex(random_bytes(32)); // 64-char hex

        $id = Database::insert(
            "INSERT INTO api_keys (name, key_value, type, is_active) VALUES (?, ?, ?, 1)",
            [
                $data['name'],
                $keyValue,
                $data['type'],
            ]
        );

        return ['id' => $id, 'key_value' => $keyValue];
    }

    public static function toggle(int $id): void
    {
        Database::execute(
            "UPDATE api_keys SET is_active = IF(is_active=1,0,1), updated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM api_keys WHERE id = ?", [$id]);
    }

    public static function updateLastUsed(int $id): void
    {
        Database::execute(
            "UPDATE api_keys SET last_used_at = NOW() WHERE id = ?",
            [$id]
        );
    }
}
