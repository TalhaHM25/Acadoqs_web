<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Response;
use App\Models\DocumentTypeModel;

class DocumentTypeController
{
    public function index(): void
    {
        $types = DocumentTypeModel::findAll();
        Response::success('Document types retrieved.', $types);
    }

    public function store(): void
    {
        $data   = $this->jsonBody();
        $errors = $this->validate($data);

        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        $data['slug'] = $this->slugify($data['name']);
        $id           = DocumentTypeModel::create($data);
        $type         = DocumentTypeModel::findById($id);

        Response::created('Document type created.', $type);
    }

    public function update(array $params): void
    {
        $id   = (int) $params['id'];
        $data = $this->jsonBody();

        $errors = $this->validate($data);
        if ($errors) {
            Response::error('Validation failed.', 422, $errors);
        }

        $data['slug'] = $this->slugify($data['name']);
        DocumentTypeModel::update($id, $data);

        $type = DocumentTypeModel::findById($id);
        Response::success('Document type updated.', $type);
    }

    public function toggle(array $params): void
    {
        $id = (int) $params['id'];
        DocumentTypeModel::toggle($id);
        Response::success('Status toggled.');
    }

    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'][] = 'Name is required.';
        }

        if (!isset($data['category_id']) || !(int) $data['category_id']) {
            $errors['category_id'][] = 'Category is required.';
        }

        if (!isset($data['base_fee']) || $data['base_fee'] < 0) {
            $errors['base_fee'][] = 'Base fee must be 0 or greater.';
        }

        return $errors;
    }

    private function slugify(string $name): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
    }

    private function jsonBody(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
