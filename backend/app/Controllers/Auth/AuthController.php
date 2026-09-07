<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Services\AuthService;
use App\Validators\AuthValidator;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function register(): void
    {
        $data   = $this->jsonBody();
        $errors = AuthValidator::register($data);

        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        $result = $this->authService->register($data);
        Response::created('Registration successful.', $result);
    }

    public function login(): void
    {
        $this->enforceRateLimit();

        $data   = $this->jsonBody();
        $errors = AuthValidator::login($data);

        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        $result = $this->authService->login($data['email'], $data['password']);
        $this->clearRateLimit();
        Response::success('Login successful.', $result);
    }

    public function logout(): void
    {
        // JWT is stateless — client simply discards the token.
        Response::success('Logged out successfully.');
    }

    public function me(): void
    {
        $userId = $this->authUserId();
        $result = $this->authService->me($userId);
        Response::success('User retrieved.', $result);
    }

    public function changePassword(): void
    {
        $data   = $this->jsonBody();
        $errors = AuthValidator::changePassword($data);

        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        $userId = $this->authUserId();
        $this->authService->changePassword(
            $userId,
            $data['current_password'],
            $data['new_password']
        );

        Response::success('Password changed successfully.');
    }

    public function verifyEmail(): void
    {
        $token = $_GET['token'] ?? '';

        if (!$token) {
            Response::error('Verification token is required.', 422);
        }

        $result = $this->authService->verifyEmail($token);
        Response::success('Email verified successfully. You are now logged in.', $result);
    }

    public function resendVerification(): void
    {
        $data = $this->jsonBody();

        if (empty($data['email'])) {
            Response::error('Email is required.', 422);
        }

        $this->authService->resendVerification($data['email']);
        Response::success('If your account exists and is unverified, a new verification email has been sent.');
    }

    public function forgotPassword(): void
    {
        $data   = $this->jsonBody();
        $errors = AuthValidator::forgotPassword($data);

        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        // Always returns 200 — never reveal whether email exists
        $this->authService->forgotPassword($data['email']);
        Response::success('If an account with that email exists, a reset link has been sent.');
    }

    public function resetPassword(): void
    {
        $data   = $this->jsonBody();
        $errors = AuthValidator::resetPassword($data);

        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        $this->authService->resetPassword($data['token'], $data['password']);
        Response::success('Password reset successfully. You can now log in.');
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function jsonBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function authUserId(): int
    {
        return (int) (JWT::getAuthUser()['user_id'] ?? 0);
    }

    private function rateLimitFile(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return sys_get_temp_dir() . '/drs_login_' . md5($ip) . '.json';
    }

    private function enforceRateLimit(): void
    {
        $file    = $this->rateLimitFile();
        $now     = time();
        $window  = 15 * 60; // 15 minutes
        $maxTries = 5;

        $attempts = file_exists($file)
            ? (json_decode(file_get_contents($file), true) ?? [])
            : [];

        // Drop attempts outside the window
        $attempts = array_values(array_filter($attempts, fn($t) => $now - $t < $window));

        if (count($attempts) >= $maxTries) {
            $retryAfter = $window - ($now - $attempts[0]);
            Response::error(
                'Too many login attempts. Please try again in ' . ceil($retryAfter / 60) . ' minute(s).',
                429
            );
        }

        $attempts[] = $now;
        file_put_contents($file, json_encode($attempts), LOCK_EX);
    }

    private function clearRateLimit(): void
    {
        $file = $this->rateLimitFile();
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
