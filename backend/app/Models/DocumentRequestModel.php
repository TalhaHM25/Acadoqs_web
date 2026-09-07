<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class DocumentRequestModel
{
    public const STATUSES = [
        'payment_verified', 'completed', 'rejected',
    ];

    public const CANCELLABLE_BY_STUDENT = [];

    // Admin status transitions
    public const ADMIN_TRANSITIONS = [
        'payment_verified' => ['completed', 'rejected'],
    ];

    private static function normalizedStatusSql(): string
    {
        return "CASE
                    WHEN dr.status IN ('paid', 'processing', 'ready_for_release') THEN 'payment_verified'
                    WHEN dr.status IN ('draft', 'submitted', 'pending_payment', 'pending', 'cancelled') THEN 'rejected'
                    ELSE dr.status
                END";
    }

    private static function expectedReleaseSql(): string
    {
        return "DATE_ADD(
                    COALESCE((
                        SELECT rsl_due.created_at
                        FROM request_status_logs rsl_due
                        WHERE rsl_due.request_id = dr.id
                          AND rsl_due.to_status = 'payment_verified'
                        ORDER BY rsl_due.created_at ASC, rsl_due.id ASC
                        LIMIT 1
                    ), dr.created_at),
                    INTERVAL COALESCE((
                        SELECT MAX(dt_due.processing_days)
                        FROM document_request_items dri_due
                        JOIN document_types dt_due ON dt_due.id = dri_due.document_type_id
                        WHERE dri_due.request_id = dr.id
                    ), COALESCE(dt.processing_days, 0)) DAY
                )";
    }

    public static function findById(int $id): ?array
    {
        return Database::selectOne(
            'SELECT dr.*,
                    ' . self::expectedReleaseSql() . ' AS expected_release_at,
                    COALESCE(dt.name, (
                        SELECT dt2.name
                        FROM document_request_items dri
                        JOIN document_types dt2 ON dt2.id = dri.document_type_id
                        WHERE dri.request_id = dr.id
                        ORDER BY dri.id ASC LIMIT 1
                    )) AS document_type_name,
                    dt.base_fee, dt.certified_copy_fee,
                    dc.name AS category_name,
                    u.email AS user_email
             FROM document_requests dr
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id
             LEFT JOIN document_categories dc ON dc.id = dt.category_id
             LEFT JOIN users u ON u.id = dr.user_id
             WHERE dr.id = ?
             LIMIT 1',
            [$id]
        );
    }

    public static function findByIdAndUser(int $id, int $userId): ?array
    {
        return Database::selectOne(
            'SELECT dr.*,
                    ' . self::expectedReleaseSql() . ' AS expected_release_at,
                    dt.name AS document_type_name,
                    dt.base_fee, dt.certified_copy_fee,
                    dc.name AS category_name
             FROM document_requests dr
             JOIN document_types dt ON dt.id = dr.document_type_id
             JOIN document_categories dc ON dc.id = dt.category_id
             WHERE dr.id = ? AND dr.user_id = ?
             LIMIT 1',
            [$id, $userId]
        );
    }

    public static function listByUser(int $userId, array $filters = []): array
    {
        $where  = ['dr.user_id = ?'];
        $params = [$userId];

        if (!empty($filters['status'])) {
            $where[]  = self::normalizedStatusSql() . ' = ?';
            $params[] = $filters['status'];
        }

        $sql = 'SELECT dr.id, dr.request_number, ' . self::normalizedStatusSql() . ' AS status, dr.total_fee,
                       dr.req_first_name, dr.req_last_name, dr.created_at,
                       ' . self::expectedReleaseSql() . ' AS expected_release_at,
                       dt.name AS document_type_name, dc.name AS category_name
                FROM document_requests dr
                JOIN document_types dt ON dt.id = dr.document_type_id
                JOIN document_categories dc ON dc.id = dt.category_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY dr.created_at DESC';

        return Database::select($sql, $params);
    }

    public static function listAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = self::normalizedStatusSql() . ' = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['document_type_id'])) {
            $where[]  = 'dr.document_type_id = ?';
            $params[] = $filters['document_type_id'];
        }

        if (!empty($filters['source'])) {
            $where[]  = 'dr.request_source = ?';
            $params[] = $filters['source'];
        }

        if (!empty($filters['date_from'])) {
            $where[]  = 'dr.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[]  = 'dr.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[]  = '(dr.request_number LIKE ? OR dr.req_last_name LIKE ? OR dr.req_first_name LIKE ? OR dr.req_student_id LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        if (!empty($filters['level'])) {
            $where[]  = 'dr.level = ?';
            $params[] = $filters['level'];
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = (int) Database::selectOne(
            "SELECT COUNT(*) AS cnt FROM document_requests dr {$whereClause}",
            $params
        )['cnt'];

        $offset = ($page - 1) * $perPage;
        $rows   = Database::select(
            "SELECT dr.id, dr.request_number, " . self::normalizedStatusSql() . " AS status, dr.total_fee, dr.created_at,
                    " . self::expectedReleaseSql() . " AS expected_release_at,
                    dr.req_first_name, dr.req_last_name, dr.req_student_id, dr.req_email,
                    dr.level, dr.request_source, dr.is_walkin,
                    (
                        SELECT u.email
                        FROM request_status_logs rsl
                        JOIN users u ON u.id = rsl.changed_by
                        WHERE rsl.request_id = dr.id AND rsl.to_status = 'rejected'
                        ORDER BY rsl.created_at DESC, rsl.id DESC
                        LIMIT 1
                    ) AS rejected_by_email,
                    (
                        SELECT rsl.created_at
                        FROM request_status_logs rsl
                        WHERE rsl.request_id = dr.id AND rsl.to_status = 'rejected'
                        ORDER BY rsl.created_at DESC, rsl.id DESC
                        LIMIT 1
                    ) AS rejected_at,
                    COALESCE(dt.name, (
                        SELECT dt2.name
                        FROM document_request_items dri
                        JOIN document_types dt2 ON dt2.id = dri.document_type_id
                        WHERE dri.request_id = dr.id
                        ORDER BY dri.id ASC LIMIT 1
                    )) AS document_type_name,
                    dc.name AS category_name
             FROM document_requests dr
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id
             LEFT JOIN document_categories dc ON dc.id = dt.category_id
             {$whereClause}
             ORDER BY dr.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return compact('rows', 'total');
    }

    public static function calendar(array $filters = []): array
    {
        $where  = [];
        $params = [];
        $dueSql = self::expectedReleaseSql();

        if (!empty($filters['date_from'])) {
            $where[]  = "{$dueSql} >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[]  = "{$dueSql} <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['level'])) {
            $where[]  = 'dr.level = ?';
            $params[] = $filters['level'];
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        return Database::select(
            "SELECT dr.id, dr.request_number, " . self::normalizedStatusSql() . " AS status, dr.total_fee, dr.created_at,
                    {$dueSql} AS expected_release_at,
                    dr.req_first_name, dr.req_last_name, dr.req_student_id, dr.req_email,
                    dr.level, dr.request_source, dr.is_walkin,
                    COALESCE(dt.name, (
                        SELECT dt2.name
                        FROM document_request_items dri
                        JOIN document_types dt2 ON dt2.id = dri.document_type_id
                        WHERE dri.request_id = dr.id
                        ORDER BY dri.id ASC LIMIT 1
                    )) AS document_type_name,
                    dc.name AS category_name
             FROM document_requests dr
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id
             LEFT JOIN document_categories dc ON dc.id = dt.category_id
             {$whereClause}
             ORDER BY expected_release_at ASC, dr.created_at ASC",
            $params
        );
    }

    public static function statusSummary(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['document_type_id'])) {
            $where[]  = 'dr.document_type_id = ?';
            $params[] = $filters['document_type_id'];
        }

        if (!empty($filters['source'])) {
            $where[]  = 'dr.request_source = ?';
            $params[] = $filters['source'];
        }

        if (!empty($filters['date_from'])) {
            $where[]  = 'dr.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[]  = 'dr.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[]  = '(dr.request_number LIKE ? OR dr.req_last_name LIKE ? OR dr.req_first_name LIKE ? OR dr.req_student_id LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$s, $s, $s, $s]);
        }

        if (!empty($filters['level'])) {
            $where[]  = 'dr.level = ?';
            $params[] = $filters['level'];
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $rows = Database::select(
            "SELECT " . self::normalizedStatusSql() . " AS status, COUNT(*) AS cnt
             FROM document_requests dr
             {$whereClause}
             GROUP BY " . self::normalizedStatusSql(),
            $params
        );

        $summary = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            if (isset($summary[$row['status']])) {
                $summary[$row['status']] = (int) $row['cnt'];
            }
        }

        return $summary;
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO document_requests (
               request_number, user_id, document_type_id, status, request_source,
               level,
               purpose, copies, is_certified_copy, total_fee,
               req_student_id, req_last_name, req_first_name, req_middle_name,
               req_program, grade_level, request_semester, req_email, req_phone,
               is_representative, rep_name, rep_relationship,
               gender, birthday, birthplace,
               has_name_change, original_name,
               is_graduate, graduation_date, last_semester, last_school_year
             ) VALUES (
               ?, ?, ?, ?, ?,
               ?,
               ?, ?, ?, ?,
               ?, ?, ?, ?,
               ?, ?, ?, ?, ?,
               ?, ?, ?,
               ?, ?, ?,
               ?, ?,
               ?, ?, ?, ?
             )',
            [
                $data['request_number'],
                $data['user_id'],
                $data['document_type_id'],
                $data['status']          ?? 'payment_verified',
                $data['request_source']  ?? 'online',
                $data['level']           ?? null,
                $data['purpose'],
                $data['copies']             ?? 1,
                $data['is_certified_copy']  ?? 0,
                $data['total_fee'],
                $data['req_student_id']     ?? null,
                $data['req_last_name'],
                $data['req_first_name'],
                $data['req_middle_name']    ?? null,
                $data['req_program']        ?? null,
                $data['grade_level']        ?? null,
                $data['request_semester']   ?? null,
                $data['req_email'],
                $data['req_phone']          ?? null,
                $data['is_representative']  ?? 0,
                $data['rep_name']           ?? null,
                $data['rep_relationship']   ?? null,
                $data['gender']             ?? null,
                $data['birthday']           ?? null,
                $data['birthplace']         ?? null,
                $data['has_name_change']    ?? null,
                $data['original_name']      ?? null,
                $data['is_graduate']        ?? null,
                $data['graduation_date']    ?? null,
                $data['last_semester']      ?? null,
                $data['last_school_year']   ?? null,
            ]
        );
    }

    public static function updateStatus(int $id, string $status, ?string $rejectionReason = null, ?int $processedBy = null): void
    {
        Database::execute(
            'UPDATE document_requests SET
               status           = ?,
               rejection_reason = ?,
               processed_by     = ?,
               processed_at     = IF(? IN (\'completed\',\'rejected\'), NOW(), processed_at),
               updated_at       = NOW()
             WHERE id           = ?',
            [$status, $rejectionReason, $processedBy, $status, $id]
        );
    }

    public static function nextRequestNumber(): string
    {
        $year = date('Y');
        $row  = Database::selectOne(
            'SELECT COUNT(*) AS cnt FROM document_requests WHERE YEAR(created_at) = ?',
            [$year]
        );
        $seq  = (int) $row['cnt'] + 1;
        return sprintf('REQ-%s-%05d', $year, $seq);
    }
}
