<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Router;
use App\Helpers\Response;

// Load environment variables
Env::load(BASE_PATH . '/.env');

function allowedCorsOrigin(): string
{
    $configuredOrigins = array_filter(array_map(
        static fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', (string) Env::get('FRONTEND_URL', '*'))
    ));

    if (in_array('*', $configuredOrigins, true)) {
        return '*';
    }

    $requestOrigin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    if ($requestOrigin !== '' && in_array($requestOrigin, $configuredOrigins, true)) {
        return $requestOrigin;
    }

    return $configuredOrigins[0] ?? '*';
}

// Global error handler — always return JSON
set_exception_handler(function (Throwable $e) {
    $exceptionCode = $e->getCode();
    $code = is_int($exceptionCode) && $exceptionCode >= 400 && $exceptionCode < 600
        ? $exceptionCode
        : 500;
    Response::error($e->getMessage(), $code);
});

set_error_handler(function (int $errno, string $errstr) {
    throw new \ErrorException($errstr, 500);
});

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    header('Access-Control-Allow-Origin: ' . allowedCorsOrigin());
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    header('Access-Control-Max-Age: 86400');
    exit;
}

// Set CORS headers for all responses
header('Access-Control-Allow-Origin: ' . allowedCorsOrigin());
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json; charset=UTF-8');

// Boot router and load routes
$router = new Router();
require_once BASE_PATH . '/routes/api.php';
$router->dispatch();
