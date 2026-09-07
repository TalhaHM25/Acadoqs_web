<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Env;
use App\Helpers\JWT;
use App\Helpers\Response;

/**
 * Serves uploaded files that live outside the web root (backend/storage/).
 * Accepts JWT via Authorization header OR ?token= query param
 * (query param is necessary for direct browser navigation in <a href>).
 */
class FileController
{
    public function serve(): void
    {
        // Authenticate — accept Bearer header OR ?token query param
        $token = $this->resolveToken();
        if (!$token) {
            Response::error('Unauthorized', 401);
        }

        $payload  = JWT::decode($token);
        $staffRoles = ['admin', 'college_registrar', 'senior_high_registrar'];
        if (!$payload || !in_array($payload['role'] ?? '', $staffRoles, true)) {
            Response::error('Forbidden', 403);
        }

        // Resolve the requested path
        $relativePath = $_GET['path'] ?? '';
        if ($relativePath === '') {
            Response::error('Path is required.', 400);
        }

        $basePath = realpath(Env::get('UPLOAD_PATH', BASE_PATH . '/storage/uploads'));
        if ($basePath === false) {
            Response::error('Storage not configured.', 500);
        }

        $fullPath = realpath($basePath . DIRECTORY_SEPARATOR . $relativePath);

        // Path traversal guard
        if (
            $fullPath === false
            || !str_starts_with($fullPath, $basePath . DIRECTORY_SEPARATOR)
            || !is_file($fullPath)
        ) {
            Response::error('File not found.', 404);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fullPath);

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
        readfile($fullPath);
        exit;
    }

    private function resolveToken(): ?string
    {
        // Authorization: Bearer <token>
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        // ?token=<token>
        $query = $_GET['token'] ?? '';
        if ($query !== '') {
            return $query;
        }

        return null;
    }
}
