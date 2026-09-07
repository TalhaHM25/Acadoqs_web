<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Services\PaymentService;

class PaymentController
{
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    public function show(array $params): void
    {
        $userId    = $this->authUserId();
        $requestId = (int) $params['id'];

        $info = $this->paymentService->getPaymentInfo($requestId, $userId);
        Response::success('Payment info retrieved.', $info);
    }

    public function store(array $params): void
    {
        $userId    = $this->authUserId();
        $requestId = (int) $params['id'];

        $result = $this->paymentService->startCheckout($requestId, $userId);
        Response::success('PayMongo checkout created.', $result);
    }

    public function sync(array $params): void
    {
        $userId    = $this->authUserId();
        $requestId = (int) $params['id'];
        $data      = $this->jsonBody();

        $result = $this->paymentService->syncCheckout(
            $requestId,
            $userId,
            $data['checkout_session_id'] ?? ($_GET['checkout_session_id'] ?? null)
        );

        Response::success('Payment status synced.', $result);
    }

    private function authUserId(): int
    {
        return (int) (JWT::getAuthUser()['user_id'] ?? 0);
    }

    private function jsonBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
