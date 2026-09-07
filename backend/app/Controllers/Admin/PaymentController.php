<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Models\DocumentRequestModel;
use App\Services\PaymentService;

class PaymentController
{
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    public function verify(array $params): void
    {
        $adminId   = $this->authUserId();
        $requestId = (int) $params['id'];
        $data      = $this->jsonBody();

        $this->assertLevelAccess($requestId);
        $this->paymentService->verifyPayment($requestId, $adminId, $data['notes'] ?? null);
        Response::success('Payment verified successfully.');
    }

    public function reject(array $params): void
    {
        $adminId   = $this->authUserId();
        $requestId = (int) $params['id'];
        $data      = $this->jsonBody();

        if (empty($data['reason'])) {
            Response::error('Rejection reason is required.', 422, ['reason' => ['Reason is required.']]);
        }

        $this->assertLevelAccess($requestId);
        $this->paymentService->rejectPayment($requestId, $adminId, $data['reason']);
        Response::success('Payment rejected.');
    }

    /** Registrars may only act on requests matching their level. */
    private function assertLevelAccess(int $requestId): void
    {
        $user  = JWT::getAuthUser();
        $level = match($user['role'] ?? '') {
            'college_registrar'     => 'college',
            'senior_high_registrar' => 'senior_high',
            default                 => null,
        };

        if ($level === null) return; // admin — no restriction

        $request = DocumentRequestModel::findById($requestId);
        if (!$request || ($request['level'] ?? null) !== $level) {
            Response::error('Request not found.', 404);
        }
    }

    private function jsonBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function authUserId(): int
    {
        return (int) (JWT::getAuthUser()['user_id'] ?? 0);
    }
}
