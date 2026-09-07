<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Models\NotificationModel;

class NotificationController
{
    public function index(): void
    {
        $userId        = $this->authUserId();
        $notifications = NotificationModel::listForUser($userId);
        Response::success('Notifications retrieved.', $notifications);
    }

    public function unreadCount(): void
    {
        $userId = $this->authUserId();
        $count  = NotificationModel::unreadCount($userId);
        Response::success('Unread count retrieved.', ['count' => $count]);
    }

    public function markRead(array $params): void
    {
        $userId = $this->authUserId();
        $id     = (int) $params['id'];
        NotificationModel::markRead($id, $userId);
        Response::success('Notification marked as read.');
    }

    public function markAllRead(): void
    {
        $userId = $this->authUserId();
        NotificationModel::markAllRead($userId);
        Response::success('All notifications marked as read.');
    }

    private function authUserId(): int
    {
        return (int) (JWT::getAuthUser()['user_id'] ?? 0);
    }
}
