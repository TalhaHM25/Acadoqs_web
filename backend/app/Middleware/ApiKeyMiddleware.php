<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;
use App\Models\ApiKeyModel;

/**
 * Authenticates kiosk / mobile API requests via X-API-Key header.
 * No user JWT required — grants walk-in access to public endpoints.
 */
class ApiKeyMiddleware
{
    public function handle(): void
    {
        $key = trim($_SERVER['HTTP_X_API_KEY'] ?? '');

        if ($key === '') {
            Response::error('API key is required. Send it as X-API-Key header.', 401);
        }

        $apiKey = ApiKeyModel::findByKey($key);

        if (!$apiKey) {
            Response::error('Invalid or inactive API key.', 401);
        }

        ApiKeyModel::updateLastUsed((int) $apiKey['id']);

        // Expose to downstream controllers
        $GLOBALS['api_key'] = $apiKey;
    }
}
