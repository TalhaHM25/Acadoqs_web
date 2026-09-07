<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class RequestAttachmentModel
{
    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO request_attachments
             (request_id, label, file_path, file_name, file_size, mime_type, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['request_id'],
                $data['label'],
                $data['file_path'],
                $data['file_name'],
                $data['file_size'],
                $data['mime_type'],
                $data['uploaded_by'],
            ]
        );
    }

    public static function forRequest(int $requestId): array
    {
        return Database::select(
            'SELECT id, request_id, label, file_path, file_name, file_size, mime_type, uploaded_by, created_at
             FROM request_attachments
             WHERE request_id = ?
             ORDER BY created_at ASC',
            [$requestId]
        );
    }
}
