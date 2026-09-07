<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Env;
use App\Helpers\Response;
use App\Helpers\Upload;
use App\Models\PaymentMethodModel;

class PaymentMethodController
{
    public function index(): void
    {
        // Admin route → all; public route → active only
        $user = \App\Helpers\JWT::getAuthUser();
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $data = $isAdmin ? PaymentMethodModel::listAll() : PaymentMethodModel::listActive();
        Response::success('Payment methods retrieved.', $data);
    }

    public function serveQr(array $params): void
    {
        $id     = (int) $params['id'];
        $method = PaymentMethodModel::findById($id);

        if (!$method || empty($method['qr_path'])) {
            Response::error('QR not found.', 404);
        }

        $basePath = realpath(Env::get('UPLOAD_PATH', BASE_PATH . '/storage/uploads'));
        if ($basePath === false) {
            Response::error('Storage not configured.', 500);
        }

        $fullPath = realpath($basePath . DIRECTORY_SEPARATOR . $method['qr_path']);

        if ($fullPath === false || !str_starts_with($fullPath, $basePath . DIRECTORY_SEPARATOR) || !is_file($fullPath)) {
            Response::error('QR file not found.', 404);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fullPath);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: public, max-age=3600');
        readfile($fullPath);
        exit;
    }

    public function store(): void
    {
        $data   = $this->body();
        $errors = [];

        if (empty($data['name'])) $errors['name'] = ['Name is required.'];
        if (empty($data['type'])) $errors['type'] = ['Type is required.'];

        if ($errors) Response::error('Validation failed.', 422, $errors);

        $id     = PaymentMethodModel::create($data);
        $method = PaymentMethodModel::findById($id);
        Response::created('Payment method created.', $method);
    }

    public function update(array $params): void
    {
        $id     = (int) $params['id'];
        $data   = $this->body();
        $errors = [];

        if (empty($data['name'])) $errors['name'] = ['Name is required.'];
        if (empty($data['type'])) $errors['type'] = ['Type is required.'];

        if ($errors) Response::error('Validation failed.', 422, $errors);

        PaymentMethodModel::update($id, $data);
        Response::success('Payment method updated.', PaymentMethodModel::findById($id));
    }

    public function uploadQr(array $params): void
    {
        $id = (int) $params['id'];

        if (empty($_FILES['qr']) || $_FILES['qr']['error'] !== UPLOAD_ERR_OK) {
            Response::error('QR image is required.', 422);
        }

        $uploaded = Upload::store('qr', 'settings');
        PaymentMethodModel::updateQr($id, $uploaded['path']);
        Response::success('QR uploaded.', ['qr_path' => $uploaded['path']]);
    }

    public function toggle(array $params): void
    {
        PaymentMethodModel::toggle((int) $params['id']);
        Response::success('Payment method status toggled.');
    }

    public function destroy(array $params): void
    {
        PaymentMethodModel::delete((int) $params['id']);
        Response::success('Payment method deleted.');
    }

    private function body(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
