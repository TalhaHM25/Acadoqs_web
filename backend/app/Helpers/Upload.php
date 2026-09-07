<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Env;

class Upload
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Handle a file upload from $_FILES.
     *
     * @param  string $field     $_FILES key
     * @param  string $subfolder e.g. 'payments', 'attachments'
     * @return array{path: string, filename: string, size: int, mime: string}
     * @throws \RuntimeException on validation failure
     */
    public static function store(string $field, string $subfolder = 'uploads'): array
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed or no file provided.', 400);
        }

        $file     = $_FILES[$field];
        $tmpPath  = $file['tmp_name'];
        $origName = basename($file['name']);
        $size     = (int) $file['size'];

        // Validate size
        if ($size > self::MAX_SIZE) {
            throw new \RuntimeException('File exceeds maximum size of 5MB.', 422);
        }

        // Validate MIME (use finfo, not the client-reported type)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException('Invalid file type. Allowed: JPEG, PNG, WebP, PDF.', 422);
        }

        // Build target directory
        $basePath = Env::get('UPLOAD_PATH', BASE_PATH . '/storage/uploads');
        $dir      = "{$basePath}/{$subfolder}/" . date('Y/m');

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Could not create upload directory.', 500);
        }

        // Randomized filename to prevent enumeration
        $ext      = self::extensionFromMime($mime);
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $target   = "{$dir}/{$filename}";

        if (!move_uploaded_file($tmpPath, $target)) {
            throw new \RuntimeException('Failed to save uploaded file.', 500);
        }

        // Return relative path from storage root
        $relativePath = "{$subfolder}/" . date('Y/m') . "/{$filename}";

        return [
            'path'     => $relativePath,
            'filename' => $origName,
            'size'     => $size,
            'mime'     => $mime,
        ];
    }

    private static function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/webp'       => 'webp',
            'application/pdf'  => 'pdf',
            default            => 'bin',
        };
    }

    /**
     * Delete an uploaded file by relative path.
     */
    public static function delete(string $relativePath): bool
    {
        $basePath = realpath(Env::get('UPLOAD_PATH', BASE_PATH . '/storage/uploads'));
        $fullPath = realpath("{$basePath}/{$relativePath}");

        // Prevent path traversal: resolved path must stay inside the upload base
        if ($fullPath === false || !str_starts_with($fullPath, $basePath . DIRECTORY_SEPARATOR)) {
            return false;
        }

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }
}
