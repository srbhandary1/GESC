<?php

declare(strict_types=1);

namespace App\Health;

use Illuminate\Database\DatabaseManager;

final readonly class DatabaseReadinessProbe implements ReadinessProbe
{
    public function __construct(private DatabaseManager $database) {}

    public function name(): string
    {
        return 'database';
    }

    public function isReady(): bool
    {
        $this->database->connection()->select('select 1');

        return true;
    }
}
