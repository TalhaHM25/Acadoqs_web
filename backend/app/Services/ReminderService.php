<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\NotificationModel;

class ReminderService
{
    public function runDueReleaseReminders(): array
    {
        $rows = Database::select(
            "SELECT dr.id,
                    dr.request_number,
                    dr.user_id,
                    dr.req_phone,
                    DATE_ADD(
                        COALESCE((
                            SELECT rsl2.created_at
                            FROM request_status_logs rsl2
                            WHERE rsl2.request_id = dr.id
                              AND rsl2.to_status = 'payment_verified'
                            ORDER BY rsl2.created_at ASC, rsl2.id ASC
                            LIMIT 1
                        ), dr.created_at),
                        INTERVAL COALESCE((
                            SELECT MAX(dt2.processing_days)
                            FROM document_request_items dri2
                            JOIN document_types dt2 ON dt2.id = dri2.document_type_id
                            WHERE dri2.request_id = dr.id
                        ), COALESCE(dt.processing_days, 0)) DAY
                    ) AS expected_release_at
             FROM document_requests dr
             LEFT JOIN document_types dt ON dt.id = dr.document_type_id
             WHERE dr.reminder_sent_at IS NULL
               AND dr.status = 'payment_verified'
               AND DATE_ADD(
                    COALESCE((
                        SELECT rsl4.created_at
                        FROM request_status_logs rsl4
                        WHERE rsl4.request_id = dr.id
                          AND rsl4.to_status = 'payment_verified'
                        ORDER BY rsl4.created_at ASC, rsl4.id ASC
                        LIMIT 1
                    ), dr.created_at),
                    INTERVAL COALESCE((
                        SELECT MAX(dt3.processing_days)
                        FROM document_request_items dri3
                        JOIN document_types dt3 ON dt3.id = dri3.document_type_id
                        WHERE dri3.request_id = dr.id
                    ), COALESCE(dt.processing_days, 0)) DAY
               ) <= NOW()
             ORDER BY dr.created_at ASC"
        );

        $sent = 0;
        $failed = 0;

        foreach ($rows as $row) {
            Database::beginTransaction();
            try {
                $requestId = (int) $row['id'];
                $requestNumber = (string) $row['request_number'];
                $releaseDate = date('F j, Y', strtotime((string) $row['expected_release_at']));

                if (!empty($row['user_id'])) {
                    NotificationModel::create(
                        (int) $row['user_id'],
                        'request_release_reminder',
                        'Document Ready for Release',
                        "Your request {$requestNumber} has reached its processing period and is ready for release as of {$releaseDate}.",
                        ['request_id' => $requestId]
                    );
                }

                if (!empty($row['req_phone'])) {
                    SmsService::readyForRelease((string) $row['req_phone'], $requestNumber);
                }

                Database::execute(
                    'UPDATE document_requests SET reminder_sent_at = NOW(), updated_at = NOW() WHERE id = ?',
                    [$requestId]
                );

                Database::commit();
                $sent++;
            } catch (\Throwable $e) {
                Database::rollback();
                $failed++;
            }
        }

        return [
            'processed' => count($rows),
            'sent' => $sent,
            'failed' => $failed,
        ];
    }
}
