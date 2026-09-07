<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Helpers\JWT;
use App\Helpers\Response;

class ReportController
{
    public function index(): void
    {
        $dateFrom  = $_GET['date_from'] ?? null;
        $dateTo    = $_GET['date_to']   ?? null;
        $groupBy   = $_GET['group_by']  ?? 'month';  // day | week | month
        $docTypeId = isset($_GET['document_type_id']) ? (int) $_GET['document_type_id'] : null;
        $status    = $_GET['status']    ?? null;

        $level = $this->levelFilter();
        [$where, $params] = $this->buildWhere($dateFrom, $dateTo, $docTypeId, $status, '', $level);

        // Summary
        $summary = Database::selectOne(
            "SELECT
               COUNT(*) AS total,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
               SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END) AS rejected,
               SUM(CASE WHEN status = 'completed' THEN total_fee ELSE 0 END) AS revenue
             FROM document_requests {$where}",
            $params
        );

        // By status
        $byStatus = Database::select(
            "SELECT status, COUNT(*) AS count,
                    SUM(CASE WHEN status = 'completed' THEN total_fee ELSE 0 END) AS revenue
             FROM document_requests {$where} GROUP BY status ORDER BY count DESC",
            $params
        );

        // By document type (JOIN query — needs dr. prefix to avoid ambiguity)
        [$whereJoined, $paramsJoined] = $this->buildWhere($dateFrom, $dateTo, $docTypeId, $status, 'dr.', $level);
        $byType = Database::select(
            "SELECT dt.name, COUNT(*) AS count,
                    SUM(CASE WHEN dr.status = 'completed' THEN dr.total_fee ELSE 0 END) AS revenue
             FROM document_requests dr
             JOIN document_types dt ON dt.id = dr.document_type_id
             {$whereJoined}
             GROUP BY dt.id, dt.name
             ORDER BY count DESC",
            $paramsJoined
        );

        // By period
        $periodFormat = match ($groupBy) {
            'day'   => '%Y-%m-%d',
            'week'  => '%Y-%u',
            default => '%Y-%m',
        };

        $byPeriod = Database::select(
            "SELECT
               DATE_FORMAT(created_at, '{$periodFormat}') AS period,
               COUNT(*) AS count,
               SUM(CASE WHEN status = 'completed' THEN total_fee ELSE 0 END) AS revenue
             FROM document_requests
             {$where}
             GROUP BY period
             ORDER BY period ASC",
            $params
        );

        Response::success('Report generated.', [
            'summary' => [
                'total'     => (int) ($summary['total']    ?? 0),
                'completed' => (int) ($summary['completed'] ?? 0),
                'rejected'  => (int) ($summary['rejected']  ?? 0),
                'revenue'   => (float) ($summary['revenue'] ?? 0),
            ],
            'by_status'      => $byStatus,
            'by_document_type' => $byType,
            'by_period'      => $byPeriod,
        ]);
    }

    public function export(): void
    {
        $dateFrom  = $_GET['date_from'] ?? null;
        $dateTo    = $_GET['date_to']   ?? null;
        $docTypeId = isset($_GET['document_type_id']) ? (int) $_GET['document_type_id'] : null;
        $status    = $_GET['status']    ?? null;

        $level = $this->levelFilter();
        [$where, $params] = $this->buildWhere($dateFrom, $dateTo, $docTypeId, $status, 'dr.', $level);

        $rows = Database::select(
            "SELECT dr.request_number, dr.req_last_name, dr.req_first_name,
                    dt.name AS document_type, dr.status, dr.total_fee,
                    DATE_FORMAT(dr.created_at, '%Y-%m-%d') AS date_submitted
             FROM document_requests dr
             JOIN document_types dt ON dt.id = dr.document_type_id
             {$where}
             ORDER BY dr.created_at DESC",
            $params
        );

        // Output CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Request #', 'Last Name', 'First Name', 'Document Type', 'Status', 'Fee', 'Date Submitted']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['request_number'],
                $row['req_last_name'],
                $row['req_first_name'],
                $row['document_type'],
                $row['status'],
                $row['total_fee'],
                $row['date_submitted'],
            ]);
        }

        fclose($out);
        exit;
    }

    private function buildWhere(?string $dateFrom, ?string $dateTo, ?int $docTypeId, ?string $status, string $prefix = '', ?string $level = null): array
    {
        $conditions = [];
        $params     = [];

        // Only apply date filters when the value is a real YYYY-MM-DD date,
        // not the literal string "undefined" or any other garbage value.
        if ($dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $conditions[] = $prefix . 'created_at >= ?';
            $params[]     = $dateFrom . ' 00:00:00';
        }
        if ($dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $conditions[] = $prefix . 'created_at <= ?';
            $params[]     = $dateTo . ' 23:59:59';
        }
        if ($docTypeId) {
            $conditions[] = $prefix . 'document_type_id = ?';
            $params[]     = $docTypeId;
        }
        if ($status) {
            $conditions[] = $prefix . 'status = ?';
            $params[]     = $status;
        }
        if ($level) {
            $conditions[] = $prefix . 'level = ?';
            $params[]     = $level;
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        return [$where, $params];
    }

    private function levelFilter(): ?string
    {
        $user = JWT::getAuthUser();
        return match($user['role'] ?? '') {
            'college_registrar'     => 'college',
            'senior_high_registrar' => 'senior_high',
            default                 => null,
        };
    }
}
