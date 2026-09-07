<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Helpers\Response;
use App\Models\DocumentRequestItemModel;
use App\Models\DocumentRequestModel;
use App\Models\DocumentTypeModel;
use App\Models\PaymentMethodModel;
use App\Models\QueueModel;
use App\Services\PaymentService;
use App\Services\RequestLimitService;
use App\Services\SmsService;

/**
 * Public endpoints for the Kiosk and Mobile App.
 * Protected by API key (X-API-Key header) — no user login required.
 * Walk-in students provide their info inline with the request.
 */
class KioskController
{
    private function normalizePhoneTo639(string $phone): string
    {
        $clean = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($clean, '09') && strlen($clean) === 11) {
            return '63' . substr($clean, 1);
        }
        return $clean;
    }

    /** GET /api/kiosk/documents?level=college|senior_high */
    public function documents(): void
    {
        $level = $_GET['level'] ?? 'college';

        $rows = Database::select(
            "SELECT dt.id, dt.name, dt.description, dt.base_fee, dt.certified_copy_fee,
                    dt.processing_days, dt.required_fields, dt.level,
                    dc.name AS category_name
             FROM document_types dt
             JOIN document_categories dc ON dc.id = dt.category_id
             WHERE dt.is_active = 1
               AND (dt.level = ? OR dt.level = 'all')
             ORDER BY dc.sort_order, dt.sort_order, dt.name",
            [$level]
        );

        foreach ($rows as &$row) {
            $row['required_fields'] = $row['required_fields']
                ? json_decode($row['required_fields'], true)
                : [];
        }

        Response::success('Documents retrieved.', $rows);
    }

    /** GET /api/kiosk/payment-methods */
    public function paymentMethods(): void
    {
        Response::success('Payment methods retrieved.', PaymentMethodModel::listActive());
    }

    /**
     * POST /api/kiosk/request
     * Creates a walk-in document request + queue number.
     *
     * Body:
     * {
     *   level: 'college'|'senior_high',
     *   walkin_name: string,
     *   walkin_phone: string,
     *   walkin_email: string,
     *   purpose: string,
     *   payment_method: 'paymongo' | null,
     *   payment_method_id: int | null,
     *   items: [
     *     { document_type_id, copies, is_certified_copy, special_data? }
     *   ]
     * }
     */
    public function createRequest(): void
    {
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = $this->validateRequest($data);

        if ($errors) Response::error('Validation failed.', 422, $errors);

        RequestLimitService::enforceDailyLimit();

        Database::beginTransaction();

        try {
            // Build total fee from items
            $totalFee = 0;
            $items    = [];

            foreach ($data['items'] as $raw) {
                $docType = DocumentTypeModel::findById((int) $raw['document_type_id']);
                if (!$docType) {
                    Response::error("Document type {$raw['document_type_id']} not found.", 422);
                }
                $copies      = max(1, (int) ($raw['copies'] ?? 1));
                $isCert      = !empty($raw['is_certified_copy']);
                $certFee     = $isCert ? (float) $docType['certified_copy_fee'] : 0;
                $itemFee     = ((float) $docType['base_fee'] + $certFee) * $copies;
                $totalFee   += $itemFee;

                $items[] = [
                    'document_type_id'  => $docType['id'],
                    'copies'            => $copies,
                    'is_certified_copy' => $isCert ? 1 : 0,
                    'item_fee'          => $itemFee,
                    'special_data'      => $raw['special_data'] ?? null,
                ];
            }

            // Insert the request header (no user_id for walk-in)
            $requestNumber = $this->nextRequestNumber();
            // Determine source from the API key type (kiosk vs mobile)
            $apiKey        = $GLOBALS['api_key'] ?? [];
            $source        = in_array($apiKey['type'] ?? '', ['mobile'], true) ? 'mobile' : 'kiosk';

            $requestId     = Database::insert(
                "INSERT INTO document_requests
                 (request_number, user_id, level, is_walkin, request_source,
                  walkin_name, walkin_phone, walkin_email,
                  status, purpose, total_fee, payment_method_id,
                  req_last_name, req_first_name, req_email, req_phone,
                  copies, is_certified_copy)
                 VALUES (?, NULL, ?, 1, ?, ?, ?, ?, 'payment_verified', ?, ?, ?, ?, ?, ?, ?, 1, 0)",
                [
                    $requestNumber,
                    $data['level'],
                    $source,
                    $data['walkin_name'],
                    $data['walkin_phone'],
                    $data['walkin_email']  ?? null,
                    $data['purpose'],
                    $totalFee,
                    ($data['payment_method'] ?? '') === 'paymongo' ? null : ($data['payment_method_id'] ?? null),
                    $data['walkin_name'],
                    $data['walkin_name'],
                    $data['walkin_email']  ?? null,
                    $this->normalizePhoneTo639($data['walkin_phone']),
                ]
            );

            DocumentRequestItemModel::createMany($requestId, $items);

            // Create queue number
            $queueId = QueueModel::create([
                'type'         => 'document_request',
                'service_area' => $data['service_area'] ?? 'registrar',
                'level'        => $data['level'],
                'request_id'   => $requestId,
            ]);

            $queue = QueueModel::findById($queueId);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        SmsService::requestSubmitted(
            $this->normalizePhoneTo639($data['walkin_phone']),
            $requestNumber,
            $queue['queue_number'] ?? null,
            (float) $totalFee
        );

        Response::created('Request submitted.', [
            'request_id'     => $requestId,
            'request_number' => $requestNumber,
            'queue_number'   => $queue['queue_number'],
            'service_area'   => $queue['service_area'] ?? ($data['service_area'] ?? 'registrar'),
            'issued_at'      => $queue['created_at'] ?? null,
            'total_fee'      => $totalFee,
            'payment_status' => 'pending',
            'items'          => $items,
        ]);
    }

    /** POST /api/kiosk/requests/{id}/payment */
    public function startPayment(array $params): void
    {
        $requestId = (int) $params['id'];
        $result = (new PaymentService())->startKioskCheckout($requestId);

        Response::success('Payment session created.', $result);
    }

    /** POST /api/kiosk/requests/{id}/payment/sync */
    public function syncPayment(array $params): void
    {
        $requestId = (int) $params['id'];
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = (new PaymentService())->syncKioskCheckout(
            $requestId,
            $data['checkout_session_id'] ?? ($_GET['checkout_session_id'] ?? null)
        );

        Response::success('Payment status synced.', $result);
    }

    /** POST /api/kiosk/queue — get a general queue number (no document request) */
    public function getQueueNumber(): void
    {
        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $level = $data['level'] ?? 'college';

        if (!in_array($level, ['college','senior_high'], true)) {
            Response::error('level must be college or senior_high.', 422);
        }

        $serviceArea = in_array($data['service_area'] ?? '', ['registrar','cashier'], true)
            ? $data['service_area']
            : 'registrar';

        $queueId = QueueModel::create(['type' => 'general', 'service_area' => $serviceArea, 'level' => $level]);
        $queue   = QueueModel::findById($queueId);

        Response::created('Queue number issued.', [
            'queue_number' => $queue['queue_number'],
            'level'        => $level,
            'service_area' => $serviceArea,
            'issued_at'    => $queue['created_at'] ?? null,
        ]);
    }

    private function validateRequest(array $data): array
    {
        $errors = [];
        $paymentMethod = null;

        if (empty($data['level']) || !in_array($data['level'], ['college','senior_high'], true))
            $errors['level'] = ['Level must be college or senior_high.'];
        if (empty($data['walkin_name']))
            $errors['walkin_name'] = ['Name is required.'];
        if (empty($data['walkin_phone']) || !preg_match('/^(09\d{9}|639\d{9})$/', preg_replace('/\D+/', '', (string) $data['walkin_phone']) ?? ''))
            $errors['walkin_phone'] = ['Valid Philippine mobile number is required (09XXXXXXXXX or 639XXXXXXXXX).'];
        if (empty($data['purpose']))
            $errors['purpose'] = ['Purpose is required.'];
        $usesPaymongo = ($data['payment_method'] ?? '') === 'paymongo';
        if (!$usesPaymongo && (!isset($data['payment_method_id']) || (int) $data['payment_method_id'] <= 0)) {
            $errors['payment_method_id'] = ['Payment method is required.'];
        } elseif (!$usesPaymongo) {
            $paymentMethod = PaymentMethodModel::findById((int) $data['payment_method_id']);
            if (!$paymentMethod || (int) ($paymentMethod['is_active'] ?? 0) !== 1) {
                $errors['payment_method_id'] = ['Selected payment method is invalid or inactive.'];
            }
        }
        if (empty($data['items']) || !is_array($data['items']))
            $errors['items'] = ['At least one document must be selected.'];
        if (!empty($data['service_area']) && !in_array($data['service_area'], ['registrar','cashier'], true))
            $errors['service_area'] = ['Service area must be registrar or cashier.'];

        return $errors;
    }

    private function nextRequestNumber(): string
    {
        $year = date('Y');
        $row  = Database::selectOne(
            'SELECT COUNT(*) AS cnt FROM document_requests WHERE YEAR(created_at) = ?',
            [$year]
        );
        return sprintf('REQ-%s-%05d', $year, (int) ($row['cnt'] ?? 0) + 1);
    }
}
