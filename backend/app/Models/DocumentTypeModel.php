<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class DocumentTypeModel
{
    public static function allActive(?string $level = null): array
    {
        $levelClause = '';
        $params      = [];

        if ($level && in_array($level, ['college','senior_high'], true)) {
            $levelClause = "AND (dt.level = ? OR dt.level = 'all')";
            $params[]    = $level;
        }

        return Database::select(
            "SELECT dt.*, dc.name AS category_name, dc.slug AS category_slug
             FROM document_types dt
             JOIN document_categories dc ON dc.id = dt.category_id
             WHERE dt.is_active = 1 {$levelClause}
             ORDER BY dc.sort_order, dt.sort_order, dt.name",
            $params
        );
    }

    public static function categoriesWithTypes(?string $level = null): array
    {
        $levelClause = '';
        $params      = [];

        if ($level && in_array($level, ['college','senior_high'], true)) {
            $levelClause = "AND (dc.level = ? OR dc.level = 'all')";
            $params[]    = $level;
        }

        $categories = Database::select(
            "SELECT * FROM document_categories WHERE is_active = 1 {$levelClause} ORDER BY sort_order",
            $params
        );

        $types = self::allActive($level);

        foreach ($categories as &$category) {
            $category['document_types'] = array_values(array_filter(
                $types,
                fn($t) => (int) $t['category_id'] === (int) $category['id']
            ));
        }

        return array_values(array_filter($categories, fn($c) => count($c['document_types']) > 0));
    }

    public static function findById(int $id): ?array
    {
        $row = Database::selectOne(
            'SELECT dt.*, dc.name AS category_name, dc.slug AS category_slug
             FROM document_types dt
             JOIN document_categories dc ON dc.id = dt.category_id
             WHERE dt.id = ? AND dt.is_active = 1
             LIMIT 1',
            [$id]
        );

        if ($row && $row['required_fields']) {
            $row['required_fields'] = json_decode($row['required_fields'], true) ?? [];
        } else {
            $row['required_fields'] = [];
        }

        return $row ?: null;
    }

    public static function findAll(): array
    {
        return Database::select(
            'SELECT dt.*, dc.name AS category_name
             FROM document_types dt
             JOIN document_categories dc ON dc.id = dt.category_id
             ORDER BY dc.sort_order, dt.sort_order, dt.name'
        );
    }

    public static function create(array $data): int
    {
        $level = in_array($data['level'] ?? '', ['college','senior_high','all'], true)
            ? $data['level'] : 'all';

        return Database::insert(
            'INSERT INTO document_types
             (category_id, name, slug, description, level, base_fee, certified_copy_fee,
              required_fields, processing_days, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['category_id'],
                $data['name'],
                $data['slug'],
                $data['description']        ?? null,
                $level,
                $data['base_fee'],
                $data['certified_copy_fee'] ?? 0,
                json_encode($data['required_fields'] ?? []),
                $data['processing_days']    ?? 3,
                $data['is_active']          ?? 1,
                $data['sort_order']         ?? 0,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        $level = in_array($data['level'] ?? '', ['college','senior_high','all'], true)
            ? $data['level'] : 'all';

        Database::execute(
            'UPDATE document_types SET
               category_id        = ?,
               name               = ?,
               slug               = ?,
               description        = ?,
               level              = ?,
               base_fee           = ?,
               certified_copy_fee = ?,
               required_fields    = ?,
               processing_days    = ?,
               is_active          = ?,
               sort_order         = ?,
               updated_at         = NOW()
             WHERE id             = ?',
            [
                $data['category_id'],
                $data['name'],
                $data['slug'],
                $data['description']        ?? null,
                $level,
                $data['base_fee'],
                $data['certified_copy_fee'] ?? 0,
                json_encode($data['required_fields'] ?? []),
                $data['processing_days']    ?? 3,
                $data['is_active']          ?? 1,
                $data['sort_order']         ?? 0,
                $id,
            ]
        );
    }

    public static function toggle(int $id): void
    {
        Database::execute(
            'UPDATE document_types SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?',
            [$id]
        );
    }
}
