<?php

declare(strict_types=1);

use App\Controllers\Auth\AuthController;
use App\Controllers\HealthController;
use App\Controllers\DocumentTypeController;
use App\Controllers\Student\ProfileController;
use App\Controllers\Student\RequestController as StudentRequestController;
use App\Controllers\Student\PaymentController as StudentPaymentController;
use App\Controllers\Student\NotificationController;
use App\Controllers\Admin\RequestController as AdminRequestController;
use App\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Controllers\Admin\PaymentMethodController;
use App\Controllers\Admin\QueueController;
use App\Controllers\Admin\CashierController;
use App\Controllers\Admin\ApiKeyController;
use App\Controllers\Admin\RegistrarController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\DocumentTypeController as AdminDocumentTypeController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Controllers\Admin\FileController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\RequestAnalyticsController;
use App\Controllers\Public\KioskController;
use App\Controllers\Public\CronController;

// ============================================================
// PUBLIC — No auth required
// ============================================================

$router->get('/api/health', [HealthController::class, 'ping']);

// Auth
$router->post('/api/auth/register',        [AuthController::class, 'register']);
$router->post('/api/auth/login',           [AuthController::class, 'login']);
$router->post('/api/auth/forgot-password',       [AuthController::class, 'forgotPassword']);
$router->post('/api/auth/reset-password',        [AuthController::class, 'resetPassword']);
$router->get('/api/auth/verify-email',           [AuthController::class, 'verifyEmail']);
$router->post('/api/auth/resend-verification',   [AuthController::class, 'resendVerification']);

// Document types (public — needed for request form)
$router->get('/api/document-categories',    [DocumentTypeController::class, 'categories']);
$router->get('/api/document-types',         [DocumentTypeController::class, 'index']);
$router->get('/api/document-types/{id}',    [DocumentTypeController::class, 'show']);

// GCash QR image + info
$router->get('/api/system/gcash-qr',   [DocumentTypeController::class, 'gcashQr']);
$router->get('/api/system/gcash-info', [DocumentTypeController::class, 'gcashInfo']);
$router->get('/api/cron/send-release-reminders', [CronController::class, 'sendReleaseReminders']);
$router->get('/api/queue/display', [CashierController::class, 'publicDisplay']);

// Programs (public)
$router->get('/api/programs', [ProfileController::class, 'programs']);

// Public payment methods (for student payment page)
$router->get('/api/payment-methods',          [PaymentMethodController::class, 'index']);
$router->get('/api/payment-methods/{id}/qr',  [PaymentMethodController::class, 'serveQr']);

// ============================================================
// KIOSK / MOBILE — API key protected (X-API-Key header)
// ============================================================

$kiosk = ['apikey'];

$router->get('/api/kiosk/documents',       [KioskController::class, 'documents'],      $kiosk);
$router->get('/api/kiosk/payment-methods', [KioskController::class, 'paymentMethods'], $kiosk);
$router->post('/api/kiosk/request',        [KioskController::class, 'createRequest'],  $kiosk);
$router->post('/api/kiosk/requests/{id}/payment', [KioskController::class, 'startPayment'], $kiosk);
$router->post('/api/kiosk/requests/{id}/payment/sync', [KioskController::class, 'syncPayment'], $kiosk);
$router->post('/api/kiosk/queue',          [KioskController::class, 'getQueueNumber'], $kiosk);

// ============================================================
// STUDENT — auth + role:student
// ============================================================

$student = ['auth', 'role:student'];

// Auth (authenticated)
$router->get('/api/auth/me',                   [AuthController::class, 'me'],             ['auth']);
$router->post('/api/auth/logout',              [AuthController::class, 'logout'],          ['auth']);
$router->post('/api/auth/change-password',     [AuthController::class, 'changePassword'],  ['auth']);

// Profile
$router->get('/api/profile',  [ProfileController::class, 'show'],   $student);
$router->put('/api/profile',  [ProfileController::class, 'update'], $student);

// Document Requests
$router->get('/api/requests',       [StudentRequestController::class, 'index'],   $student);
$router->post('/api/requests',      [StudentRequestController::class, 'store'],   $student);
$router->post('/api/requests/payment/finalize', [StudentRequestController::class, 'finalizePaidCheckout'], $student);
$router->get('/api/requests/{id}',  [StudentRequestController::class, 'show'],    $student);
$router->delete('/api/requests/{id}', [StudentRequestController::class, 'destroy'], $student);

// Payment
$router->get('/api/requests/{id}/payment',  [StudentPaymentController::class, 'show'],  $student);
$router->post('/api/requests/{id}/payment', [StudentPaymentController::class, 'store'], $student);
$router->post('/api/requests/{id}/payment/sync', [StudentPaymentController::class, 'sync'], $student);

// Notifications
$router->get('/api/notifications',              [NotificationController::class, 'index'],      $student);
$router->get('/api/notifications/unread-count', [NotificationController::class, 'unreadCount'], $student);
$router->patch('/api/notifications/read-all',   [NotificationController::class, 'markAllRead'], $student);
$router->patch('/api/notifications/{id}/read',  [NotificationController::class, 'markRead'],    $student);

// ============================================================
// STAFF (Admin + Registrars) — auth + role:staff
// ============================================================

$staff = ['auth', 'role:staff'];

// Queue
$router->get('/api/admin/queue',                  [QueueController::class, 'index'],    $staff);
$router->patch('/api/admin/queue/{id}/call',      [QueueController::class, 'call'],     $staff);
$router->patch('/api/admin/queue/{id}/complete',  [QueueController::class, 'complete'], $staff);
$router->patch('/api/admin/queue/{id}/cancel',    [QueueController::class, 'cancel'],   $staff);

