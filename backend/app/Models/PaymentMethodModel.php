<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class PaymentMethodModel
{
    public static function listActive(): array
    {
        return Database::select(
            "SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC",
            []
        );
    }

    public static function listAll(): array
    {
        return Database::select(
            "SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC",
            []
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::selectOne(
            "SELECT * FROM payment_methods WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert(
            "INSERT INTO payment_methods
             (name, type, account_name, account_number, instructions, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['type']           ?? 'other',
                $data['account_name']   ?? null,
                $data['account_number'] ?? null,
                $data['instructions']   ?? null,
                $data['is_active']      ?? 1,
                $data['sort_order']     ?? 0,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            "UPDATE payment_methods SET
               name           = ?,
               type           = ?,
               account_name   = ?,
               account_number = ?,
               instructions   = ?,
               sort_order     = ?,
               updated_at     = NOW()
             WHERE id = ?",
            [
                $data['name'],
                $data['type']           ?? 'other',
                $data['account_name']   ?? null,
                $data['account_number'] ?? null,
                $data['instructions']   ?? null,
                $data['sort_order']     ?? 0,
                $id,
            ]
        );
    }

    public static function updateQr(int $id, string $qrPath): void
    {
        Database::execute(
            "UPDATE payment_methods SET qr_path = ?, updated_at = NOW() WHERE id = ?",
            [$qrPath, $id]
        );
    }

    public static function toggle(int $id): void
    {
        Database::execute(
            "UPDATE payment_methods SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM payment_methods WHERE id = ?", [$id]);
    }
}
