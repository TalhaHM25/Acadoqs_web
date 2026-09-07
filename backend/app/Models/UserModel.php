<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class UserModel
{
    public static function findById(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::selectOne('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO users (email, password, role) VALUES (?, ?, ?)',
            [
                $data['email'],
                $data['password'],
                $data['role'] ?? 'student',
            ]
        );
    }

    public static function updateLastLogin(int $id): void
    {
        Database::execute(
            'UPDATE users SET last_login_at = NOW() WHERE id = ?',
            [$id]
        );
    }

    public static function updatePassword(int $id, string $hashedPassword): void
    {
        Database::execute(
            'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?',
            [$hashedPassword, $id]
        );
    }

    public static function emailExists(string $email): bool
    {
        $row = Database::selectOne(
            'SELECT id FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        return $row !== null;
    }

    public static function setResetToken(int $id, string $token, \DateTime $expiresAt): void
    {
        Database::execute(
            'UPDATE users SET reset_token = ?, reset_token_expires_at = ?, updated_at = NOW() WHERE id = ?',
            [$token, $expiresAt->format('Y-m-d H:i:s'), $id]
        );
    }

    public static function findByResetToken(string $token): ?array
    {
        return Database::selectOne(
            'SELECT * FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW() LIMIT 1',
            [$token]
        );
    }

    public static function clearResetToken(int $id): void
    {
        Database::execute(
            'UPDATE users SET reset_token = NULL, reset_token_expires_at = NULL, updated_at = NOW() WHERE id = ?',
            [$id]
        );
    }

    public static function setVerificationToken(int $id, string $token, \DateTime $expiresAt): void
    {
        Database::execute(
            'UPDATE users SET email_verification_token = ?, email_verification_expires_at = ?, updated_at = NOW() WHERE id = ?',
            [$token, $expiresAt->format('Y-m-d H:i:s'), $id]
        );
    }

    public static function findByVerificationToken(string $token): ?array
    {
        return Database::selectOne(
            'SELECT * FROM users WHERE email_verification_token = ? AND email_verification_expires_at > NOW() LIMIT 1',
            [$token]
        );
    }

    public static function markEmailVerified(int $id): void
    {
        Database::execute(
            'UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL, updated_at = NOW() WHERE id = ?',
            [$id]
        );
    }

    /** Returns all active admin user IDs. */
    public static function adminIds(): array
    {
        $rows = Database::select(
            "SELECT id FROM users WHERE role = 'admin' AND is_active = 1",
            []
        );
        return array_column($rows, 'id');
    }
}
