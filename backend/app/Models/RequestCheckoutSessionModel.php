<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

class RequestCheckoutSessionModel
{
    public static function create(array $data): int
    {
        try {
            return Database::insert(
                'INSERT INTO request_checkout_sessions
                 (token, user_id, request_payload, identity_proof_path, identity_proof_filename,
                  identity_proof_mime, identity_proof_size, amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['token'],
                    $data['user_id'],
                    json_encode($data['request_payload']),
                    $data['identity_proof_path'],
                    $data['identity_proof_filename'],
                    $data['identity_proof_mime'],
                    $data['identity_proof_size'],
                    $data['amount'],
                ]
            );
        } catch (PDOException $e) {
            self::throwMigrationHint($e);
            throw $e;
        }
    }

    public static function findByTokenForUser(string $token, int $userId): ?array
    {
        try {
            return Database::selectOne(
                'SELECT * FROM request_checkout_sessions WHERE token = ? AND user_id = ? LIMIT 1',
                [$token, $userId]
            );
        } catch (PDOException $e) {
            self::throwMigrationHint($e);
            throw $e;
        }
    }

    public static function updatePaymongoSession(string $token, array $data): void
    {
        try {
            Database::execute(
                'UPDATE request_checkout_sessions SET
                   paymongo_checkout_session_id = ?,
                   paymongo_checkout_url        = ?,
                   paymongo_payment_intent_id   = ?,
                   gateway_payload              = ?,
                   updated_at                   = NOW()
                 WHERE token = ?',
                [
                    $data['paymongo_checkout_session_id'] ?? null,
                    $data['paymongo_checkout_url'] ?? null,
                    $data['paymongo_payment_intent_id'] ?? null,
                    isset($data['gateway_payload']) ? json_encode($data['gateway_payload']) : null,
                    $token,
                ]
            );
        } catch (PDOException $e) {
            self::throwMigrationHint($e);
            throw $e;
        }
    }

    public static function markConverted(string $token, int $requestId, array $session, string $paymentId): void
    {
        try {
            Database::execute(
                'UPDATE request_checkout_sessions SET
                   status              = \'converted\',
                   document_request_id = ?,
                   paymongo_payment_id = COALESCE(?, paymongo_payment_id),
                   gateway_payload     = COALESCE(?, gateway_payload),
                   updated_at          = NOW()
                 WHERE token = ?',
                [
                    $requestId,
                    $paymentId !== '' ? $paymentId : null,
                    json_encode($session),
                    $token,
                ]
            );
        } catch (PDOException $e) {
            self::throwMigrationHint($e);
            throw $e;
        }
    }

    private static function throwMigrationHint(PDOException $e): void
    {
        if ($e->getCode() === '42S02' || str_contains($e->getMessage(), 'request_checkout_sessions')) {
            throw new \RuntimeException(
                'Payment-before-request setup is not installed yet. Run database migration 020_paid_request_checkout_flow.sql.',
                500
            );
        }
    }
}
