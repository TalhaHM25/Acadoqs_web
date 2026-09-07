<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class DocumentRequestItemModel
{
    public static function createMany(int $requestId, array $items): void
    {
        foreach ($items as $item) {
            Database::insert(
                "INSERT INTO document_request_items
                 (request_id, document_type_id, copies, is_certified_copy, item_fee, special_data)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $requestId,
                    $item['document_type_id'],
                    $item['copies']            ?? 1,
                    $item['is_certified_copy'] ?? 0,
                    $item['item_fee']          ?? 0,
                    isset($item['special_data']) ? json_encode($item['special_data']) : null,
                ]
            );
        }
    }

    public static function forRequest(int $requestId): array
    {
        $rows = Database::select(
            "SELECT dri.*, dt.name AS document_type_name, dt.base_fee, dt.certified_copy_fee
             FROM document_request_items dri
             JOIN document_types dt ON dt.id = dri.document_type_id
             WHERE dri.request_id = ?
             ORDER BY dri.id ASC",
            [$requestId]
        );

        foreach ($rows as &$row) {
            if ($row['special_data']) {
                $row['special_data'] = json_decode($row['special_data'], true);
            }
        }

        return $rows;
    }

    public static function deleteForRequest(int $requestId): void
    {
        Database::execute("DELETE FROM document_request_items WHERE request_id = ?", [$requestId]);
    }
}
