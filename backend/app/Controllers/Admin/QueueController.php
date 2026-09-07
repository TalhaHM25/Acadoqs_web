<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Helpers\Response;
use App\Models\QueueModel;

class QueueController
{
    /** GET /api/admin/queue?level=college|senior_high|all */
    public function index(): void
    {
        $level = $this->scopedLevel($_GET['level'] ?? 'all');
        $serviceArea = $this->serviceArea();
        Response::success('Queue retrieved.', [
            'area'    => $serviceArea,
            'queue'   => QueueModel::listToday($level, $serviceArea),
            'serving' => QueueModel::currentlyServing($level, $serviceArea),
            'summary' => QueueModel::dailySummary($level, $serviceArea),
        ]);
    }

    /** PATCH /api/admin/queue/{id}/call */
    public function call(array $params): void
    {
        $id = (int) $params['id'];
        $this->assertQueueAccess($id);
        QueueModel::call($id);
        Response::success('Queue number called.');
    }

    /** PATCH /api/admin/queue/{id}/complete */
    public function complete(array $params): void
    {
        $id = (int) $params['id'];
        $this->assertQueueAccess($id);
        QueueModel::complete($id);
        Response::success('Queue number completed.');
    }

    /** PATCH /api/admin/queue/{id}/cancel */
    public function cancel(array $params): void
    {
        $id = (int) $params['id'];
        $this->assertQueueAccess($id);
        QueueModel::cancel($id);
        Response::success('Queue number cancelled.');
    }

    private function scopedLevel(string $requestedLevel): string
    {
        return in_array($requestedLevel, ['college', 'senior_high', 'all'], true)
            ? $requestedLevel
            : 'all';
    }

    private function assertQueueAccess(int $id): void
    {
        $queue = QueueModel::findById($id);
        if (!$queue) {
            Response::error('Queue number not found.', 404);
        }
        if (($queue['service_area'] ?? 'registrar') !== $this->serviceArea()) {
            Response::error('Queue number not found.', 404);
        }
    }

    private function serviceArea(): string
    {
        return in_array($_GET['area'] ?? '', ['registrar', 'cashier'], true)
            ? (string) $_GET['area']
            : 'registrar';
    }
}
