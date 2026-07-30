<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Contracts\Foundation\Application;

final readonly class ConversationEnvironmentGate
{
    public function __construct(
        private Application $application,
    ) {}

    public function requireDeterministicFakes(): void
    {
        if (
            (
                ! $this->application->environment('local')
                && ! $this->application->environment('testing')
            )
            || config('conversation.provider_driver') !== 'fake'
        ) {
            throw new ConversationProviderException(
                safeCode: 'provider_disabled',
                providerKey: 'disabled',
                modelVersion: 'disabled',
            );
        }
    }
}
