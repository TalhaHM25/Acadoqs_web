<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class NotificationModel
{
    public static function create(int $userId, string $type, string $title, string $message, ?array $data = null): int
    {
        return Database::insert(
            'INSERT INTO notifications (user_id, type, title, message, data)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $type, $title, $message, $data ? json_encode($data) : null]
        );
    }

    public static function listForUser(int $userId, int $limit = 30): array
    {
        return Database::select(
            'SELECT * FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public static function unreadCount(int $userId): int
    {
        $row = Database::selectOne(
            'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::execute(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        Database::execute(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
    }
}
