<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\JWT;
use App\Helpers\Response;

class AuthMiddleware
{
    public function handle(): void
    {
        $token = $this->resolveToken();

        if ($token === null) {
            Response::error('Unauthorized — missing token', 401);
        }

        $payload = JWT::decode($token);

        if ($payload === null) {
            Response::error('Unauthorized — invalid or expired token', 401);
        }

        // Inject into global context for downstream access
        $GLOBALS['auth_user'] = $payload;
    }

    private function resolveToken(): ?string
    {
        // Bearer header (primary — used by Axios)
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // ?token= query param (used by CSV export and file download links)
        $query = trim($_GET['token'] ?? '');
        if ($query !== '') {
            return $query;
        }

        return null;
    }
}
