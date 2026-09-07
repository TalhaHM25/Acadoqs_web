<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class CashierWindowModel
{
    public static function findById(int $id): ?array
    {
        return Database::selectOne(
            "SELECT * FROM cashier_windows WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    public static function list(string $level = 'all', bool $includeInactive = false, string $windowType = 'cashier'): array
    {
        $where = ['cw.window_type = ?'];
        $params = [$windowType];

        if ($windowType !== 'cashier' && $level !== 'all') {
            $where[] = 'cw.level = ?';
            $params[] = $level;
        }
        if (!$includeInactive) {
            $where[] = 'cw.is_active = 1';
        }

        $clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return Database::select(
            "SELECT cw.*,
                    q.id AS queue_id,
                    q.queue_number,
                    q.type AS queue_type,
                    q.called_at,
                    q.call_count
             FROM cashier_windows cw
             LEFT JOIN queue_numbers q
               ON q.window_id = cw.id
              AND q.status = 'serving'
              AND q.service_area = cw.window_type
              AND DATE(q.created_at) = CURDATE()
             {$clause}
             ORDER BY cw.sort_order ASC, cw.id ASC",
            $params
        );
    }

    public static function waitingCounts(string $level = 'all'): array
    {
        return self::waitingCountsForArea('cashier', $level);
    }

    public static function waitingCountsForArea(string $serviceArea = 'cashier', string $level = 'all'): array
    {
        $params = [];
        $levelSql = ' AND service_area = ?';
        $params[] = $serviceArea;
        if ($level !== 'all') {
            $levelSql .= ' AND level = ?';
            $params[] = $level;
        }

        return Database::select(
            "SELECT level, COUNT(*) AS waiting
             FROM queue_numbers
             WHERE status = 'waiting' AND DATE(created_at) = CURDATE(){$levelSql}
             GROUP BY level",
            $params
        );
    }

    public static function create(array $data): int
    {
        return Database::insert(
            "INSERT INTO cashier_windows (window_type, name, level, sort_order) VALUES (?, ?, ?, ?)",
            [$data['window_type'] ?? 'cashier', $data['name'], $data['level'] ?? 'all', $data['sort_order']]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            "UPDATE cashier_windows SET window_type = ?, name = ?, level = ?, sort_order = ? WHERE id = ?",
            [$data['window_type'] ?? 'cashier', $data['name'], $data['level'] ?? 'all', $data['sort_order'], $id]
        );
    }

    public static function toggle(int $id): void
    {
        Database::execute(
            "UPDATE cashier_windows SET is_active = NOT is_active WHERE id = ?",
            [$id]
        );
    }

    public static function callNext(int $windowId, int $userId): ?array
    {
        Database::beginTransaction();

        try {
            $window = Database::selectOne(
                "SELECT * FROM cashier_windows WHERE id = ? FOR UPDATE",
                [$windowId]
            );
            if (!$window || !(bool) $window['is_active']) {
                throw new \RuntimeException('Cashier window is unavailable.', 404);
            }

            $current = Database::selectOne(
                "SELECT id FROM queue_numbers
                 WHERE window_id = ? AND status = 'serving' AND DATE(created_at) = CURDATE()
                 LIMIT 1 FOR UPDATE",
                [$windowId]
            );
            if ($current) {
                throw new \RuntimeException('Complete the current queue number before calling another.', 409);
            }

            if (($window['window_type'] ?? 'cashier') === 'cashier') {
                $queue = Database::selectOne(
                    "SELECT * FROM queue_numbers
                     WHERE status = 'waiting' AND DATE(created_at) = CURDATE()
                       AND service_area = 'cashier'
                     ORDER BY created_at ASC, id ASC
                     LIMIT 1 FOR UPDATE"
                );
            } else {
                $queue = Database::selectOne(
                    "SELECT * FROM queue_numbers
                     WHERE status = 'waiting' AND level = ? AND DATE(created_at) = CURDATE()
                       AND service_area = ?
                     ORDER BY created_at ASC, id ASC
                     LIMIT 1 FOR UPDATE",
                    [$window['level'], $window['window_type']]
                );
            }

            if (!$queue) {
                Database::commit();
                return null;
            }

            Database::execute(
                "UPDATE queue_numbers
                 SET status = 'serving', window_id = ?, called_by = ?, called_at = NOW(),
                     call_count = call_count + 1, updated_at = NOW()
                 WHERE id = ? AND status = 'waiting'",
                [$windowId, $userId, $queue['id']]
            );

            Database::commit();
            return self::servingAtWindow($windowId);
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    public static function recall(int $windowId): ?array
    {
        Database::execute(
            "UPDATE queue_numbers
             SET called_at = NOW(), call_count = call_count + 1, updated_at = NOW()
             WHERE window_id = ? AND status = 'serving' AND DATE(created_at) = CURDATE()",
            [$windowId]
        );

        return self::servingAtWindow($windowId);
    }

    public static function completeCurrent(int $windowId): ?array
    {
        $queue = self::servingAtWindow($windowId);
        if (!$queue) {
            return null;
        }

        Database::execute(
            "UPDATE queue_numbers
             SET status = 'completed', completed_at = NOW(), updated_at = NOW()
             WHERE id = ? AND status = 'serving'",
            [$queue['id']]
        );

        return $queue;
    }

    public static function servingAtWindow(int $windowId): ?array
    {
        return Database::selectOne(
            "SELECT q.*, cw.name AS window_name
             FROM queue_numbers q
             INNER JOIN cashier_windows cw ON cw.id = q.window_id
             WHERE q.window_id = ? AND q.status = 'serving' AND DATE(q.created_at) = CURDATE()
             LIMIT 1",
            [$windowId]
        );
    }
}
