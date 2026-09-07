<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class ProgramModel
{
    public static function all(): array
    {
        return Database::select(
            'SELECT id, name, code, department FROM programs ORDER BY department ASC, name ASC',
            []
        );
    }
}
