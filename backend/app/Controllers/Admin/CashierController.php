<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Models\CashierWindowModel;
use App\Models\RegistrarPermissionModel;

class CashierController
{
    public function index(): void
    {
        $this->assertCashierPermission();
        $level = $this->staffLevel();
        $windowType = $this->windowType();

        Response::success('Cashier windows retrieved.', [
            'area' => $windowType,
            'windows' => CashierWindowModel::list($level, $this->isAdmin(), $windowType),
            'waiting' => CashierWindowModel::waitingCountsForArea($windowType, $level),
        ]);
    }

    public function publicDisplay(): void
    {
        $windowType = $this->publicWindowType();
        Response::success('Queue display retrieved.', [
            'area' => $windowType,
            'windows' => CashierWindowModel::list('all', false, $windowType),
            'waiting' => CashierWindowModel::waitingCountsForArea($windowType),
            'updated_at' => date(DATE_ATOM),
        ]);
    }

    public function store(): void
    {
        $data = $this->validatedWindow($this->jsonBody());
        $id = CashierWindowModel::create($data);
        Response::created('Cashier window created.', CashierWindowModel::findById($id));
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];
        $this->requireWindow($id);
        CashierWindowModel::update($id, $this->validatedWindow($this->jsonBody()));
        Response::success('Cashier window updated.', CashierWindowModel::findById($id));
    }

    public function toggle(array $params): void
    {
        $id = (int) $params['id'];
        $this->requireWindow($id);
        CashierWindowModel::toggle($id);
        Response::success('Cashier window status updated.', CashierWindowModel::findById($id));
    }

    public function callNext(array $params): void
    {
        $this->assertCashierPermission();
        $window = $this->accessibleWindow((int) $params['id']);
        $user = JWT::getAuthUser();
        $queue = CashierWindowModel::callNext((int) $window['id'], (int) ($user['user_id'] ?? 0));

        if (!$queue) {
            Response::error('There are no waiting queue numbers for this window.', 409);
        }

        Response::success('Next queue number called.', $queue);
    }

    public function recall(array $params): void
    {
        $this->assertCashierPermission();
        $window = $this->accessibleWindow((int) $params['id']);
        $queue = CashierWindowModel::recall((int) $window['id']);

        if (!$queue) {
            Response::error('This window has no active queue number.', 409);
        }

        Response::success('Queue number called again.', $queue);
    }

    public function complete(array $params): void
    {
        $this->assertCashierPermission();
        $window = $this->accessibleWindow((int) $params['id']);
        $queue = CashierWindowModel::completeCurrent((int) $window['id']);

        if (!$queue) {
            Response::error('This window has no active queue number.', 409);
        }

        Response::success('Queue number completed.', $queue);
    }

    private function accessibleWindow(int $id): array
    {
        $window = $this->requireWindow($id);
        $level = $this->staffLevel();

        if ($window['window_type'] !== $this->windowType()) {
            Response::error('Cashier window not found.', 404);
        }

        if ($window['window_type'] !== 'cashier' && $level !== 'all' && $window['level'] !== $level) {
            Response::error('Cashier window not found.', 404);
        }

        return $window;
    }

    private function requireWindow(int $id): array
    {
        $window = CashierWindowModel::findById($id);
        if (!$window) {
            Response::error('Cashier window not found.', 404);
        }
        return $window;
    }

    private function validatedWindow(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $windowType = in_array($data['window_type'] ?? '', ['registrar', 'cashier'], true)
            ? (string) $data['window_type']
            : 'cashier';
        $level = $windowType === 'cashier' ? 'all' : (string) ($data['level'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            Response::error('Window name is required and must be 100 characters or fewer.', 422);
        }
        if ($windowType === 'registrar' && !in_array($level, ['college', 'senior_high'], true)) {
            Response::error('A valid registrar window level is required.', 422);
        }

        return [
            'window_type' => $windowType,
            'name' => $name,
            'level' => $level,
            'sort_order' => max(0, min(32767, (int) ($data['sort_order'] ?? 0))),
        ];
    }

    private function assertCashierPermission(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $user = JWT::getAuthUser();
        if (($user['role'] ?? '') === 'cashier') {
            return;
        }

        $requiredModule = $this->publicWindowType() === 'registrar' ? 'queue' : 'cashier';
        if (!RegistrarPermissionModel::hasModule((int) ($user['user_id'] ?? 0), $requiredModule)) {
            Response::error("Forbidden - {$requiredModule} access is not enabled for this account.", 403);
        }
    }

    private function staffLevel(): string
    {
        return match (JWT::getAuthUser()['role'] ?? '') {
            'college_registrar' => 'college',
            'senior_high_registrar' => 'senior_high',
            default => 'all',
        };
    }

    private function windowType(): string
    {
        $role = JWT::getAuthUser()['role'] ?? '';
        if ($role === 'cashier') {
            return 'cashier';
        }

        return $this->publicWindowType();
    }

    private function publicWindowType(): string
    {
        return in_array($_GET['area'] ?? '', ['registrar', 'cashier'], true)
            ? (string) $_GET['area']
            : 'cashier';
    }

    private function isAdmin(): bool
    {
        return (JWT::getAuthUser()['role'] ?? '') === 'admin';
    }

    private function jsonBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
