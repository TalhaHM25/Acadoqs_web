<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\JWT;
use App\Helpers\Response;

class RoleMiddleware
{
    /** Role groups for convenience checks */
    private const GROUPS = [
        'admin'      => ['admin'],
        'registrar'  => ['college_registrar', 'senior_high_registrar'],
        'student'    => ['college_student', 'senior_high_student'],
        'cashier'    => ['cashier'],
        'staff'      => ['admin', 'college_registrar', 'senior_high_registrar', 'cashier'],
    ];

    /**
     * $requiredRole can be an exact role ('admin') or a group name ('staff', 'student').
     */
    public function handle(string $requiredRole): void
    {
        $user = JWT::getAuthUser();

        if (!$user) {
            Response::error('Unauthorized', 401);
        }

        $role    = $user['role'] ?? '';
        $allowed = self::GROUPS[$requiredRole] ?? [$requiredRole];

        if (!in_array($role, $allowed, true)) {
            Response::error('Forbidden — insufficient permissions', 403);
        }
    }
}
