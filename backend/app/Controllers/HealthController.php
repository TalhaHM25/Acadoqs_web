<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;

class HealthController
{
    public function ping(): void
    {
        Response::success('ok', [
            'status'    => 'ok',
            'timestamp' => date('c'),
        ]);
    }
}
