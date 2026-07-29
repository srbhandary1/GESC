<?php

declare(strict_types=1);

namespace App\Health;

use Illuminate\Redis\RedisManager;

final readonly class RedisReadinessProbe implements ReadinessProbe
{
    public function __construct(private RedisManager $redis) {}

    public function name(): string
    {
        return 'redis';
    }

    public function isReady(): bool
    {
        $this->redis->connection('health')->command('ping');

        return true;
    }
}
