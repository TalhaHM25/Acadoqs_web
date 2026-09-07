<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

class PaymongoService
{
    private const API_BASE = 'https://api.paymongo.com/v1';

    public function createCheckoutSession(array $attributes): array
    {
        return $this->request('POST', '/checkout_sessions', [
            'data' => [
                'attributes' => $attributes,
            ],
        ]);
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        return $this->request('GET', '/checkout_sessions/' . rawurlencode($sessionId));
    }

    public function createRefund(array $attributes): array
    {
        return $this->request('POST', '/refunds', [
            'data' => [
                'attributes' => $attributes,
            ],
        ], 'https://api.paymongo.com');
    }

    private function request(string $method, string $path, ?array $payload = null, ?string $baseUrl = null): array
    {
        $secretKey = (string) Env::require('PAYMONGO_SECRET_KEY');
        $ch = curl_init(($baseUrl ?? self::API_BASE) . $path);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new \RuntimeException('Unable to connect to PayMongo.', 502);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('PayMongo returned an invalid response.', 502);
        }

        if ($status < 200 || $status >= 300) {
            $message = $decoded['errors'][0]['detail']
                ?? $decoded['errors'][0]['message']
                ?? 'PayMongo request failed.';
            throw new \RuntimeException((string) $message, $status >= 400 && $status < 500 ? 422 : 502);
        }

        return $decoded;
    }
}
