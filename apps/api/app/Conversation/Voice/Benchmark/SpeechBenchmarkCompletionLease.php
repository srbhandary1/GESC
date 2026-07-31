<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Benchmark;

final class SpeechBenchmarkCompletionLease
{
    private bool $released = false;

    /**
     * @param  array<string, mixed>  $state
     * @param  resource  $lock
     */
    public function __construct(
        public readonly array $state,
        private mixed $lock,
    ) {}

    public function release(): void
    {
        if ($this->released) {
            return;
        }

        flock($this->lock, LOCK_UN);
        fclose($this->lock);
        $this->released = true;
    }

    public function __destruct()
    {
        $this->release();
    }
}
