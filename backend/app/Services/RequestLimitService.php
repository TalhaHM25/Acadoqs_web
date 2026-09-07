<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\SystemSettingModel;

class RequestLimitService
{
    public const DAILY_LIMIT_KEY = 'daily_request_limit';

    public static function enforceDailyLimit(): void
    {
        $limit = self::dailyLimit();

        if ($limit <= 0) {
            return;
        }

        if (self::requestsToday() >= $limit) {
            throw new \RuntimeException(
                'Daily request limit reached. Students can no longer submit requests today.',
                429
            );
        }
    }

    public static function dailyLimit(): int
    {
        return max(0, (int) SystemSettingModel::get(self::DAILY_LIMIT_KEY, '0'));
    }

    public static function requestsToday(): int
    {
        $row = Database::selectOne(
            'SELECT COUNT(*) AS cnt FROM document_requests WHERE DATE(created_at) = CURDATE()'
        );

        return (int) ($row['cnt'] ?? 0);
    }
}
