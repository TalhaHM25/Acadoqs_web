<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Models\DocumentRequestModel;
use App\Services\RequestService;

class RequestController
{
    private RequestService $requestService;

    public function __construct()
    {
        $this->requestService = new RequestService();
    }

    public function index(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($_GET['per_page'] ?? 20)));

        $filters = [
            'status'           => $_GET['status']           ?? null,
            'document_type_id' => $_GET['document_type_id'] ?? null,
            'source'           => $_GET['source']           ?? null,
            'date_from'        => $_GET['date_from']         ?? null,
            'date_to'          => $_GET['date_to']           ?? null,
            'search'           => $_GET['search']            ?? null,
            'level'            => $this->levelFilter(),  // null for admin: no filter
        ];

        $result = DocumentRequestModel::listAll($filters, $page, $perPage);
        $summaryFilters = $filters;
        unset($summaryFilters['status']);

        Response::success('Requests retrieved.', $result['rows'], 200, [
            'total'          => $result['total'],
            'per_page'       => $perPage,
            'current_page'   => $page,
            'last_page'      => (int) ceil($result['total'] / $perPage),
            'status_summary' => DocumentRequestModel::statusSummary($summaryFilters),
        ]);
    }

    public function calendar(): void
    {
        $filters = [
            'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
            'date_to'   => $_GET['date_to']   ?? date('Y-m-t'),
            'level'     => $this->levelFilter(),
        ];

        $rows = DocumentRequestModel::calendar($filters);

        Response::success('Calendar requests retrieved.', $rows);
    }

    public function show(array $params): void
    {
        $requestId = (int) $params['id'];
        $request   = $this->requestService->adminGet($requestId);

        // Registrars may only view requests matching their level
        $level = $this->levelFilter();
        if ($level && ($request['level'] ?? null) !== $level) {
            Response::error('Request not found.', 404);
        }

        Response::success('Request retrieved.', $request);
    }

    public function updateStatus(array $params): void
    {
        $adminId   = $this->authUserId();
        $requestId = (int) $params['id'];
        $data      = $this->jsonBody();

        if (empty($data['status'])) {
            Response::error('Status is required.', 422, ['status' => ['Status is required.']]);
        }

        // Level guard for registrars
        $level = $this->levelFilter();
        if ($level) {
            $request = $this->requestService->adminGet($requestId);
            if (($request['level'] ?? null) !== $level) {
                Response::error('Request not found.', 404);
            }
        }

        $request = $this->requestService->adminUpdateStatus(
            $requestId,
            $data['status'],
            $adminId,
            $data['notes']            ?? null,
            $data['rejection_reason'] ?? null
        );

        Response::success('Status updated successfully.', $request);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Returns 'college' or 'senior_high' for registrar accounts, null for admin.
     */
    private function levelFilter(): ?string
    {
        $user = JWT::getAuthUser();
        return match($user['role'] ?? '') {
            'college_registrar'     => 'college',
            'senior_high_registrar' => 'senior_high',
            default                 => null,
        };
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
