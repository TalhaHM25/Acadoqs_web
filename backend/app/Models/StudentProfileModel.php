<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class StudentProfileModel
{
    public static function findByUserId(int $userId): ?array
    {
        return Database::selectOne(
            'SELECT sp.* FROM student_profiles sp WHERE sp.user_id = ? LIMIT 1',
            [$userId]
        );
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO student_profiles
             (user_id, level, student_id, first_name, middle_name, last_name, suffix,
              gender, birthday, birthplace, phone_number, address, program, year_level)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'],
                $data['level']        ?? 'college',
                $data['student_id']   ?? null,
                $data['first_name'],
                $data['middle_name']  ?? null,
                $data['last_name'],
                $data['suffix']       ?? null,
                $data['gender']       ?? null,
                $data['birthday']     ?? null,
                $data['birthplace']   ?? null,
                $data['phone_number'] ?? null,
                $data['address']      ?? null,
                $data['program']      ?? null,
                $data['year_level']   ?? null,
            ]
        );
    }

    public static function updateByUserId(int $userId, array $data): void
    {
        Database::execute(
            'UPDATE student_profiles SET
               student_id   = ?,
               first_name   = ?,
               middle_name  = ?,
               last_name    = ?,
               suffix       = ?,
               gender       = ?,
               birthday     = ?,
               birthplace   = ?,
               phone_number = ?,
               address      = ?,
               program      = ?,
               year_level   = ?,
               updated_at   = NOW()
             WHERE user_id  = ?',
            [
                $data['student_id']   ?? null,
                $data['first_name'],
                $data['middle_name']  ?? null,
                $data['last_name'],
                $data['suffix']       ?? null,
                $data['gender']       ?? null,
                $data['birthday']     ?? null,
                $data['birthplace']   ?? null,
                $data['phone_number'] ?? null,
                $data['address']      ?? null,
                $data['program']      ?? null,
                $data['year_level']   ?? null,
                $userId,
            ]
        );
    }
}
