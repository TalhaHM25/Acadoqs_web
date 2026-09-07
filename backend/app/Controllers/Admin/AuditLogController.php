<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Helpers\Response;

class AuditLogController
{
    public function index(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $requestNumber = trim((string) ($_GET['request_number'] ?? ''));
        $actor         = trim((string) ($_GET['actor'] ?? ''));
        $dateFrom      = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo        = trim((string) ($_GET['date_to'] ?? ''));

        $where = ['1 = 1'];
        $params = [];

        if ($requestNumber !== '') {
            $where[] = 'dr.request_number LIKE ?';
            $params[] = '%' . $requestNumber . '%';
        }

        if ($actor !== '') {
            $where[] = "COALESCE(u.email, 'System') LIKE ?";
            $params[] = '%' . $actor . '%';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $where[] = 'rsl.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $where[] = 'rsl.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $countRow = Database::selectOne(
            "SELECT COUNT(*) AS cnt
             FROM request_status_logs rsl
             JOIN document_requests dr ON dr.id = rsl.request_id
             LEFT JOIN users u ON u.id = rsl.changed_by
             {$whereClause}",
            $params
        );

        $rows = Database::select(
            "SELECT rsl.id,
                    rsl.request_id,
                    dr.request_number,
                    dr.req_first_name,
                    dr.req_last_name,
                    dr.req_student_id,
                    dr.req_email,
                    dr.rejection_reason,
                    dr.status AS current_status,
                    COALESCE(dt.name, (
                        SELECT GROUP_CONCAT(dt_items.name ORDER BY dt_items.name SEPARATOR ', ')
                        FROM document_request_items dri_items
                        JOIN document_types dt_items ON dt_items.id = dri_items.document_type_id
                        WHERE dri_items.request_id = dr.id
                    )) AS document_type,
                    rsl.from_status,
                    rsl.to_status,
                    rsl.notes,
                    COALESCE(u.email, 'System') AS actor_email,
                    rsl.created_at
             FROM request_status_logs rsl
             JOIN document_requests dr ON dr.id = rsl.request_id
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id
             LEFT JOIN users u ON u.id = rsl.changed_by
             {$whereClause}
             ORDER BY rsl.created_at DESC, rsl.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        Response::paginated(
            'Audit logs retrieved.',
            $rows,
            (int) ($countRow['cnt'] ?? 0),
            $perPage,
            $page
        );
    }
}
