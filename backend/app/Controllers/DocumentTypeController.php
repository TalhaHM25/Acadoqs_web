<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Models\DocumentTypeModel;
use App\Models\SystemSettingModel;
use App\Core\Env;

class DocumentTypeController
{
    public function categories(): void
    {
        $categories = DocumentTypeModel::categoriesWithTypes($this->resolveLevel());
        Response::success('Categories retrieved.', $categories);
    }

    public function index(): void
    {
        $types = DocumentTypeModel::allActive($this->resolveLevel());
        Response::success('Document types retrieved.', $types);
    }

    private function resolveLevel(): ?string
    {
        // ?level= query param (kiosk / mobile)
        if (!empty($_GET['level']) && in_array($_GET['level'], ['college','senior_high'], true)) {
            return $_GET['level'];
        }
        // Derive from JWT role
        $user = \App\Helpers\JWT::getAuthUser();
        return match($user['role'] ?? '') {
            'college_student', 'college_registrar'           => 'college',
            'senior_high_student', 'senior_high_registrar'   => 'senior_high',
            default                                           => null,
        };
    }

    public function show(array $params): void
    {
        $type = DocumentTypeModel::findById((int) $params['id']);

        if (!$type) {
            Response::error('Document type not found.', 404);
        }

        Response::success('Document type retrieved.', $type);
    }

    public function gcashInfo(): void
    {
        Response::success('GCash info retrieved.', [
            'name'   => SystemSettingModel::get('gcash_name', ''),
            'number' => SystemSettingModel::get('gcash_number', ''),
            'has_qr' => (bool) SystemSettingModel::get('gcash_qr'),
        ]);
    }

    public function gcashQr(): void
    {
        $qrPath = SystemSettingModel::get('gcash_qr');

        if (!$qrPath) {
            Response::error('GCash QR not configured.', 404);
        }

        $basePath = Env::get('UPLOAD_PATH', BASE_PATH . '/storage/uploads');
        $fullPath = "{$basePath}/{$qrPath}";

        if (!file_exists($fullPath)) {
            Response::error('QR image not found.', 404);
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fullPath);

        header("Content-Type: {$mimeType}");
        header('Cache-Control: public, max-age=3600');
        readfile($fullPath);
        exit;
    }
}
