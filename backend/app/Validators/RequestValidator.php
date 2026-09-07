<?php

declare(strict_types=1);

namespace App\Validators;

class RequestValidator
{
    /**
     * Validate a multi-document request submission.
     */
    public static function create(array $data): array
    {
        $errors = [];

        // Items array
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors['items'][] = 'At least one document must be selected.';
        } else {
            foreach ($data['items'] as $i => $item) {
                if (empty($item['document_type_id'])) {
                    $errors["items.{$i}.document_type_id"][] = 'Document type is required.';
                }
                $copies = (int) ($item['copies'] ?? 1);
                if ($copies < 1 || $copies > 20) {
                    $errors["items.{$i}.copies"][] = 'Copies must be between 1 and 20.';
                }
                $torYear = $item['special_data']['tor_year'] ?? null;
                if ($torYear !== null && !preg_match('/^\d{4}$/', (string) $torYear)) {
                    $errors["item_{$i}_tor_year"][] = 'Year of graduation must be a 4-digit number.';
                }
            }
        }

        // Common fields
        if (empty($data['purpose'])) {
            $errors['purpose'][] = 'Purpose is required.';
        } elseif (strlen($data['purpose']) > 500) {
            $errors['purpose'][] = 'Purpose must not exceed 500 characters.';
        }

        if (empty($data['req_last_name'])) {
            $errors['req_last_name'][] = 'Last name is required.';
        } elseif (!self::isLettersOnly($data['req_last_name'])) {
            $errors['req_last_name'][] = 'Last name must contain letters only.';
        }

        if (empty($data['req_first_name'])) {
            $errors['req_first_name'][] = 'First name is required.';
        } elseif (!self::isLettersOnly($data['req_first_name'])) {
            $errors['req_first_name'][] = 'First name must contain letters only.';
        }

        if (!empty($data['req_middle_name']) && !self::isLettersOnly($data['req_middle_name'])) {
            $errors['req_middle_name'][] = 'Middle name must contain letters only.';
        }

        if (!empty($data['req_student_id']) && !preg_match('/^\d+$/', (string) $data['req_student_id'])) {
            $errors['req_student_id'][] = 'ID number must contain numbers only.';
        }

        if (!empty($data['original_name']) && !self::isLettersOnly($data['original_name'])) {
            $errors['original_name'][] = 'Original name must contain letters only.';
        }

        if (!empty($data['graduation_date']) && !preg_match('/^\d{4}$/', (string) $data['graduation_date'])) {
            $errors['graduation_date'][] = 'Graduation year must be a 4-digit number.';
        }

        if (empty($data['req_email'])) {
            $errors['req_email'][] = 'Email is required.';
        } elseif (!filter_var($data['req_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['req_email'][] = 'Please enter a valid email address.';
        }

        if (empty($data['req_phone'])) {
            $errors['req_phone'][] = 'Phone number is required.';
        } elseif (!preg_match('/^639\d{9}$/', $data['req_phone'])) {
            $errors['req_phone'][] = 'Phone number must be exactly 12 digits starting with 639.';
        }

        if (empty($data['grade_level'])) {
            $errors['grade_level'][] = 'Grade level is required.';
        }

        if (empty($data['request_semester'])) {
            $errors['request_semester'][] = 'Request semester is required.';
        } elseif (!in_array($data['request_semester'], ['1st', '2nd'], true)) {
            $errors['request_semester'][] = 'Request semester must be 1st or 2nd.';
        }

        // Representative
        if (!empty($data['is_representative'])) {
            if (empty($data['rep_name'])) {
                $errors['rep_name'][] = 'Representative name is required.';
            } elseif (!self::isLettersOnly($data['rep_name'])) {
                $errors['rep_name'][] = 'Representative name must contain letters only.';
            }
            if (empty($data['rep_relationship'])) {
                $errors['rep_relationship'][] = 'Relationship is required.';
            }
        }

        if (!isset($_FILES['identity_proof']) || $_FILES['identity_proof']['error'] !== UPLOAD_ERR_OK) {
            $errors['identity_proof'][] = 'Identity proof photo is required.';
        }

        return $errors;
    }

    private static function isLettersOnly(string $value): bool
    {
        return preg_match('/^[A-Za-z ]+$/', trim($value)) === 1;
    }
}
