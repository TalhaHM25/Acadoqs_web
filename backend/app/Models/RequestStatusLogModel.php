<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class RequestStatusLogModel
{
    public static function log(int $requestId, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $notes = null): void
    {
        Database::insert(
            'INSERT INTO request_status_logs (request_id, from_status, to_status, notes, changed_by)
             VALUES (?, ?, ?, ?, ?)',
            [$requestId, $fromStatus, $toStatus, $notes, $changedBy]
        );
    }

    public static function forRequest(int $requestId): array
    {
        return Database::select(
            "SELECT rsl.*, COALESCE(u.email, 'System') AS changed_by_email
             FROM request_status_logs rsl
             LEFT JOIN users u ON u.id = rsl.changed_by
             WHERE rsl.request_id = ?
             ORDER BY rsl.created_at ASC",
            [$requestId]
        );
    }
}
