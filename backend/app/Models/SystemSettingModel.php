<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class SystemSettingModel
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = Database::selectOne(
            'SELECT value FROM system_settings WHERE `key` = ? LIMIT 1',
            [$key]
        );
        return $row ? $row['value'] : $default;
    }

    public static function all(): array
    {
        $rows = Database::select('SELECT `key`, value, type, description FROM system_settings ORDER BY `key`');
        $map  = [];
        foreach ($rows as $row) {
            $map[$row['key']] = $row;
        }
        return $map;
    }

    public static function set(string $key, string $value): void
    {
        Database::execute(
            'INSERT INTO system_settings (`key`, value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()',
            [$key, $value]
        );
    }

    public static function bulkSet(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            self::set($key, (string) $value);
        }
    }
}
