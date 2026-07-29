<?php

declare(strict_types=1);

namespace App\Health;

interface ReadinessProbe
{
    public function name(): string;

    public function isReady(): bool;
}
