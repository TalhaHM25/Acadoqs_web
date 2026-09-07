<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Database;
use App\Models\DocumentRequestModel;
use App\Models\PaymentRecordModel;
use App\Models\RequestStatusLogModel;
use App\Services\NotificationService;
use App\Services\SmsService;

class PaymentService
{
    /**
     * Get payment info for a request.
     */
    public function getPaymentInfo(int $requestId, int $userId): array
    {
        $request = DocumentRequestModel::findByIdAndUser($requestId, $userId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        $payment = PaymentRecordModel::findByRequestId($requestId);

        return [
            'request_id' => $requestId,
            'amount'     => $request['total_fee'],
            'gateway'    => 'paymongo',
            'payment' => $payment ? [
                'status'                      => $payment['status'],
                'payment_method'              => $payment['payment_method'] ?? 'paymongo',
                'reference_number'            => $payment['reference_number'],
                'submitted_at'                => $payment['submitted_at'],
                'rejection_reason'            => $payment['rejection_reason'],
                'paymongo_checkout_session_id' => $payment['paymongo_checkout_session_id'] ?? null,
                'paymongo_checkout_url'        => $payment['paymongo_checkout_url'] ?? null,
            ] : null,
        ];
    }

    /**
     * Student starts PayMongo hosted checkout.
     */
    public function startCheckout(int $requestId, int $userId): array
    {
        $request = DocumentRequestModel::findByIdAndUser($requestId, $userId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        $allowedStatuses = ['payment_verified'];
        if (!in_array($request['status'], $allowedStatuses, true)) {
            throw new \RuntimeException('Payment cannot be submitted for this request status.', 422);
        }

        $amount = (int) round(((float) $request['total_fee']) * 100);
        if ($amount < 100) {
            throw new \RuntimeException('PayMongo requires a minimum payment amount of PHP 1.00.', 422);
        }

        $frontendUrl = rtrim((string) Env::get('FRONTEND_URL', ''), '/');
        if ($frontendUrl === '') {
            throw new \RuntimeException('FRONTEND_URL is required for PayMongo checkout redirects.', 500);
        }

        $returnUrl = "{$frontendUrl}/requests/{$requestId}/payment";
        $paymongo = new PaymongoService();
        $session = $paymongo->createCheckoutSession([
            'line_items' => [[
                'name'        => $request['document_type_name'] ?? 'Document Request',
                'description' => $request['request_number'],
                'amount'      => $amount,
                'currency'    => 'PHP',
                'quantity'    => 1,
            ]],
            'payment_method_types' => ['card', 'gcash', 'paymaya', 'grab_pay'],
            'success_url'          => "{$returnUrl}?paymongo=success",
            'cancel_url'           => "{$returnUrl}?paymongo=cancelled",
            'description'          => "Payment for {$request['request_number']}",
            'reference_number'     => $request['request_number'],
            'send_email_receipt'   => true,
            'show_description'     => true,
            'show_line_items'      => true,
        ]);

        $sessionData = $session['data'] ?? [];
        $attributes = $sessionData['attributes'] ?? [];
        $sessionId = (string) ($sessionData['id'] ?? '');
        $checkoutUrl = (string) (
            $attributes['checkout_url']
            ?? $attributes['url']
            ?? $sessionData['checkout_url']
            ?? ''
        );

        if ($sessionId === '' || $checkoutUrl === '') {
            throw new \RuntimeException('PayMongo did not return a checkout URL.', 502);
        }

        $paymentIntentId = $attributes['payment_intent']['id']
            ?? $attributes['payment_intent_id']
            ?? null;

        Database::beginTransaction();

        try {
            $payment = PaymentRecordModel::findByRequestId($requestId);
            $data = [
                'request_id'                    => $requestId,
                'amount'                        => $request['total_fee'],
                'payment_method'                => 'paymongo',
                'paymongo_checkout_session_id'  => $sessionId,
                'paymongo_checkout_url'         => $checkoutUrl,
                'paymongo_payment_intent_id'    => $paymentIntentId,
                'gateway_payload'               => $session,
            ];

            if ($payment) {
                PaymentRecordModel::updatePaymongoSession($requestId, $data);
            } else {
                PaymentRecordModel::create($data);
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return [
            'checkout_session_id' => $sessionId,
            'checkout_url'        => $checkoutUrl,
            'payment'             => PaymentRecordModel::findByRequestId($requestId),
        ];
    }

    public function syncCheckout(int $requestId, int $userId, ?string $sessionId = null): array
    {
        $request = DocumentRequestModel::findByIdAndUser($requestId, $userId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        $payment = PaymentRecordModel::findByRequestId($requestId);
        if (!$payment || empty($payment['paymongo_checkout_session_id'])) {
            throw new \RuntimeException('No PayMongo checkout session found for this request.', 404);
        }

        $storedSessionId = (string) $payment['paymongo_checkout_session_id'];
        if ($sessionId && $sessionId !== $storedSessionId) {
            throw new \RuntimeException('Checkout session does not match this request.', 422);
        }

        $paymongo = new PaymongoService();
        $session = $paymongo->retrieveCheckoutSession($storedSessionId);
        $isPaid = $this->checkoutSessionIsPaid($session);
        $paymentId = $this->paymentIdFromCheckoutSession($session);

        if ($isPaid && $payment['status'] !== 'verified') {
            Database::beginTransaction();
            try {
                PaymentRecordModel::markGatewayVerified($requestId, $session, $paymentId);

                if (!in_array($request['status'], ['payment_verified', 'completed'], true)) {
                    $previous = $request['status'];
                    DocumentRequestModel::updateStatus($requestId, 'payment_verified', null, null);
                    RequestStatusLogModel::log($requestId, $previous, 'payment_verified', $userId, 'Payment verified by PayMongo.');
                    NotificationService::paymentVerified($userId, $request['request_number'], $requestId);
                    $this->sendProcessingEstimateSms($requestId, $request);
                }

                Database::commit();
            } catch (\Throwable $e) {
                Database::rollback();
                throw $e;
            }
        }

        return [
            'paid'    => $isPaid,
            'session' => $session,
            'payment' => PaymentRecordModel::findByRequestId($requestId),
        ];
    }

    public function startKioskCheckout(int $requestId): array
    {
        $request = DocumentRequestModel::findById($requestId);

        if (!$request || ($request['request_source'] ?? '') !== 'kiosk') {
            throw new \RuntimeException('Request not found.', 404);
        }

        $allowedStatuses = ['payment_verified'];
        if (!in_array($request['status'], $allowedStatuses, true)) {
            throw new \RuntimeException('Payment cannot be started for this request status.', 422);
        }

        return $this->createCheckoutForRequest($request, $this->kioskReturnUrl($requestId));
    }

    public function syncKioskCheckout(int $requestId, ?string $sessionId = null): array
    {
        $request = DocumentRequestModel::findById($requestId);

        if (!$request || ($request['request_source'] ?? '') !== 'kiosk') {
            throw new \RuntimeException('Request not found.', 404);
        }

        $payment = PaymentRecordModel::findByRequestId($requestId);
        if (!$payment || empty($payment['paymongo_checkout_session_id'])) {
            throw new \RuntimeException('No PayMongo checkout session found for this request.', 404);
        }

        $storedSessionId = (string) $payment['paymongo_checkout_session_id'];
        if ($sessionId && $sessionId !== $storedSessionId) {
            throw new \RuntimeException('Checkout session does not match this request.', 422);
        }

        $paymongo = new PaymongoService();
        $session = $paymongo->retrieveCheckoutSession($storedSessionId);
        $isPaid = $this->checkoutSessionIsPaid($session);
        $paymentId = $this->paymentIdFromCheckoutSession($session);

        if ($isPaid && $payment['status'] !== 'verified') {
            Database::beginTransaction();
            try {
                PaymentRecordModel::markGatewayVerified($requestId, $session, $paymentId);

                if (!in_array($request['status'], ['payment_verified', 'completed'], true)) {
                    DocumentRequestModel::updateStatus($requestId, 'payment_verified', null, null);
                    RequestStatusLogModel::log($requestId, $request['status'], 'payment_verified', null, 'Payment verified by PayMongo.');
                    $this->sendProcessingEstimateSms($requestId, $request);
                }

                Database::commit();
            } catch (\Throwable $e) {
                Database::rollback();
                throw $e;
            }
        }

        return [
            'paid'           => $isPaid,
            'request_id'     => $requestId,
            'request_number' => $request['request_number'],
            'session'        => $session,
            'payment'        => PaymentRecordModel::findByRequestId($requestId),
        ];
    }

    /**
     * Admin verifies payment.
     */
    public function verifyPayment(int $requestId, int $adminId, ?string $notes = null): void
    {
        $request = DocumentRequestModel::findById($requestId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        if ($request['status'] !== 'payment_verified') {
            throw new \RuntimeException('Payment can only be verified when status is payment verified.', 422);
        }

        Database::beginTransaction();

        try {
            PaymentRecordModel::verify($requestId, $adminId);
            DocumentRequestModel::updateStatus($requestId, 'payment_verified', null, $adminId);
            RequestStatusLogModel::log($requestId, 'payment_verified', 'payment_verified', $adminId, $notes ?? 'Payment verified by admin.');

            if ($request['user_id']) {
                NotificationService::paymentVerified($request['user_id'], $request['request_number'], $requestId);
            }
            $this->sendProcessingEstimateSms($requestId, $request);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Admin rejects payment.
     */
    public function rejectPayment(int $requestId, int $adminId, string $reason): void
    {
        $request = DocumentRequestModel::findById($requestId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        if ($request['status'] === 'payment_verified') {
            $this->refundAndRejectRequest($requestId, $adminId, $reason);
            return;
        }

        if ($request['status'] !== 'payment_verified') {
            throw new \RuntimeException('Payment can only be rejected when status is payment verified.', 422);
        }

        Database::beginTransaction();

        try {
            PaymentRecordModel::reject($requestId, $reason);
            DocumentRequestModel::updateStatus($requestId, 'rejected', $reason, $adminId);
            RequestStatusLogModel::log($requestId, 'payment_verified', 'rejected', $adminId, "Payment rejected: {$reason}");

            if ($request['user_id']) {
                NotificationService::paymentRejected($request['user_id'], $request['request_number'], $requestId, $reason);
            } else {
                $phone = $request['req_phone'] ?: ($request['walkin_phone'] ?? null);
                if ($phone) {
                    SmsService::paymentRejected($phone, $request['request_number'], $reason);
                }
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    public function refundAndRejectRequest(int $requestId, int $adminId, string $reason): void
    {
        $request = DocumentRequestModel::findById($requestId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        if ($request['status'] !== 'payment_verified') {
            throw new \RuntimeException('Only payment verified requests can be refunded and rejected.', 422);
        }

        $payment = PaymentRecordModel::findByRequestId($requestId);
        if (!$payment || $payment['status'] !== 'verified') {
            throw new \RuntimeException('Verified payment record is required before issuing a refund.', 422);
        }

        $paymentId = (string) ($payment['paymongo_payment_id'] ?? '');
        if ($paymentId === '') {
            $paymentId = $this->refreshPaymentId($requestId, $payment);
        }

        if ($paymentId === '') {
            throw new \RuntimeException('PayMongo payment ID is missing; refund cannot be created automatically.', 422);
        }

        $amount = (int) round(((float) $payment['amount']) * 100);
        if ($amount <= 0) {
            throw new \RuntimeException('Refund amount is invalid.', 422);
        }

        $refund = (new PaymongoService())->createRefund([
            'payment_id' => $paymentId,
            'amount'     => $amount,
            'reason'     => 'requested_by_customer',
            'notes'      => $reason,
        ]);

        Database::beginTransaction();

        try {
            PaymentRecordModel::markRefunded($requestId, $refund, $reason);
            DocumentRequestModel::updateStatus($requestId, 'rejected', $reason, $adminId);
            RequestStatusLogModel::log($requestId, 'payment_verified', 'rejected', $adminId, "Request rejected and payment refunded: {$reason}");

            if ($request['user_id']) {
                NotificationService::paymentRejected($request['user_id'], $request['request_number'], $requestId, $reason);
            } else {
                $phone = $request['req_phone'] ?: ($request['walkin_phone'] ?? null);
                if ($phone) {
                    SmsService::paymentRejected($phone, $request['request_number'], $reason);
                }
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    private function checkoutSessionIsPaid(array $session): bool
    {
        $attributes = $session['data']['attributes'] ?? [];
        $status = strtolower((string) ($attributes['status'] ?? ''));
        if (in_array($status, ['paid', 'completed', 'complete'], true)) {
            return true;
        }

        $paymentIntent = $attributes['payment_intent'] ?? [];
        $paymentIntentStatus = strtolower((string) ($paymentIntent['attributes']['status'] ?? $paymentIntent['status'] ?? ''));
        if (in_array($paymentIntentStatus, ['succeeded', 'paid'], true)) {
            return true;
        }

        $payments = $attributes['payments'] ?? [];
        foreach (is_array($payments) ? $payments : [] as $payment) {
            $paymentStatus = strtolower((string) ($payment['attributes']['status'] ?? $payment['status'] ?? ''));
            if ($paymentStatus === 'paid') {
                return true;
            }
        }

        return false;
    }

    private function refreshPaymentId(int $requestId, array $payment): string
    {
        $sessionId = (string) ($payment['paymongo_checkout_session_id'] ?? '');
        if ($sessionId === '') {
            return '';
        }

        $session = (new PaymongoService())->retrieveCheckoutSession($sessionId);
        $paymentId = $this->paymentIdFromCheckoutSession($session);
        if ($paymentId !== '') {
            PaymentRecordModel::markGatewayVerified($requestId, $session, $paymentId);
        }

        return $paymentId;
    }

    public function paymentIdFromCheckoutSession(array $session): string
    {
        $attributes = $session['data']['attributes'] ?? [];
        $payments = $attributes['payments'] ?? [];

        foreach (is_array($payments) ? $payments : [] as $payment) {
            $id = $payment['id'] ?? $payment['data']['id'] ?? null;
            if (is_string($id) && str_starts_with($id, 'pay_')) {
                return $id;
            }

            $nestedId = $payment['attributes']['id'] ?? null;
            if (is_string($nestedId) && str_starts_with($nestedId, 'pay_')) {
                return $nestedId;
            }
        }

        $relationshipPayments = $session['data']['relationships']['payments']['data'] ?? [];
        foreach (is_array($relationshipPayments) ? $relationshipPayments : [] as $payment) {
            $id = $payment['id'] ?? null;
            if (is_string($id) && str_starts_with($id, 'pay_')) {
                return $id;
            }
        }

        return '';
    }

    public function sendProcessingEstimateSms(int $requestId, array $request): void
    {
        $phone = $request['req_phone'] ?: ($request['walkin_phone'] ?? null);
        if (!$phone) {
            return;
        }

        $processingDays = $this->processingDays($requestId);
        $freshRequest = DocumentRequestModel::findById($requestId);
        $releaseDate = !empty($freshRequest['expected_release_at'])
            ? date('F j, Y', strtotime((string) $freshRequest['expected_release_at']))
            : (new \DateTimeImmutable('today'))->modify("+{$processingDays} days")->format('F j, Y');

        SmsService::paymentVerifiedWithEstimate(
            (string) $phone,
            (string) $request['request_number'],
            $processingDays,
            $releaseDate
        );
    }

    private function processingDays(int $requestId): int
    {
        $row = Database::selectOne(
            "SELECT COALESCE(MAX(dt.processing_days), 0) AS processing_days
             FROM document_request_items dri
             JOIN document_types dt ON dt.id = dri.document_type_id
             WHERE dri.request_id = ?",
            [$requestId]
        );

        return max(0, (int) ($row['processing_days'] ?? 0));
    }

    private function createCheckoutForRequest(array $request, string $returnUrl): array
    {
        $requestId = (int) $request['id'];
        $amount = (int) round(((float) $request['total_fee']) * 100);
        if ($amount < 100) {
            throw new \RuntimeException('PayMongo requires a minimum payment amount of PHP 1.00.', 422);
        }

        $paymongo = new PaymongoService();
        $session = $paymongo->createCheckoutSession([
            'line_items' => [[
                'name'        => $request['document_type_name'] ?? 'Document Request',
                'description' => $request['request_number'],
                'amount'      => $amount,
                'currency'    => 'PHP',
                'quantity'    => 1,
            ]],
            'payment_method_types' => ['card', 'gcash', 'paymaya', 'grab_pay'],
            'success_url'          => "{$returnUrl}?paymongo=success",
            'cancel_url'           => "{$returnUrl}?paymongo=cancelled",
            'description'          => "Payment for {$request['request_number']}",
            'reference_number'     => $request['request_number'],
            'send_email_receipt'   => true,
            'show_description'     => true,
            'show_line_items'      => true,
        ]);

        $sessionData = $session['data'] ?? [];
        $attributes = $sessionData['attributes'] ?? [];
        $sessionId = (string) ($sessionData['id'] ?? '');
        $checkoutUrl = (string) (
            $attributes['checkout_url']
            ?? $attributes['url']
            ?? $sessionData['checkout_url']
            ?? ''
        );

        if ($sessionId === '' || $checkoutUrl === '') {
            throw new \RuntimeException('PayMongo did not return a checkout URL.', 502);
        }

        $paymentIntentId = $attributes['payment_intent']['id']
            ?? $attributes['payment_intent_id']
            ?? null;

        Database::beginTransaction();

        try {
            $payment = PaymentRecordModel::findByRequestId($requestId);
            $data = [
                'request_id'                    => $requestId,
                'amount'                        => $request['total_fee'],
                'payment_method'                => 'paymongo',
                'paymongo_checkout_session_id'  => $sessionId,
                'paymongo_checkout_url'         => $checkoutUrl,
                'paymongo_payment_intent_id'    => $paymentIntentId,
                'gateway_payload'               => $session,
            ];

            if ($payment) {
                PaymentRecordModel::updatePaymongoSession($requestId, $data);
            } else {
                PaymentRecordModel::create($data);
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return [
            'request_id'          => $requestId,
            'request_number'      => $request['request_number'],
            'amount'              => (float) $request['total_fee'],
            'gateway'             => 'paymongo',
            'checkout_session_id' => $sessionId,
            'checkout_url'        => $checkoutUrl,
            'payment_status'      => 'pending',
            'payment'             => PaymentRecordModel::findByRequestId($requestId),
        ];
    }

    private function kioskReturnUrl(int $requestId): string
    {
        $frontendUrl = rtrim((string) Env::get('FRONTEND_URL', ''), '/');
        if ($frontendUrl === '') {
            throw new \RuntimeException('FRONTEND_URL is required for PayMongo checkout redirects.', 500);
        }

        return "{$frontendUrl}/kiosk/payment/{$requestId}";
    }
}
