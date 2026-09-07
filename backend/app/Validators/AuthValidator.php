<?php

declare(strict_types=1);

namespace App\Validators;

class AuthValidator
{
    public static function register(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'][] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }

        if (empty($data['password'])) {
            $errors['password'][] = 'Password is required.';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'][] = 'Password must be at least 8 characters.';
        }

        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'][] = 'Please confirm your password.';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
        }

        if (empty($data['first_name'])) {
            $errors['first_name'][] = 'First name is required.';
        } elseif (!self::isLettersOnly($data['first_name'])) {
            $errors['first_name'][] = 'First name must contain letters only.';
        }

        if (!empty($data['middle_name']) && !self::isLettersOnly($data['middle_name'])) {
            $errors['middle_name'][] = 'Middle name must contain letters only.';
        }

        if (empty($data['last_name'])) {
            $errors['last_name'][] = 'Last name is required.';
        } elseif (!self::isLettersOnly($data['last_name'])) {
            $errors['last_name'][] = 'Last name must contain letters only.';
        }

        if (empty($data['level']) || !in_array($data['level'], ['college', 'senior_high'], true)) {
            $errors['level'][] = 'Please select college or senior_high.';
        }

        if (empty($data['phone_number'])) {
            $errors['phone_number'][] = 'Phone number is required.';
        } elseif (!preg_match('/^639\d{9}$/', $data['phone_number'])) {
            $errors['phone_number'][] = 'Phone number must be exactly 12 digits starting with 639.';
        }

        if (!empty($data['student_id']) && !preg_match('/^\d+$/', $data['student_id'])) {
            $errors['student_id'][] = 'Student ID must contain numbers only.';
        }

        return $errors;
    }

    private static function isLettersOnly(string $value): bool
    {
        return preg_match('/^[A-Za-z ]+$/', trim($value)) === 1;
    }

    public static function login(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'][] = 'Email is required.';
        }

        if (empty($data['password'])) {
            $errors['password'][] = 'Password is required.';
        }

        return $errors;
    }

    public static function forgotPassword(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'][] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        }

        return $errors;
    }

    public static function resetPassword(array $data): array
    {
        $errors = [];

        if (empty($data['token'])) {
            $errors['token'][] = 'Reset token is required.';
        }

        if (empty($data['password'])) {
            $errors['password'][] = 'New password is required.';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'][] = 'Password must be at least 8 characters.';
        }

        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'][] = 'Please confirm your password.';
        } elseif (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
        }

        return $errors;
    }

    public static function changePassword(array $data): array
    {
        $errors = [];

        if (empty($data['current_password'])) {
            $errors['current_password'][] = 'Current password is required.';
        }

        if (empty($data['new_password'])) {
            $errors['new_password'][] = 'New password is required.';
        } elseif (strlen($data['new_password']) < 8) {
            $errors['new_password'][] = 'New password must be at least 8 characters.';
        }

        if (empty($data['new_password_confirmation'])) {
            $errors['new_password_confirmation'][] = 'Please confirm your new password.';
        } elseif (($data['new_password'] ?? '') !== ($data['new_password_confirmation'] ?? '')) {
            $errors['new_password_confirmation'][] = 'Passwords do not match.';
        }

        return $errors;
    }
}
