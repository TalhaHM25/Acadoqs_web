<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * Sends SMS via PhilSMS (https://philsms.com).
 * Silently skipped when SMS_API_KEY is empty.
 */
class SmsService
{
    private static bool $enabled;

    private static function isEnabled(): bool
    {
        if (!isset(self::$enabled)) {
            self::$enabled = !empty(Env::get('SMS_API_KEY'));
        }
        return self::$enabled;
    }

    /**
     * Format a Philippine mobile number to 09XXXXXXXXX.
     * Accepts: 09XX, +639XX, 639XX
     */
    private static function formatNumber(string $number): string
    {
        $clean = preg_replace('/\D/', '', $number);
        if (str_starts_with($clean, '09') && strlen($clean) === 11) {
            return '63' . substr($clean, 1);
        }
        return $clean;
    }

    public static function send(string $recipient, string $message): void
    {
        if (!self::isEnabled()) {
            error_log('[SmsService] SMS skipped: SMS_API_KEY is not configured.');
            return;
        }

        if (!function_exists('curl_init')) {
            error_log('[SmsService] SMS skipped: PHP cURL extension is not enabled.');
            return;
        }

        $recipient = self::formatNumber($recipient);

        if (strlen($recipient) !== 12 || !str_starts_with($recipient, '639')) {
            error_log('[SmsService] Invalid recipient number: ' . $recipient);
            return;
        }

        $payload = json_encode([
            'sender_id' => Env::get('SMS_SENDER_ID', 'PhilSMS'),
            'recipient' => $recipient,
            'message'   => $message,
        ]);

        $ch = curl_init('https://dashboard.philsms.com/api/v3/sms/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . Env::get('SMS_API_KEY'),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => $payload,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            error_log('[SmsService] cURL error: ' . $error);
            return;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[SmsService] Provider rejected SMS. HTTP ' . $httpCode . ' Response: ' . substr((string) $response, 0, 500));
        }
    }

    // -------------------------------------------------------
    // Named senders — called from NotificationService
    // -------------------------------------------------------

    public static function paymentVerified(string $phone, string $requestNumber): void
    {
        self::send($phone, "AcaDocs: Your payment for request {$requestNumber} has been verified and is now being processed.");
    }

    public static function paymentVerifiedWithEstimate(string $phone, string $requestNumber, int $processingDays, string $releaseDate): void
    {
        $dayLabel = $processingDays === 1 ? 'day' : 'days';
        self::send(
            $phone,
            "AcaDocs: Payment for request {$requestNumber} has been verified. Your document is now being processed. Estimated release: {$releaseDate} ({$processingDays} {$dayLabel})."
        );
    }

    public static function requestSubmitted(string $phone, string $requestNumber, ?string $queueNumber = null, ?float $totalFee = null): void
    {
        $message = "AcaDocs: Your request {$requestNumber} has been submitted.";

        if ($queueNumber) {
            $message .= " Queue number: {$queueNumber}.";
        }

        if ($totalFee !== null) {
            $message .= ' Total fee: PHP ' . number_format($totalFee, 2) . '.';
        }

        self::send($phone, $message);
    }

    public static function requestReleaseEstimate(string $phone, string $requestNumber, int $processingDays, string $releaseDate): void
    {
        $dayLabel = $processingDays === 1 ? 'day' : 'days';
        self::send(
            $phone,
            "AcaDocs: Request {$requestNumber} received. Estimated release is {$releaseDate} ({$processingDays} {$dayLabel} processing time)."
        );
    }

    public static function readyForRelease(string $phone, string $requestNumber): void
    {
        self::send($phone, "AcaDocs: Your document for request {$requestNumber} is ready for release. Please visit the registrar's office to claim it.");
    }

    public static function paymentRejected(string $phone, string $requestNumber, string $reason): void
    {
        self::send($phone, "AcaDocs: Your payment for request {$requestNumber} was rejected. Reason: {$reason}. Please re-submit.");
    }

    public static function statusChanged(string $phone, string $requestNumber, string $status): void
    {
        $messages = [
            'payment_verified'  => "AcaDocs: Your payment for request {$requestNumber} has been verified and is now being processed.",
            'completed'         => "AcaDocs: Your document for request {$requestNumber} is ready for release. Please visit the registrar's office to claim it.",
            'rejected'          => "AcaDocs: Request {$requestNumber} has been rejected. Please check your account for details.",
        ];

        if (isset($messages[$status])) {
            self::send($phone, $messages[$status]);
        }
    }
}
