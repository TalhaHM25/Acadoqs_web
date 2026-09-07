<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class QueueModel
{
    /**
     * Generate the next queue number for today.
     * Format: R-001 for registrar or C-001 for cashier, scoped per service area.
     */
    public static function nextNumber(string $level, string $serviceArea = 'registrar'): string
    {
        $prefix = $serviceArea === 'cashier' ? 'C' : 'R';

        $row = Database::selectOne(
            "SELECT COUNT(*) AS cnt FROM queue_numbers
             WHERE DATE(created_at) = CURDATE() AND service_area = ?",
            [$serviceArea]
        );

        $seq = (int) ($row['cnt'] ?? 0) + 1;
        return sprintf('%s-%03d', $prefix, $seq);
    }

    public static function create(array $data): int
    {
        $level  = $data['level'] ?? 'college';
        $serviceArea = in_array($data['service_area'] ?? '', ['registrar', 'cashier'], true)
            ? $data['service_area']
            : 'registrar';
        $number = self::nextNumber($level, $serviceArea);

        return Database::insert(
            "INSERT INTO queue_numbers (queue_number, type, service_area, level, status, request_id)
             VALUES (?, ?, ?, ?, 'waiting', ?)",
            [
                $number,
                $data['type']       ?? 'general',
                $serviceArea,
                $level,
                $data['request_id'] ?? null,
            ]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::selectOne(
            "SELECT q.*, cw.name AS window_name
             FROM queue_numbers q
             LEFT JOIN cashier_windows cw ON cw.id = q.window_id
             WHERE q.id = ? LIMIT 1",
            [$id]
        );
    }

    public static function listToday(string $level = 'all', string $serviceArea = 'registrar'): array
    {
        $areaSql = ' AND q.service_area = ?';
        $areaParams = [$serviceArea];

        if ($level === 'all') {
            return Database::select(
                "SELECT q.*, cw.name AS window_name
                 FROM queue_numbers q
                 LEFT JOIN cashier_windows cw ON cw.id = q.window_id
                 WHERE DATE(q.created_at) = CURDATE(){$areaSql}
                 ORDER BY q.created_at ASC",
                $areaParams
            );
        }
        return Database::select(
            "SELECT q.*, cw.name AS window_name
             FROM queue_numbers q
             LEFT JOIN cashier_windows cw ON cw.id = q.window_id
             WHERE DATE(q.created_at) = CURDATE() AND q.level = ?{$areaSql}
             ORDER BY q.created_at ASC",
            array_merge([$level], $areaParams)
        );
    }

    public static function currentlyServing(string $level = 'all', string $serviceArea = 'registrar'): array
    {
        $areaSql = ' AND q.service_area = ?';
        $areaParams = [$serviceArea];

        if ($level === 'all') {
            return Database::select(
                "SELECT q.*, cw.name AS window_name
                 FROM queue_numbers q
                 LEFT JOIN cashier_windows cw ON cw.id = q.window_id
                 WHERE q.status = 'serving' AND DATE(q.created_at) = CURDATE(){$areaSql}
                 ORDER BY q.called_at ASC",
                $areaParams
            );
        }
        return Database::select(
            "SELECT q.*, cw.name AS window_name
             FROM queue_numbers q
             LEFT JOIN cashier_windows cw ON cw.id = q.window_id
             WHERE q.status = 'serving' AND q.level = ? AND DATE(q.created_at) = CURDATE(){$areaSql}
             ORDER BY q.called_at ASC",
            array_merge([$level], $areaParams)
        );
    }

    public static function call(int $id): void
    {
        Database::execute(
            "UPDATE queue_numbers SET status = 'serving', called_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$id]
        );
    }

    public static function complete(int $id): void
    {
        Database::execute(
            "UPDATE queue_numbers SET status = 'completed', completed_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$id]
        );
    }

    public static function cancel(int $id): void
    {
        Database::execute(
            "UPDATE queue_numbers SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public static function dailySummary(string $level = 'all', string $serviceArea = 'registrar'): array
    {
        if ($level !== 'all') {
            return Database::select(
                "SELECT level,
                        COUNT(*) AS total,
                        SUM(status = 'waiting')   AS waiting,
                        SUM(status = 'serving')   AS serving,
                        SUM(status = 'completed') AS completed,
                        SUM(status = 'cancelled') AS cancelled
                 FROM queue_numbers WHERE DATE(created_at) = CURDATE() AND level = ? AND service_area = ?
                 GROUP BY level",
                [$level, $serviceArea]
            );
        }

        return Database::select(
            "SELECT level,
                    COUNT(*) AS total,
                    SUM(status = 'waiting')   AS waiting,
                    SUM(status = 'serving')   AS serving,
                    SUM(status = 'completed') AS completed,
                    SUM(status = 'cancelled') AS cancelled
             FROM queue_numbers WHERE DATE(created_at) = CURDATE() AND service_area = ?
             GROUP BY level",
            [$serviceArea]
        );
    }
}
