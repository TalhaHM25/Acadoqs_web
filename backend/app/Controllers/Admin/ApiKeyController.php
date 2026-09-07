<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Response;
use App\Models\ApiKeyModel;

class ApiKeyController
{
    public function index(): void
    {
        Response::success('API keys retrieved.', ApiKeyModel::listAll());
    }

    public function store(): void
    {
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $errors = [];

        if (empty($data['name'])) $errors['name'] = ['Name is required.'];
        if (empty($data['type']) || !in_array($data['type'], ['kiosk','mobile'], true))
            $errors['type'] = ['Type must be kiosk or mobile.'];

        if ($errors) Response::error('Validation failed.', 422, $errors);

        $result = ApiKeyModel::create($data);

        // Return the full key ONCE — it will never be shown again
        Response::created('API key created. Copy the key now — it will not be shown again.', [
            'id'        => $result['id'],
            'key_value' => $result['key_value'],
            'name'      => $data['name'],
            'type'      => $data['type'],
        ]);
    }

    public function toggle(array $params): void
    {
        ApiKeyModel::toggle((int) $params['id']);
        Response::success('API key status toggled.');
    }

    public function destroy(array $params): void
    {
        ApiKeyModel::delete((int) $params['id']);
        Response::success('API key deleted.');
    }
}
