<?php

declare(strict_types=1);

namespace App\Helpers;

class Response
{
    public static function success(
        string $message,
        mixed  $data    = null,
        int    $code    = 200,
        ?array $meta    = null
    ): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');

        $body = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $body['data'] = $data;
        }

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(
        string $message,
        int    $code   = 400,
        array  $errors = []
    ): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');

        $body = ['success' => false, 'message' => $message];

        if (!empty($errors)) {
            $body['errors'] = $errors;
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function created(string $message, mixed $data = null): void
    {
        self::success($message, $data, 201);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    public static function paginated(
        string $message,
        array  $data,
        int    $total,
        int    $perPage,
        int    $currentPage
    ): void {
        self::success($message, $data, 200, [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'last_page'    => (int) ceil($total / $perPage),
        ]);
    }
}
