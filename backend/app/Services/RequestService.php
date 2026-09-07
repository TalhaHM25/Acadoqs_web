<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Helpers\Upload;
use App\Models\DocumentRequestItemModel;
use App\Models\DocumentRequestModel;
use App\Models\DocumentTypeModel;
use App\Models\PaymentRecordModel;
use App\Models\RequestAttachmentModel;
use App\Models\RequestCheckoutSessionModel;
use App\Models\RequestStatusLogModel;

class RequestService
{
    public function startPaidCheckout(int $userId, array $data): array
    {
        RequestLimitService::enforceDailyLimit();

        [$items, $totalFee, $firstDocTypeId, $maxProcessingDays] = $this->prepareItems($data['items'] ?? []);
        $identityProof = $this->storePendingIdentityProof();
        $token = bin2hex(random_bytes(24));

        $payload = array_merge($data, [
            'user_id'              => $userId,
            'request_source'       => 'online',
            'status'               => 'payment_verified',
            'total_fee'            => $totalFee,
            'document_type_id'     => $firstDocTypeId,
            'copies'               => count($items),
            'is_certified_copy'    => 0,
            'items'                => $items,
            'max_processing_days'  => $maxProcessingDays,
        ]);

        RequestCheckoutSessionModel::create([
            'token'                   => $token,
            'user_id'                 => $userId,
            'request_payload'         => $payload,
            'identity_proof_path'     => $identityProof['path'],
            'identity_proof_filename' => $identityProof['filename'],
            'identity_proof_mime'     => $identityProof['mime'],
            'identity_proof_size'     => $identityProof['size'],
            'amount'                  => $totalFee,
        ]);

        $amount = (int) round($totalFee * 100);
        if ($amount < 100) {
            throw new \RuntimeException('PayMongo requires a minimum payment amount of PHP 1.00.', 422);
        }

        $frontendUrl = rtrim((string) Env::get('FRONTEND_URL', ''), '/');
        if ($frontendUrl === '') {
            throw new \RuntimeException('FRONTEND_URL is required for PayMongo checkout redirects.', 500);
        }

        $returnUrl = "{$frontendUrl}/requests/payment/complete";
        $session = (new PaymongoService())->createCheckoutSession([
            'line_items' => [[
                'name'        => $this->checkoutDocumentName($items),
                'description' => 'Document request payment',
                'amount'      => $amount,
                'currency'    => 'PHP',
                'quantity'    => 1,
            ]],
            'payment_method_types' => ['card', 'gcash', 'paymaya', 'grab_pay'],
            'success_url'          => "{$returnUrl}?paymongo=success&checkout_token={$token}",
            'cancel_url'           => "{$frontendUrl}/requests/new?paymongo=cancelled",
            'description'          => 'Payment for document request',
            'reference_number'     => $token,
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

        RequestCheckoutSessionModel::updatePaymongoSession($token, [
            'paymongo_checkout_session_id' => $sessionId,
            'paymongo_checkout_url'        => $checkoutUrl,
            'paymongo_payment_intent_id'   => $attributes['payment_intent']['id'] ?? $attributes['payment_intent_id'] ?? null,
            'gateway_payload'              => $session,
        ]);

        return [
            'checkout_token'      => $token,
            'checkout_session_id' => $sessionId,
            'checkout_url'        => $checkoutUrl,
            'amount'              => $totalFee,
        ];
    }

    public function finalizePaidCheckout(int $userId, string $token, ?string $sessionId = null): array
    {
        $checkout = RequestCheckoutSessionModel::findByTokenForUser($token, $userId);
        if (!$checkout) {
            throw new \RuntimeException('Checkout session not found.', 404);
        }

        if (!empty($checkout['document_request_id'])) {
            return $this->getForStudent((int) $checkout['document_request_id'], $userId);
        }

        $storedSessionId = (string) ($checkout['paymongo_checkout_session_id'] ?? '');
        if ($storedSessionId === '') {
            throw new \RuntimeException('Checkout session is incomplete.', 422);
        }

        if ($sessionId && $sessionId !== $storedSessionId) {
            throw new \RuntimeException('Checkout session does not match this request.', 422);
        }

        $paymongo = new PaymongoService();
        $paymentService = new PaymentService();
        $session = $paymongo->retrieveCheckoutSession($storedSessionId);
        if (!$this->checkoutSessionIsPaid($session)) {
            throw new \RuntimeException('Payment has not been confirmed yet.', 422);
        }

        $payload = json_decode((string) $checkout['request_payload'], true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Stored checkout payload is invalid.', 500);
        }

        $paymentId = $paymentService->paymentIdFromCheckoutSession($session);

        Database::beginTransaction();
        try {
            $requestNumber = DocumentRequestModel::nextRequestNumber();
            $requestId = DocumentRequestModel::create(array_merge($payload, [
                'request_number' => $requestNumber,
                'status'         => 'payment_verified',
            ]));

            DocumentRequestItemModel::createMany($requestId, $payload['items'] ?? []);

            RequestAttachmentModel::create([
                'request_id'   => $requestId,
                'label'        => 'identity_proof',
                'file_path'    => $checkout['identity_proof_path'],
                'file_name'    => $checkout['identity_proof_filename'],
                'file_size'    => (int) $checkout['identity_proof_size'],
                'mime_type'    => $checkout['identity_proof_mime'],
                'uploaded_by'  => $userId,
            ]);

            PaymentRecordModel::create([
                'request_id'                   => $requestId,
                'amount'                       => $checkout['amount'],
                'payment_method'               => 'paymongo',
                'paymongo_checkout_session_id' => $storedSessionId,
                'paymongo_checkout_url'        => $checkout['paymongo_checkout_url'],
                'paymongo_payment_intent_id'   => $checkout['paymongo_payment_intent_id'],
                'gateway_payload'              => $session,
                'submitted_at'                 => date('Y-m-d H:i:s'),
            ]);
            PaymentRecordModel::markGatewayVerified($requestId, $session, $paymentId);

            RequestStatusLogModel::log($requestId, null, 'payment_verified', $userId, 'Request created after successful PayMongo payment.');
            RequestCheckoutSessionModel::markConverted($token, $requestId, $session, $paymentId);
            NotificationService::requestSubmitted($userId, $requestNumber, $requestId);
            NotificationService::paymentVerified($userId, $requestNumber, $requestId);
            $paymentService->sendProcessingEstimateSms($requestId, array_merge($payload, ['request_number' => $requestNumber]));

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return $this->getForStudent($requestId, $userId);
    }

    public function create(int $userId, array $data): array
    {
        RequestLimitService::enforceDailyLimit();

        [$items, $totalFee, $firstDocTypeId] = $this->prepareItems($data['items'] ?? []);

        Database::beginTransaction();

        try {
            $requestNumber = DocumentRequestModel::nextRequestNumber();

            $requestId = DocumentRequestModel::create(array_merge($data, [
                'user_id'           => $userId,
                'request_number'    => $requestNumber,
                'status'            => 'payment_verified',
                'request_source'    => 'online',
                'total_fee'         => $totalFee,
                'document_type_id'  => $firstDocTypeId,
                'copies'            => count($items),
                'is_certified_copy' => 0,
            ]));

            DocumentRequestItemModel::createMany($requestId, $items);
            $this->storeIdentityProofAttachment($requestId, $userId);
            RequestStatusLogModel::log($requestId, null, 'payment_verified', $userId, 'Request submitted by student.');

            PaymentRecordModel::create([
                'request_id'       => $requestId,
                'amount'           => $totalFee,
                'payment_method'   => 'paymongo',
                'payment_method_id' => $data['payment_method_id'] ?? null,
            ]);

            NotificationService::requestSubmitted($userId, $requestNumber, $requestId);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return $this->getForStudent($requestId, $userId);
    }

    public function getForStudent(int $requestId, int $userId): array
    {
        $request = DocumentRequestModel::findByIdAndUser($requestId, $userId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        $request['status_logs'] = RequestStatusLogModel::forRequest($requestId);
        $request['payment']     = PaymentRecordModel::findByRequestId($requestId);
        $request['items']       = DocumentRequestItemModel::forRequest($requestId);
        $request['attachments'] = RequestAttachmentModel::forRequest($requestId);

        return $request;
    }

    public function cancel(int $requestId, int $userId): void
    {
        $request = DocumentRequestModel::findByIdAndUser($requestId, $userId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        if (!in_array($request['status'], DocumentRequestModel::CANCELLABLE_BY_STUDENT, true)) {
            throw new \RuntimeException('This request can no longer be cancelled.', 422);
        }

        DocumentRequestModel::updateStatus($requestId, 'rejected', 'Cancelled by student.', $userId);
        RequestStatusLogModel::log($requestId, $request['status'], 'rejected', $userId, 'Cancelled by student.');
    }

    public function adminUpdateStatus(int $requestId, string $newStatus, int $adminId, ?string $notes = null, ?string $rejectionReason = null): array
    {
        $request = DocumentRequestModel::findById($requestId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        $current = $request['status'];
        $allowed = DocumentRequestModel::ADMIN_TRANSITIONS[$current] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException("Cannot transition from '{$current}' to '{$newStatus}'.", 422);
        }

        if ($newStatus === 'rejected' && empty($rejectionReason)) {
            throw new \RuntimeException('Rejection reason is required.', 422);
        }

        if ($current === 'payment_verified' && $newStatus === 'rejected') {
            (new PaymentService())->refundAndRejectRequest($requestId, $adminId, $rejectionReason);
            return DocumentRequestModel::findById($requestId);
        }

        DocumentRequestModel::updateStatus($requestId, $newStatus, $rejectionReason, $adminId);
        RequestStatusLogModel::log($requestId, $current, $newStatus, $adminId, $this->statusAuditNote($request, $newStatus, $notes));

        if ($request['user_id']) {
            NotificationService::statusChanged($request['user_id'], $request['request_number'], $newStatus, $requestId, $rejectionReason);
        } else {
            $phone = $request['req_phone'] ?: ($request['walkin_phone'] ?? null);
            if ($phone) {
                SmsService::statusChanged($phone, $request['request_number'], $newStatus);
            }
        }

        return DocumentRequestModel::findById($requestId);
    }

    public function adminGet(int $requestId): array
    {
        $request = DocumentRequestModel::findById($requestId);

        if (!$request) {
            throw new \RuntimeException('Request not found.', 404);
        }

        $request['status_logs'] = RequestStatusLogModel::forRequest($requestId);
        $request['payment']     = PaymentRecordModel::findByRequestId($requestId);
        $request['items']       = DocumentRequestItemModel::forRequest($requestId);
        $request['attachments'] = RequestAttachmentModel::forRequest($requestId);

        return $request;
    }

    private function prepareItems(array $rawItems): array
    {
        if (empty($rawItems)) {
            throw new \RuntimeException('At least one document must be selected.', 422);
        }

        $items = [];
        $totalFee = 0.0;
        $firstDocTypeId = null;
        $maxProcessingDays = 0;

        foreach ($rawItems as $raw) {
            $docType = DocumentTypeModel::findById((int) $raw['document_type_id']);
            if (!$docType) {
                throw new \RuntimeException("Document type {$raw['document_type_id']} not found.", 404);
            }

            $copies = max(1, (int) ($raw['copies'] ?? 1));
            $isCert = !empty($raw['is_certified_copy']);
            $certFee = $isCert ? (float) $docType['certified_copy_fee'] : 0;
            $itemFee = ((float) $docType['base_fee'] + $certFee) * $copies;
            $totalFee += $itemFee;
            $maxProcessingDays = max($maxProcessingDays, (int) ($docType['processing_days'] ?? 0));

            if ($firstDocTypeId === null) {
                $firstDocTypeId = $docType['id'];
            }

            $items[] = [
                'document_type_id'   => $docType['id'],
                'document_type_name' => $docType['name'] ?? null,
                'copies'             => $copies,
                'is_certified_copy'  => $isCert ? 1 : 0,
                'item_fee'           => $itemFee,
                'special_data'       => $raw['special_data'] ?? null,
            ];
        }

        return [$items, $totalFee, $firstDocTypeId, $maxProcessingDays];
    }

    private function storePendingIdentityProof(): array
    {
        $this->assertIdentityProofImage();
        return Upload::store('identity_proof', 'pending-identity-proofs');
    }

    private function storeIdentityProofAttachment(int $requestId, int $userId): void
    {
        $this->assertIdentityProofImage();
        $uploaded = Upload::store('identity_proof', 'request-attachments');

        RequestAttachmentModel::create([
            'request_id'   => $requestId,
            'label'        => 'identity_proof',
            'file_path'    => $uploaded['path'],
            'file_name'    => $uploaded['filename'],
            'file_size'    => $uploaded['size'],
            'mime_type'    => $uploaded['mime'],
            'uploaded_by'  => $userId,
        ]);
    }

    private function assertIdentityProofImage(): void
    {
        if (empty($_FILES['identity_proof']) || $_FILES['identity_proof']['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Identity proof photo is required.', 422);
        }

        $tmpPath = $_FILES['identity_proof']['tmp_name'] ?? '';
        $mimeType = $tmpPath !== '' ? (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath) : '';
        if (!is_string($mimeType) || strpos($mimeType, 'image/') !== 0) {
            throw new \RuntimeException('Identity proof must be an image file.', 422);
        }
    }

    private function checkoutDocumentName(array $items): string
    {
        $names = array_values(array_filter(array_map(
            static fn (array $item): ?string => $item['document_type_name'] ?? null,
            $items
        )));

        if (count($names) === 1) {
            return $names[0];
        }

        if (count($names) > 1) {
            return 'Document Request (' . count($names) . ' items)';
        }

        return 'Document Request';
    }

    private function checkoutSessionIsPaid(array $session): bool
    {
        $attributes = $session['data']['attributes'] ?? [];
        $status = strtolower((string) ($attributes['status'] ?? ''));
        if (in_array($status, ['paid', 'completed', 'complete'], true)) {
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

    private function statusAuditNote(array $request, string $newStatus, ?string $notes): ?string
    {
        if ($notes) {
            return $notes;
        }

        return match ($newStatus) {
            'completed' => 'Document released/completed by staff.',
            'rejected'  => 'Document request rejected by staff.',
            default     => null,
        };
    }
}
