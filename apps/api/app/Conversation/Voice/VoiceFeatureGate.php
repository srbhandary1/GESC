<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

use Illuminate\Contracts\Foundation\Application;

final readonly class VoiceFeatureGate
{
    public function __construct(private Application $app) {}

    public function requireEnabled(string $organizationId): void
    {
        if (! (bool) config('voice.upload_enabled', false)) {
            throw new VoiceOperationRejected;
        }

        if (! $this->app->environment('local', 'testing', 'soft-launch', 'production')) {
            throw new VoiceOperationRejected;
        }

        $allowed = config('voice.allowed_organization_ids', []);

        if (! is_array($allowed)) {
            throw new VoiceOperationRejected;
        }

        if (
            $this->app->environment('testing')
            && $allowed === []
        ) {
            return;
        }

        if (
            $allowed === []
            || ! in_array($organizationId, $allowed, true)
        ) {
            throw new VoiceOperationRejected;
        }
    }
}
