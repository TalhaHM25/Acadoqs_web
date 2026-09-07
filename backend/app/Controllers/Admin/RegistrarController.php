<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Helpers\Response;
use App\Models\RegistrarPermissionModel;
use App\Models\StudentProfileModel;
use App\Models\UserModel;

class RegistrarController
{
    /** GET /api/admin/registrars */
    public function index(): void
    {
        $rows = Database::select(
            "SELECT u.id, u.email, u.role, u.is_active, u.created_at,
                    u.last_login_at
             FROM users u
             WHERE u.role IN ('college_registrar','senior_high_registrar','cashier')
             ORDER BY u.created_at DESC",
            []
        );

        foreach ($rows as &$row) {
            $row['permissions'] = $row['role'] === 'cashier'
                ? ['cashier']
                : RegistrarPermissionModel::forUser((int) $row['id']);
        }

        Response::success('Staff accounts retrieved.', $rows);
    }

    /** POST /api/admin/registrars */
    public function store(): void
    {
        $data   = $this->body();
        $errors = [];

        if (empty($data['email']))    $errors['email']    = ['Email is required.'];
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                                      $errors['email']    = ['Invalid email format.'];
        if (empty($data['password'])) $errors['password'] = ['Password is required.'];
        elseif (strlen($data['password']) < 8)
                                      $errors['password'] = ['Password must be at least 8 characters.'];
        if (empty($data['role']) || !in_array($data['role'], ['college_registrar','senior_high_registrar','cashier'], true))
                                      $errors['role']     = ['Role must be college_registrar, senior_high_registrar, or cashier.'];

        if ($errors) Response::error('Validation failed.', 422, $errors);

        if (UserModel::emailExists(strtolower(trim($data['email'])))) {
            Response::error('Email is already registered.', 409);
        }

        Database::beginTransaction();
        try {
            $userId = UserModel::create([
                'email'    => strtolower(trim($data['email'])),
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'role'     => $data['role'],
            ]);

            // Mark email as verified (admin-created accounts skip email verification)
            Database::execute(
                "UPDATE users SET email_verified_at = NOW() WHERE id = ?",
                [$userId]
            );

            if (($data['role'] ?? '') !== 'cashier' && !empty($data['permissions']) && is_array($data['permissions'])) {
                RegistrarPermissionModel::setModules($userId, $data['permissions']);
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        Response::created('Staff account created.', [
            'id'          => $userId,
            'email'       => strtolower(trim($data['email'])),
            'role'        => $data['role'],
            'permissions' => $data['role'] === 'cashier' ? ['cashier'] : ($data['permissions'] ?? []),
        ]);
    }

    /** PUT /api/admin/registrars/{id} — update role + permissions */
    public function update(array $params): void
    {
        $id   = (int) $params['id'];
        $data = $this->body();

        $this->assertStaff($id);

        if (!empty($data['role']) && in_array($data['role'], ['college_registrar','senior_high_registrar','cashier'], true)) {
            Database::execute("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?", [$data['role'], $id]);
            if ($data['role'] === 'cashier') {
                RegistrarPermissionModel::deleteForUser($id);
            }
        }

        if (($data['role'] ?? '') !== 'cashier' && isset($data['permissions']) && is_array($data['permissions'])) {
            RegistrarPermissionModel::setModules($id, $data['permissions']);
        }

        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                Response::error('Password must be at least 8 characters.', 422);
            }
            Database::execute(
                "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
                [password_hash($data['password'], PASSWORD_BCRYPT), $id]
            );
        }

        Response::success('Staff account updated.');
    }

    /** PATCH /api/admin/registrars/{id}/toggle */
    public function toggle(array $params): void
    {
        $id = (int) $params['id'];
        $this->assertStaff($id);
        Database::execute(
            "UPDATE users SET is_active = IF(is_active=1,0,1), updated_at = NOW() WHERE id = ?",
            [$id]
        );
        Response::success('Staff account status toggled.');
    }

    /** DELETE /api/admin/registrars/{id} */
    public function destroy(array $params): void
    {
        $id = (int) $params['id'];
        $this->assertStaff($id);
        RegistrarPermissionModel::deleteForUser($id);
        Database::execute("DELETE FROM users WHERE id = ?", [$id]);
        Response::success('Staff account deleted.');
    }

    private function assertStaff(int $id): void
    {
        $user = UserModel::findById($id);
        if (!$user || !in_array($user['role'], ['college_registrar','senior_high_registrar','cashier'], true)) {
            Response::error('Staff account not found.', 404);
        }
    }

    private function body(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
