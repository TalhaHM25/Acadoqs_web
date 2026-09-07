<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class PaymentRecordModel
{
    public static function findByRequestId(int $requestId): ?array
    {
        return Database::selectOne(
            'SELECT pr.*, u.email AS verified_by_email
             FROM payment_records pr
             LEFT JOIN users u ON u.id = pr.verified_by
             WHERE pr.request_id = ?
             LIMIT 1',
            [$requestId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO payment_records
             (request_id, amount, payment_method, payment_method_id, reference_number, proof_path, proof_filename,
              paymongo_checkout_session_id, paymongo_checkout_url, paymongo_payment_intent_id, gateway_payload, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['request_id'],
                $data['amount'],
                $data['payment_method']    ?? 'paymongo',
                $data['payment_method_id'] ?? null,
                $data['reference_number']  ?? null,
                $data['proof_path']        ?? null,
                $data['proof_filename']    ?? null,
                $data['paymongo_checkout_session_id'] ?? null,
                $data['paymongo_checkout_url']        ?? null,
                $data['paymongo_payment_intent_id']   ?? null,
                isset($data['gateway_payload']) ? json_encode($data['gateway_payload']) : null,
                $data['submitted_at'] ?? null,
            ]
        );
    }

    public static function updatePaymongoSession(int $requestId, array $data): void
    {
        Database::execute(
            'UPDATE payment_records SET
               payment_method                = \'paymongo\',
               paymongo_checkout_session_id  = ?,
               paymongo_checkout_url         = ?,
               paymongo_payment_intent_id    = ?,
               paymongo_payment_id           = NULL,
               paymongo_refund_id            = NULL,
               refund_payload                = NULL,
               refunded_at                   = NULL,
               gateway_payload               = ?,
               status                        = \'pending\',
               rejection_reason              = NULL,
               submitted_at                  = NOW(),
               updated_at                    = NOW()
             WHERE request_id                = ?',
            [
                $data['paymongo_checkout_session_id'] ?? null,
                $data['paymongo_checkout_url']        ?? null,
                $data['paymongo_payment_intent_id']   ?? null,
                isset($data['gateway_payload']) ? json_encode($data['gateway_payload']) : null,
                $requestId,
            ]
        );
    }

    public static function findByPaymongoSessionId(string $sessionId): ?array
    {
        return Database::selectOne(
            'SELECT * FROM payment_records WHERE paymongo_checkout_session_id = ? LIMIT 1',
            [$sessionId]
        );
    }

    public static function updateProof(int $requestId, array $data): void
    {
        Database::execute(
            'UPDATE payment_records SET
               reference_number = ?,
               proof_path       = COALESCE(?, proof_path),
               proof_filename   = COALESCE(?, proof_filename),
               status           = \'pending\',
               rejection_reason = NULL,
               submitted_at     = NOW(),
               updated_at       = NOW()
             WHERE request_id   = ?',
            [
                $data['reference_number'] ?? null,
                $data['proof_path']       ?? null,
                $data['proof_filename']   ?? null,
                $requestId,
            ]
        );
    }

    public static function verify(int $requestId, int $verifiedBy): void
    {
        Database::execute(
            'UPDATE payment_records SET
               status      = \'verified\',
               verified_by = ?,
               verified_at = NOW(),
               updated_at  = NOW()
             WHERE request_id = ?',
            [$verifiedBy, $requestId]
        );
    }

    public static function markGatewayVerified(int $requestId, ?array $payload = null, ?string $paymentId = null): void
    {
        Database::execute(
            'UPDATE payment_records SET
               status          = \'verified\',
               verified_by     = NULL,
               verified_at     = NOW(),
               paymongo_payment_id = COALESCE(?, paymongo_payment_id),
               gateway_payload = COALESCE(?, gateway_payload),
               updated_at      = NOW()
             WHERE request_id  = ?',
            [
                $paymentId,
                $payload !== null ? json_encode($payload) : null,
                $requestId,
            ]
        );
    }

    public static function markRefunded(int $requestId, array $refund, string $reason): void
    {
        $refundId = $refund['data']['id'] ?? null;

        Database::execute(
            'UPDATE payment_records SET
               status           = \'rejected\',
               rejection_reason = ?,
               paymongo_refund_id = ?,
               refund_payload   = ?,
               refunded_at      = NOW(),
               updated_at       = NOW()
             WHERE request_id   = ?',
            [
                $reason,
                $refundId,
                json_encode($refund),
                $requestId,
            ]
        );
    }

    public static function reject(int $requestId, string $reason): void
    {
        Database::execute(
            'UPDATE payment_records SET
               status           = \'rejected\',
               rejection_reason = ?,
               updated_at       = NOW()
             WHERE request_id   = ?',
            [$reason, $requestId]
        );
    }
}