// Cashier windows
$router->get('/api/admin/cashier', [CashierController::class, 'index'], $staff);
$router->patch('/api/admin/cashier/windows/{id}/call-next', [CashierController::class, 'callNext'], $staff);
$router->patch('/api/admin/cashier/windows/{id}/recall', [CashierController::class, 'recall'], $staff);
$router->patch('/api/admin/cashier/windows/{id}/complete', [CashierController::class, 'complete'], $staff);

// Dashboard (admin + registrars)
$router->get('/api/admin/dashboard', [DashboardController::class, 'index'], $staff);

// Requests (admin + registrars — data filtered per role inside controller)
$router->get('/api/admin/requests',               [AdminRequestController::class, 'index'],        $staff);
$router->get('/api/admin/requests/calendar',      [AdminRequestController::class, 'calendar'],     $staff);
$router->get('/api/admin/requests/{id}',          [AdminRequestController::class, 'show'],         $staff);
$router->patch('/api/admin/requests/{id}/status', [AdminRequestController::class, 'updateStatus'], $staff);

// Payment verification (admin + registrars)
$router->post('/api/admin/requests/{id}/payment/verify', [AdminPaymentController::class, 'verify'], $staff);
$router->post('/api/admin/requests/{id}/payment/reject', [AdminPaymentController::class, 'reject'], $staff);

// Reports (admin + registrars)
$router->get('/api/admin/reports',        [ReportController::class, 'index'],  $staff);
$router->get('/api/admin/reports/export', [ReportController::class, 'export'], $staff);
$router->get('/api/admin/request-analytics', [RequestAnalyticsController::class, 'index'], $staff);

// File serving (payment proof — admin + registrars)
$router->get('/api/admin/file', [FileController::class, 'serve'], $staff);

// ============================================================
// ADMIN ONLY — auth + role:admin
// ============================================================

$admin = ['auth', 'role:admin'];

// Cashier window configuration
$router->post('/api/admin/cashier/windows', [CashierController::class, 'store'], $admin);
$router->put('/api/admin/cashier/windows/{id}', [CashierController::class, 'update'], $admin);
$router->patch('/api/admin/cashier/windows/{id}/toggle', [CashierController::class, 'toggle'], $admin);

// Document type management (staff = admin + registrars)
$router->get('/api/admin/document-types',               [AdminDocumentTypeController::class, 'index'],  $staff);
$router->post('/api/admin/document-types',              [AdminDocumentTypeController::class, 'store'],  $staff);
$router->put('/api/admin/document-types/{id}',          [AdminDocumentTypeController::class, 'update'], $staff);
$router->patch('/api/admin/document-types/{id}/toggle', [AdminDocumentTypeController::class, 'toggle'], $staff);

// Settings (read available to all staff; registrars may update daily request limit)
$router->get('/api/admin/settings',            [SettingsController::class, 'index'],        $staff);
$router->put('/api/admin/settings',            [SettingsController::class, 'update'],       $staff);
$router->post('/api/admin/settings/gcash-qr',  [SettingsController::class, 'uploadGcashQr'], $admin);

// Payment methods (admin manages)
$router->get('/api/admin/payment-methods',                  [PaymentMethodController::class, 'index'],    $admin);
$router->post('/api/admin/payment-methods',                 [PaymentMethodController::class, 'store'],    $admin);
$router->put('/api/admin/payment-methods/{id}',             [PaymentMethodController::class, 'update'],   $admin);
$router->post('/api/admin/payment-methods/{id}/qr',         [PaymentMethodController::class, 'uploadQr'], $admin);
$router->patch('/api/admin/payment-methods/{id}/toggle',    [PaymentMethodController::class, 'toggle'],   $admin);
$router->delete('/api/admin/payment-methods/{id}',          [PaymentMethodController::class, 'destroy'],  $admin);

// API keys (admin manages)
$router->get('/api/admin/api-keys',            [ApiKeyController::class, 'index'],   $admin);
$router->post('/api/admin/api-keys',           [ApiKeyController::class, 'store'],   $admin);
$router->patch('/api/admin/api-keys/{id}/toggle', [ApiKeyController::class, 'toggle'], $admin);
$router->delete('/api/admin/api-keys/{id}',    [ApiKeyController::class, 'destroy'], $admin);

// Registrars (admin manages)
$router->get('/api/admin/registrars',              [RegistrarController::class, 'index'],   $admin);
$router->post('/api/admin/registrars',             [RegistrarController::class, 'store'],   $admin);
$router->put('/api/admin/registrars/{id}',         [RegistrarController::class, 'update'],  $admin);
$router->patch('/api/admin/registrars/{id}/toggle',[RegistrarController::class, 'toggle'],  $admin);
$router->delete('/api/admin/registrars/{id}',      [RegistrarController::class, 'destroy'], $admin);

// Uploaded file serving (auth via Bearer or ?token query param)

// Admin Notifications (unread-count on $staff so registrar layout can call it)
$router->get('/api/admin/notifications/unread-count', [AdminNotificationController::class, 'unreadCount'], $staff);
$router->get('/api/admin/notifications',              [AdminNotificationController::class, 'index'],      $admin);
$router->patch('/api/admin/notifications/read-all',   [AdminNotificationController::class, 'markAllRead'], $admin);
$router->patch('/api/admin/notifications/{id}/read',  [AdminNotificationController::class, 'markRead'],    $admin);

// Audit logs (admin-only)
$router->get('/api/admin/audit-logs', [AuditLogController::class, 'index'], $admin);
