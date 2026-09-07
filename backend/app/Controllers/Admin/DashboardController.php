<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Helpers\JWT;
use App\Helpers\Response;

class DashboardController
{
    public function index(): void
    {
        $level = $this->levelFilter();
        [$where, $params] = $level
            ? ["WHERE level = ?", [$level]]
            : ["", []];
        [$drWhere, $drParams] = $level
            ? ["WHERE dr.level = ?", [$level]]
            : ["", []];
        $statusSql = "CASE
                        WHEN status IN ('paid', 'processing', 'ready_for_release') THEN 'payment_verified'
                        WHEN status IN ('draft', 'submitted', 'pending_payment', 'pending', 'cancelled') THEN 'rejected'
                        ELSE status
                      END";
        $drStatusSql = "CASE
                          WHEN dr.status IN ('paid', 'processing', 'ready_for_release') THEN 'payment_verified'
                          WHEN dr.status IN ('draft', 'submitted', 'pending_payment', 'pending', 'cancelled') THEN 'rejected'
                          ELSE dr.status
                        END";

        // Counts by status
        $statusRows = Database::select(
            "SELECT {$statusSql} AS status, COUNT(*) AS cnt FROM document_requests {$where} GROUP BY {$statusSql}",
            $params
        );

        $byStatus = [];
        foreach ($statusRows as $row) {
            $byStatus[$row['status']] = (int) $row['cnt'];
        }
        $readyToComplete = $byStatus['payment_verified'] ?? 0;

        $total = array_sum($byStatus);

        // Time-based counts
        $dateWhere  = $level ? "WHERE level = ? AND " : "WHERE ";
        $dateParams = $level ? [$level] : [];

        $today = (int) Database::selectOne(
            "SELECT COUNT(*) AS cnt FROM document_requests {$dateWhere}DATE(created_at) = CURDATE()",
            $dateParams
        )['cnt'];

        $thisWeek = (int) Database::selectOne(
            "SELECT COUNT(*) AS cnt FROM document_requests {$dateWhere}YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)",
            $dateParams
        )['cnt'];

        $thisMonth = (int) Database::selectOne(
            "SELECT COUNT(*) AS cnt FROM document_requests {$dateWhere}YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())",
            $dateParams
        )['cnt'];

        // Recent requests (last 10)
        $recentRows = Database::select(
            "SELECT dr.id, dr.request_number, {$drStatusSql} AS status, dr.total_fee, dr.created_at,
                    dr.req_first_name, dr.req_last_name, dr.level, dr.request_source,
                    COALESCE(dt.name, 'Walk-in Request') AS document_type_name
             FROM document_requests dr
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id
             {$drWhere}
             ORDER BY dr.created_at DESC
             LIMIT 10",
            $drParams
        );

        $upcomingReleases = Database::select(
            "SELECT dr.id,
                    dr.request_number,
                    {$drStatusSql} AS status,
                    dr.req_first_name,
                    dr.req_last_name,
                    COALESCE(dt.name, (
                        SELECT dt2.name
                        FROM document_request_items dri2
                        JOIN document_types dt2 ON dt2.id = dri2.document_type_id
                        WHERE dri2.request_id = dr.id
                        ORDER BY dri2.id ASC LIMIT 1
                    ), 'Document') AS document_type_name,
                    DATE_ADD(
                        dr.created_at,
                        INTERVAL COALESCE((
                            SELECT MAX(dt3.processing_days)
                            FROM document_request_items dri3
                            JOIN document_types dt3 ON dt3.id = dri3.document_type_id
                            WHERE dri3.request_id = dr.id
                        ), COALESCE(dt.processing_days, 0)) DAY
                    ) AS expected_release_at
             FROM document_requests dr
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id
             {$drWhere}
             " . ($drWhere ? "AND" : "WHERE") . " {$drStatusSql} = 'payment_verified'
             ORDER BY expected_release_at ASC, dr.created_at ASC
             LIMIT 6",
            $drParams
        );

        $summary = [
            'total_requests'    => $total,
            'payment_verified'  => $byStatus['payment_verified']  ?? 0,
            'completed'         => $byStatus['completed']         ?? 0,
            'rejected'          => $byStatus['rejected']          ?? 0,
        ];

        Response::success('Dashboard stats retrieved.', [
            'summary'                      => $summary,
            'by_status'                    => $byStatus,
            'total_requests'               => $total,
            'requests_today'               => $today,
            'requests_this_week'           => $thisWeek,
            'requests_this_month'          => $thisMonth,
            'ready_to_complete'            => $readyToComplete,
            'recent_requests'              => $recentRows,
            'upcoming_releases'            => $upcomingReleases,
        ]);
    }

    /**
     * Returns the level string ('college' or 'senior_high') for registrars,
     * or null for admin (sees everything).
     */
    private function levelFilter(): ?string
    {
        $user = JWT::getAuthUser();
        return match($user['role'] ?? '') {
            'college_registrar'      => 'college',
            'senior_high_registrar'  => 'senior_high',
            default                  => null,
        };
    }
}
