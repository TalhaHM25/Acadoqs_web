<?php

declare(strict_types=1);

namespace App\Validators;

class PaymentValidator
{
    public static function submit(array $data): array
    {
        $errors = [];

        $hasProof      = !empty($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK;
        $hasReference  = !empty($data['reference_number']);

        if (!$hasReference && !$hasProof) {
            $errors['reference_number'][] = 'Please provide a GCash reference number or upload a payment screenshot.';
        }

        if ($hasReference && !preg_match('/^\d{6,20}$/', $data['reference_number'])) {
            $errors['reference_number'][] = 'Reference number must be 6–20 digits.';
        }

        return $errors;
    }
}
