<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Env;
use App\Helpers\Response;
use App\Services\ReminderService;

class CronController
{
    private ReminderService $reminderService;

    public function __construct()
    {
        $this->reminderService = new ReminderService();
    }

    public function sendReleaseReminders(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $secret = (string) Env::get('CRON_SECRET', '');

        if ($secret === '' || !hash_equals($secret, $token)) {
            Response::error('Unauthorized.', 401);
        }

        $result = $this->reminderService->runDueReleaseReminders();
        Response::success('Release reminders processed.', $result);
    }
}

