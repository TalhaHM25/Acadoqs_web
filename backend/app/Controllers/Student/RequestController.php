<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Models\DocumentRequestModel;
use App\Services\RequestService;
use App\Validators\RequestValidator;

class RequestController
{
    private RequestService $requestService;

    public function __construct()
    {
        $this->requestService = new RequestService();
    }

    public function index(): void
    {
        $userId  = $this->authUserId();
        $filters = [
            'status' => $_GET['status'] ?? null,
        ];

        $rows = DocumentRequestModel::listByUser($userId, $filters);
        Response::success('Requests retrieved.', $rows);
    }

    public function store(): void
    {
        $data   = $this->requestBody();
        $userId = $this->authUserId();
        $errors = RequestValidator::create($data);

        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        $checkout = $this->requestService->startPaidCheckout($userId, $data);
        Response::created('Checkout created successfully.', $checkout);
    }

    public function finalizePaidCheckout(): void
    {
        $userId = $this->authUserId();
        $data = $this->jsonBody();
        $token = trim((string) ($data['checkout_token'] ?? ($_GET['checkout_token'] ?? '')));

        if ($token === '') {
            Response::error('Checkout token is required.', 422, ['checkout_token' => ['Checkout token is required.']]);
        }

        $request = $this->requestService->finalizePaidCheckout(
            $userId,
            $token,
            $data['checkout_session_id'] ?? ($_GET['checkout_session_id'] ?? null)
        );

        Response::created('Request submitted successfully.', $request);
    }

    public function show(array $params): void
    {
        $userId    = $this->authUserId();
        $requestId = (int) $params['id'];

        $request = $this->requestService->getForStudent($requestId, $userId);
        Response::success('Request retrieved.', $request);
    }

    public function destroy(array $params): void
    {
        $userId    = $this->authUserId();
        $requestId = (int) $params['id'];

        $this->requestService->cancel($requestId, $userId);
        Response::success('Request cancelled successfully.');
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function jsonBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function requestBody(): array
    {
        if (!empty($_POST)) {
            $data = $_POST;
            if (isset($data['items']) && is_string($data['items'])) {
                $decoded = json_decode($data['items'], true);
                $data['items'] = is_array($decoded) ? $decoded : [];
            }
            return $data;
        }

        return $this->jsonBody();
    }

    private function authUserId(): int
    {
        return (int) (JWT::getAuthUser()['user_id'] ?? 0);
    }
}
