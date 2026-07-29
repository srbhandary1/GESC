<?php

declare(strict_types=1);

namespace App\Health;

use Illuminate\Support\Facades\Log;
use Throwable;

final class ReadinessChecker
{
    /**
     * @var list<ReadinessProbe>
     */
    private readonly array $probes;

    public function __construct(ReadinessProbe ...$probes)
    {
        $this->probes = array_values($probes);
    }

    public function isReady(): bool
    {
        foreach ($this->probes as $probe) {
            try {
                if ($probe->isReady()) {
                    continue;
                }

                Log::warning('health.readiness_probe_failed', [
                    'dependency' => $probe->name(),
                    'failure_type' => 'unavailable',
                ]);
            } catch (Throwable $exception) {
                Log::warning('health.readiness_probe_failed', [
                    'dependency' => $probe->name(),
                    'failure_type' => 'exception',
                    'exception_class' => $exception::class,
                ]);
            }

            return false;
        }

        return true;
    }
}
