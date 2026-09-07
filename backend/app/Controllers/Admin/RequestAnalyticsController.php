<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Helpers\JWT;
use App\Helpers\Response;

class RequestAnalyticsController
{
    public function index(): void
    {
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to'] ?? null;

        [$where, $params] = $this->buildWhere($dateFrom, $dateTo);

        $monthlyTotals = Database::select(
            "SELECT DATE_FORMAT(dr.created_at, '%Y-%m') AS month,
                    COUNT(*) AS total_requests
             FROM document_requests dr
             {$where}
             GROUP BY month
             ORDER BY month ASC",
            $params
        );

        $documentSourceSql = $this->documentSourceSql();

        $byDocumentAndMonth = Database::select(
            "SELECT DATE_FORMAT(dr.created_at, '%Y-%m') AS month,
                    COALESCE(dt.name, 'Unknown') AS document_type,
                    COUNT(*) AS request_count
             FROM document_requests dr
             LEFT JOIN ({$documentSourceSql}) request_docs ON request_docs.request_id = dr.id
             LEFT JOIN document_types dt ON dt.id = request_docs.document_type_id
             {$where}
             GROUP BY month, dt.id, dt.name
             ORDER BY month ASC, request_count DESC",
            $params
        );

        $topDocuments = Database::select(
            "SELECT COALESCE(dt.name, 'Unknown') AS document_type,
                    COUNT(*) AS request_count
             FROM document_requests dr
             LEFT JOIN ({$documentSourceSql}) request_docs ON request_docs.request_id = dr.id
             LEFT JOIN document_types dt ON dt.id = request_docs.document_type_id
             {$where}
             GROUP BY dt.id, dt.name
             ORDER BY request_count DESC
             LIMIT 10",
            $params
        );

        Response::success('Request analytics retrieved.', [
            'monthly_totals' => $monthlyTotals,
            'monthly_document_trends' => $byDocumentAndMonth,
            'top_documents' => $topDocuments,
        ]);
    }

    private function buildWhere(?string $dateFrom, ?string $dateTo): array
    {
        $conditions = [];
        $params = [];

        $level = $this->levelFilter();
        if ($level) {
            $conditions[] = 'dr.level = ?';
            $params[] = $level;
        }

        if (is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $conditions[] = 'dr.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        if (is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $conditions[] = 'dr.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        return [$where, $params];
    }

    private function documentSourceSql(): string
    {
        return "SELECT request_id, document_type_id
                FROM document_request_items
                UNION ALL
                SELECT dr_fallback.id AS request_id, dr_fallback.document_type_id
                FROM document_requests dr_fallback
                WHERE dr_fallback.document_type_id IS NOT NULL
                  AND NOT EXISTS (
                    SELECT 1
                    FROM document_request_items dri_fallback
                    WHERE dri_fallback.request_id = dr_fallback.id
                  )";
    }

    private function levelFilter(): ?string
    {
        $user = JWT::getAuthUser();
        return match($user['role'] ?? '') {
            'college_registrar' => 'college',
            'senior_high_registrar' => 'senior_high',
            default => null,
        };
    }
}
