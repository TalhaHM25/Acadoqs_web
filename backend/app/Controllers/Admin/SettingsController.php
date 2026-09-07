<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Env;
use App\Helpers\JWT;
use App\Helpers\Response;
use App\Helpers\Upload;
use App\Models\SystemSettingModel;

class SettingsController
{
    public function index(): void
    {
        $settings = SystemSettingModel::all();
        // Mask the GCash QR path — expose URL instead
        Response::success('Settings retrieved.', $settings);
    }

    public function update(): void
    {
        $data         = $this->jsonBody();
        $user         = JWT::getAuthUser();
        $allowedKeys  = ['daily_request_limit'];

        if (($user['role'] ?? '') === 'admin') {
            $allowedKeys = [
                'gcash_name',
                'gcash_number',
                'school_name',
                'support_email',
                'support_phone',
                'daily_request_limit',
            ];
        }

        $filteredData = array_filter(
            $data,
            fn($key) => in_array($key, $allowedKeys, true),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($filteredData)) {
            Response::error('No valid settings provided.', 400);
        }

        if (array_key_exists('daily_request_limit', $filteredData)) {
            $limit = trim((string) $filteredData['daily_request_limit']);
            if ($limit === '') {
                $limit = '0';
            }

            if (!ctype_digit($limit)) {
                Response::error('Daily request limit must be a whole number.', 422, [
                    'daily_request_limit' => ['Daily request limit must be a whole number.'],
                ]);
            }

            $filteredData['daily_request_limit'] = (string) max(0, (int) $limit);
        }

        SystemSettingModel::bulkSet($filteredData);
        Response::success('Settings updated.');
    }

    public function uploadGcashQr(): void
    {
        if (empty($_FILES['qr']) || $_FILES['qr']['error'] !== UPLOAD_ERR_OK) {
            Response::error('No QR file uploaded.', 400);
        }

        $uploaded = Upload::store('qr', 'settings');
        SystemSettingModel::set('gcash_qr', $uploaded['path']);

        Response::success('GCash QR uploaded.', ['path' => $uploaded['path']]);
    }

    private function jsonBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
