<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Models\StudentProfileModel;
use App\Models\UserModel;
use App\Models\ProgramModel;

class ProfileController
{
    public function show(): void
    {
        $userId  = $this->authUserId();
        $profile = StudentProfileModel::findByUserId($userId);
        $user    = UserModel::findById($userId);

        if (!$profile) {
            Response::error('Profile not found.', 404);
        }

        $profile['email'] = $user['email'];
        Response::success('Profile retrieved.', $profile);
    }

    public function update(): void
    {
        $data   = $this->jsonBody();
        $userId = $this->authUserId();
        $errors = $this->validate($data);

        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        StudentProfileModel::updateByUserId($userId, $data);

        $profile          = StudentProfileModel::findByUserId($userId);
        $user             = UserModel::findById($userId);
        $profile['email'] = $user['email'];

        Response::success('Profile updated successfully.', $profile);
    }

    public function programs(): void
    {
        Response::success('Programs retrieved.', ProgramModel::all());
    }

    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['first_name'])) {
            $errors['first_name'][] = 'First name is required.';
        } elseif (!preg_match('/^[A-Za-z ]+$/', trim((string) $data['first_name']))) {
            $errors['first_name'][] = 'First name must contain letters only.';
        }

        if (!empty($data['middle_name']) && !preg_match('/^[A-Za-z ]+$/', trim((string) $data['middle_name']))) {
            $errors['middle_name'][] = 'Middle name must contain letters only.';
        }

        if (empty($data['last_name'])) {
            $errors['last_name'][] = 'Last name is required.';
        } elseif (!preg_match('/^[A-Za-z ]+$/', trim((string) $data['last_name']))) {
            $errors['last_name'][] = 'Last name must contain letters only.';
        }

        if (!empty($data['student_id']) && !preg_match('/^\d+$/', (string) $data['student_id'])) {
            $errors['student_id'][] = 'Student ID must contain numbers only.';
        }

        if (!empty($data['birthday']) && !strtotime($data['birthday'])) {
            $errors['birthday'][] = 'Invalid date format.';
        }

        if (!empty($data['gender']) && !in_array($data['gender'], ['male', 'female', 'other'], true)) {
            $errors['gender'][] = 'Invalid gender value.';
        }

        if (empty($data['phone_number'])) {
            $errors['phone_number'][] = 'Phone number is required.';
        } elseif (!preg_match('/^639\d{9}$/', $data['phone_number'])) {
            $errors['phone_number'][] = 'Phone number must be exactly 12 digits starting with 639.';
        }

        return $errors;
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
