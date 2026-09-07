<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Sends transactional emails via SMTP (PHPMailer).
 * Silently disabled when MAIL_HOST is empty — in-app notifications still fire.
 */
class EmailService
{
    private static bool $enabled;

    private static function isEnabled(): bool
    {
        if (!isset(self::$enabled)) {
            self::$enabled = !empty(Env::get('MAIL_HOST'));
        }
        return self::$enabled;
    }

    /**
     * Send an email. Fails silently — email errors must never break the main flow.
     *
     * @param string $toAddress Recipient email
     * @param string $toName    Recipient display name
     * @param string $subject
     * @param string $htmlBody  HTML content
     */
    public static function send(string $toAddress, string $toName, string $subject, string $htmlBody): void
    {
        if (!self::isEnabled()) {
            return;
        }

        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = Env::get('MAIL_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = Env::get('MAIL_USERNAME');
            $mail->Password   = Env::get('MAIL_PASSWORD');
            $mail->SMTPSecure = Env::get('MAIL_ENCRYPTION', 'tls');
            $mail->Port       = (int) Env::get('MAIL_PORT', '587');

            $fromAddress = Env::get('MAIL_FROM_ADDRESS', 'noreply@school.edu');
            $fromName    = Env::get('MAIL_FROM_NAME', 'AcaDocs');

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($toAddress, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = self::wrapHtml($subject, $htmlBody);
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
        } catch (MailerException $e) {
            error_log('[EmailService] Failed to send email to ' . $toAddress . ': ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // Named senders (call from NotificationService)
    // -------------------------------------------------------

    public static function requestSubmitted(string $toEmail, string $toName, string $requestNumber, int $requestId): void
    {
        self::send(
            $toEmail,
            $toName,
            "Request {$requestNumber} Submitted",
            "<p>Hi <strong>{$toName}</strong>,</p>
            <p>Your document request <strong>{$requestNumber}</strong> has been submitted successfully.</p>
            <p>You will receive updates as your request is processed.</p>"
        );
    }

    public static function paymentReceived(string $toEmail, string $toName, string $requestNumber): void
    {
        self::send(
            $toEmail,
            $toName,
            "Payment Submitted for {$requestNumber}",
            "<p>Hi <strong>{$toName}</strong>,</p>
            <p>Your payment for request <strong>{$requestNumber}</strong> has been received and is awaiting verification by the registrar.</p>
            <p>We'll notify you once it's verified.</p>"
        );
    }

    public static function paymentVerified(string $toEmail, string $toName, string $requestNumber): void
    {
        self::send(
            $toEmail,
            $toName,
            "Payment Verified — {$requestNumber}",
            "<p>Hi <strong>{$toName}</strong>,</p>
            <p>Your payment for request <strong>{$requestNumber}</strong> has been <strong>verified</strong>.</p>
            <p>Your document is now being processed by the registrar's office.</p>"
        );
    }

    public static function paymentRejected(string $toEmail, string $toName, string $requestNumber, string $reason): void
    {
        self::send(
            $toEmail,
            $toName,
            "Payment Rejected — {$requestNumber}",
            "<p>Hi <strong>{$toName}</strong>,</p>
            <p>Your payment for request <strong>{$requestNumber}</strong> was <strong>rejected</strong>.</p>
            <p><strong>Reason:</strong> {$reason}</p>
            <p>Please re-submit your payment with the correct details.</p>"
        );
    }

    public static function emailVerification(string $toEmail, string $toName, string $verifyLink): void
    {
        self::send(
            $toEmail,
            $toName,
            'Verify Your Email Address',
            "<p>Hi <strong>{$toName}</strong>,</p>
            <p>Thank you for registering with AcaDocs. Please verify your email address to activate your account.</p>
            <p style='margin:24px 0;'>
              <a href='{$verifyLink}' style='background:#1d4ed8;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;'>
                Verify Email Address
              </a>
            </p>
            <p>Or copy this link into your browser:<br>
              <span style='font-size:13px;color:#6b7280;word-break:break-all;'>{$verifyLink}</span>
            </p>
            <p>This link expires in <strong>24 hours</strong>. If you did not create an account, you can safely ignore this email.</p>"
        );
    }

    public static function passwordReset(string $toEmail, string $toName, string $resetLink): void
    {
        self::send(
            $toEmail,
            $toName,
            'Reset Your Password',
            "<p>Hi,</p>
            <p>We received a request to reset your AcaDocs password.</p>
            <p style='margin:24px 0;'>
              <a href='{$resetLink}' style='background:#1d4ed8;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;'>
                Reset Password
              </a>
            </p>
            <p>Or copy this link into your browser:<br>
              <span style='font-size:13px;color:#6b7280;word-break:break-all;'>{$resetLink}</span>
            </p>
            <p>This link expires in <strong>1 hour</strong>. If you didn't request a password reset, you can safely ignore this email.</p>"
        );
    }

    public static function statusChanged(string $toEmail, string $toName, string $requestNumber, string $status, ?string $reason = null): void
    {
        $messages = [
            'completed'         => ['Document Ready for Release — ' . $requestNumber, "Your document for request <strong>{$requestNumber}</strong> is ready. Please visit the registrar's office to claim it."],
            'rejected'          => ['Request Rejected — ' . $requestNumber, "Your request <strong>{$requestNumber}</strong> has been rejected.<br><strong>Reason:</strong> {$reason}"],
        ];

        if (!isset($messages[$status])) {
            return;
        }

        [$subject, $body] = $messages[$status];

        self::send(
            $toEmail,
            $toName,
            $subject,
            "<p>Hi <strong>{$toName}</strong>,</p><p>{$body}</p>"
        );
    }

    // -------------------------------------------------------
    // Layout wrapper
    // -------------------------------------------------------

    private static function wrapHtml(string $title, string $body): string
    {
        $fromName = htmlspecialchars(Env::get('MAIL_FROM_NAME', 'AcaDocs'), ENT_QUOTES);
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>{$title}</title></head>
        <body style="margin:0;padding:0;font-family:Arial,sans-serif;background:#f1f5f9;">
          <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
            <tr><td align="center">
              <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                <tr><td style="background:linear-gradient(135deg,#0369a1,#1d4ed8);padding:24px 32px;">
                  <p style="margin:0;color:#fff;font-size:18px;font-weight:bold;">{$fromName}</p>
                </td></tr>
                <tr><td style="padding:32px;color:#374151;font-size:15px;line-height:1.6;">
                  {$body}
                </td></tr>
                <tr><td style="padding:16px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;">
                  <p style="margin:0;font-size:12px;color:#94a3b8;">This is an automated notification from {$fromName}. Please do not reply to this email.</p>
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }
}
