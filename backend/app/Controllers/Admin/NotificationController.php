<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\JWT;
use App\Helpers\Response;
use App\Models\NotificationModel;

class NotificationController
{
    public function index(): void
    {
        $adminId       = $this->authUserId();
        $notifications = NotificationModel::listForUser($adminId);
        Response::success('Notifications retrieved.', $notifications);
    }

    public function unreadCount(): void
    {
        $adminId = $this->authUserId();
        $count   = NotificationModel::unreadCount($adminId);
        Response::success('Unread count retrieved.', ['count' => $count]);
    }

    public function markRead(array $params): void
    {
        $adminId = $this->authUserId();
        $id      = (int) $params['id'];
        NotificationModel::markRead($id, $adminId);
        Response::success('Notification marked as read.');
    }

    public function markAllRead(): void
    {
        $adminId = $this->authUserId();
        NotificationModel::markAllRead($adminId);
        Response::success('All notifications marked as read.');
    }

    private function authUserId(): int
    {
        return (int) (JWT::getAuthUser()['user_id'] ?? 0);
    }
}
