<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class RegistrarPermissionModel
{
    /** All modules a registrar can potentially be granted */
    public const ALL_MODULES = [
        'requests',
        'queue',
        'cashier',
        'reports',
        'analytics',
        'notifications',
        'calendar',
        'document_types',
        'settings',
    ];

    public static function forUser(int $userId): array
    {
        $rows = Database::select(
            "SELECT module FROM registrar_permissions WHERE user_id = ?",
            [$userId]
        );
        return array_column($rows, 'module');
    }

    public static function hasModule(int $userId, string $module): bool
    {
        $row = Database::selectOne(
            "SELECT id FROM registrar_permissions WHERE user_id = ? AND module = ? LIMIT 1",
            [$userId, $module]
        );
        return $row !== null;
    }

    public static function setModules(int $userId, array $modules): void
    {
        Database::execute("DELETE FROM registrar_permissions WHERE user_id = ?", [$userId]);

        foreach ($modules as $module) {
            if (in_array($module, self::ALL_MODULES, true)) {
                Database::insert(
                    "INSERT IGNORE INTO registrar_permissions (user_id, module) VALUES (?, ?)",
                    [$userId, $module]
                );
            }
        }
    }

    public static function deleteForUser(int $userId): void
    {
        Database::execute("DELETE FROM registrar_permissions WHERE user_id = ?", [$userId]);
    }
}
