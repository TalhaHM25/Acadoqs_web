<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\JWT;
use App\Models\RegistrarPermissionModel;
use App\Models\UserModel;
use App\Models\StudentProfileModel;

class AuthService
{
    public function register(array $data): array
    {
        if (UserModel::emailExists($data['email'])) {
            throw new \RuntimeException('Email is already registered.', 409);
        }

        Database::beginTransaction();

        try {
            $level = in_array($data['level'] ?? '', ['college','senior_high'], true)
                ? $data['level']
                : 'college';
            $role  = $level === 'senior_high' ? 'senior_high_student' : 'college_student';

            $userId = UserModel::create([
                'email'    => strtolower(trim($data['email'])),
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'role'     => $role,
            ]);

            StudentProfileModel::create([
                'user_id'      => $userId,
                'level'        => $level,
                'first_name'   => trim($data['first_name']),
                'middle_name'  => isset($data['middle_name']) ? trim($data['middle_name']) : null,
                'last_name'    => trim($data['last_name']),
                'gender'       => $data['gender']      ?? null,
                'birthday'     => $data['birthday']    ?? null,
                'birthplace'   => $data['birthplace']  ?? null,
                'student_id'   => $data['student_id']  ?? null,
                'phone_number' => isset($data['phone_number']) ? trim($data['phone_number']) : null,
                'address'      => $data['address']     ?? null,
                'program'      => $data['program']     ?? null,
                'year_level'   => $data['year_level']  ?? null,
            ]);

            // Generate email verification token (expires in 24 hours)
            $verificationToken = bin2hex(random_bytes(32));
            $expiresAt         = new \DateTime('+24 hours');
            UserModel::setVerificationToken($userId, $verificationToken, $expiresAt);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        // Send verification email
        $frontendUrl  = rtrim(\App\Core\Env::get('FRONTEND_URL', 'http://localhost:5173'), '/');
        $verifyLink   = "{$frontendUrl}/verify-email?token={$verificationToken}";
        $name         = trim($data['first_name']) . ' ' . trim($data['last_name']);
        \App\Services\EmailService::emailVerification(strtolower(trim($data['email'])), $name, $verifyLink);

        // Do NOT issue a JWT yet — account must be verified first
        return [
            'requires_verification' => true,
            'email'                 => strtolower(trim($data['email'])),
        ];
    }

    public function verifyEmail(string $token): array
    {
        $user = UserModel::findByVerificationToken($token);

        if (!$user) {
            throw new \RuntimeException('This verification link is invalid or has expired.', 422);
        }

        UserModel::markEmailVerified((int) $user['id']);

        // Issue JWT now that email is confirmed
        $jwt = JWT::encode(['user_id' => $user['id'], 'role' => $user['role']]);
        UserModel::updateLastLogin((int) $user['id']);

        return [
            'token' => $jwt,
            'user'  => $this->publicUser($user),
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = UserModel::findByEmail(strtolower(trim($email)));

        if (!$user || !password_verify($password, $user['password'])) {
            throw new \RuntimeException('Invalid email or password.', 401);
        }

        if (!(bool) $user['is_active']) {
            throw new \RuntimeException('Your account has been deactivated.', 403);
        }

        // Registrar/Admin accounts created by admin skip email verification
        $staffRoles = ['admin', 'college_registrar', 'senior_high_registrar', 'cashier'];
        if (!in_array($user['role'], $staffRoles, true) && empty($user['email_verified_at'])) {
            throw new \RuntimeException('Please verify your email address before logging in. Check your inbox for the verification link.', 403);
        }

        UserModel::updateLastLogin($user['id']);

        $token = JWT::encode([
            'user_id' => $user['id'],
            'role'    => $user['role'],
        ]);

        return [
            'token' => $token,
            'user'  => $this->publicUser($user),
        ];
    }

    public function me(int $userId): array
    {
        $user = UserModel::findById($userId);

        if (!$user) {
            throw new \RuntimeException('User not found.', 404);
        }

        $result = $this->publicUser($user);

        $registrarRoles = ['college_registrar', 'senior_high_registrar'];
        $studentRoles   = ['college_student', 'senior_high_student'];

        if (in_array($user['role'], $studentRoles, true)) {
            $result['profile'] = StudentProfileModel::findByUserId($userId);
        }

        if (in_array($user['role'], $registrarRoles, true)) {
            $result['permissions'] = RegistrarPermissionModel::forUser($userId);
        } elseif ($user['role'] === 'cashier') {
            $result['permissions'] = ['cashier'];
        }

        return $result;
    }

    public function resendVerification(string $email): void
    {
        $user = UserModel::findByEmail(strtolower(trim($email)));

        // Silently fail if not found, already verified, or inactive
        if (!$user || !empty($user['email_verified_at']) || !(bool) $user['is_active']) {
            return;
        }

        $verificationToken = bin2hex(random_bytes(32));
        $expiresAt         = new \DateTime('+24 hours');
        UserModel::setVerificationToken((int) $user['id'], $verificationToken, $expiresAt);

        $frontendUrl = rtrim(\App\Core\Env::get('FRONTEND_URL', 'http://localhost:5173'), '/');
        $verifyLink  = "{$frontendUrl}/verify-email?token={$verificationToken}";

        $profile = \App\Models\StudentProfileModel::findByUserId((int) $user['id']);
        $name    = $profile
            ? trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))
            : $user['email'];

        \App\Services\EmailService::emailVerification($user['email'], $name ?: $user['email'], $verifyLink);
    }

    public function forgotPassword(string $email): void
    {
        $user = UserModel::findByEmail(strtolower(trim($email)));

        // Always succeed silently — don't leak whether email exists
        if (!$user || !(bool) $user['is_active']) {
            return;
        }

        $token     = bin2hex(random_bytes(32)); // 64 hex chars
        $expiresAt = new \DateTime('+1 hour');

        UserModel::setResetToken((int) $user['id'], $token, $expiresAt);

        $frontendUrl = rtrim(\App\Core\Env::get('FRONTEND_URL', 'http://localhost:5173'), '/');
        $resetLink   = "{$frontendUrl}/reset-password?token={$token}";
        $name        = $user['email'];

        \App\Services\EmailService::passwordReset($user['email'], $name, $resetLink);
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $user = UserModel::findByResetToken($token);

        if (!$user) {
            throw new \RuntimeException('This reset link is invalid or has expired.', 422);
        }

        UserModel::updatePassword((int) $user['id'], password_hash($newPassword, PASSWORD_BCRYPT));
        UserModel::clearResetToken((int) $user['id']);
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = UserModel::findById($userId);

        if (!$user) {
            throw new \RuntimeException('User not found.', 404);
        }

        if (!password_verify($currentPassword, $user['password'])) {
            throw new \RuntimeException('Current password is incorrect.', 422);
        }

        UserModel::updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
    }

    private function publicUser(array $user): array
    {
        $result = [
            'id'    => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'level' => match($user['role']) {
                'college_student', 'college_registrar' => 'college',
                'senior_high_student', 'senior_high_registrar' => 'senior_high',
                default => null,
            },
        ];

        if (in_array($user['role'], ['college_registrar', 'senior_high_registrar'], true)) {
            $result['permissions'] = RegistrarPermissionModel::forUser((int) $user['id']);
        } elseif ($user['role'] === 'cashier') {
            $result['permissions'] = ['cashier'];
        }

        return $result;
    }
}
