<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentRequestModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Models\StudentProfileModel;
use App\Services\SmsService;

/**
 * Central place for all notification messages.
 * Called by RequestService and PaymentService instead of NotificationModel::create() directly.
 */
class NotificationService
{
    public static function requestSubmitted(int $userId, string $requestNumber, int $requestId): void
    {
        NotificationModel::create(
            $userId,
            'request_submitted',
            'Request Submitted',
            "Your request {$requestNumber} has been submitted successfully.",
            ['request_id' => $requestId]
        );

        // Notify all admins
        self::notifyAdmins(
            'new_request',
            'New Request Received',
            "Request {$requestNumber} has been submitted and needs review.",
            ['request_id' => $requestId]
        );

        // Send email to the request's notification email
        [$email, $name] = self::userContact($userId, $requestId);
        EmailService::requestSubmitted($email, $name, $requestNumber, $requestId); // SMS not needed on submission
    }

    public static function paymentReceived(int $userId, string $requestNumber, int $requestId): void
    {
        NotificationModel::create(
            $userId,
            'payment_received',
            'Payment Submitted',
            "Your payment for request {$requestNumber} has been submitted and is awaiting verification.",
            ['request_id' => $requestId]
        );

        // Notify all admins
        self::notifyAdmins(
            'payment_pending',
            'Payment Awaiting Verification',
            "Payment submitted for request {$requestNumber}. Please verify.",
            ['request_id' => $requestId]
        );

        [$email, $name] = self::userContact($userId, $requestId);
        EmailService::paymentReceived($email, $name, $requestNumber);
    }

    public static function paymentVerified(int $userId, string $requestNumber, int $requestId): void
    {
        NotificationModel::create(
            $userId,
            'payment_verified',
            'Payment Verified',
            "Your payment for request {$requestNumber} has been verified.",
            ['request_id' => $requestId]
        );

        [$email, $name] = self::userContact($userId, $requestId);
        EmailService::paymentVerified($email, $name, $requestNumber);
    }

    public static function paymentRejected(int $userId, string $requestNumber, int $requestId, string $reason): void
    {
        NotificationModel::create(
            $userId,
            'payment_rejected',
            'Payment Rejected',
            "Your payment for request {$requestNumber} was rejected. Reason: {$reason}",
            ['request_id' => $requestId]
        );

        [$email, $name, $phone] = self::userContact($userId, $requestId);
        EmailService::paymentRejected($email, $name, $requestNumber, $reason);
        if ($phone) SmsService::paymentRejected($phone, $requestNumber, $reason);
    }

    public static function statusChanged(int $userId, string $requestNumber, string $status, int $requestId, ?string $reason = null): void
    {
        $messages = [
            'payment_verified'  => ['Payment Verified',           "Your payment for request {$requestNumber} has been verified."],
            'completed'         => ['Request Completed',          "Your request {$requestNumber} has been completed. Thank you!"],
            'rejected'          => ['Request Rejected',           "Your request {$requestNumber} has been rejected. Reason: {$reason}"],
        ];

        if (!isset($messages[$status])) {
            return;
        }

        [$title, $message] = $messages[$status];

        NotificationModel::create($userId, "request_{$status}", $title, $message, ['request_id' => $requestId]);

        [$email, $name, $phone] = self::userContact($userId, $requestId);
        EmailService::statusChanged($email, $name, $requestNumber, $status, $reason);
        if ($phone) SmsService::statusChanged($phone, $requestNumber, $status);
    }

    /**
     * Sends a notification to every active admin account.
     */
    private static function notifyAdmins(string $type, string $title, string $message, ?array $data = null): void
    {
        foreach (UserModel::adminIds() as $adminId) {
            NotificationModel::create((int) $adminId, $type, $title, $message, $data);
        }
    }

    /**
     * Returns [email, displayName, phone|null] for a given user.
     * Uses req_email from the request form when available (student may have changed it).
     */
    private static function userContact(int $userId, ?int $requestId = null): array
    {
        $user  = UserModel::findById($userId);
        $email = $user['email'] ?? '';

        if ($requestId !== null) {
            $request = DocumentRequestModel::findById($requestId);
            if (!empty($request['req_email'])) {
                $email = $request['req_email'];
            }
        }

        $profile = StudentProfileModel::findByUserId($userId);
        $name    = $profile
            ? trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))
            : $email;
        $phone   = $profile['phone_number'] ?? null;

        return [$email, $name ?: $email, $phone ?: null];
    }
}
