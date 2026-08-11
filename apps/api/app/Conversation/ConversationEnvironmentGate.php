<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Contracts\Foundation\Application;

final readonly class ConversationEnvironmentGate
{
    /**
     * Environments that may serve live Azure conversation over HTTP.
     *
     * @var list<string>
     */
    private const array LIVE_AZURE_ENVIRONMENTS = [
        'conversation-model-dev',
        'staging',
        'soft-launch',
    ];

    public function __construct(
        private Application $application,
    ) {}

    /**
     * Require an authorized conversation provider for product HTTP entrypoints.
     *
     * Ordinary local/testing product paths remain deterministic fakes.
     * Soft-launch / staging / conversation-model-dev may use azure-openai-dev.
     * Production stays fail-closed (disabled).
     */
    public function requireAuthorizedConversationProvider(): void
    {
        $driver = (string) config('conversation.provider_driver');

        if ($this->allowsDeterministicFakes($driver) || $this->allowsLiveAzure($driver)) {
            return;
        }

        throw new ConversationProviderException(
            safeCode: 'provider_disabled',
            providerKey: 'disabled',
            modelVersion: 'disabled',
        );
    }

    /**
     * @deprecated Use requireAuthorizedConversationProvider(); retained name
     *             for older call sites during soft-launch cutover.
     */
    public function requireDeterministicFakes(): void
    {
        $this->requireAuthorizedConversationProvider();
    }

    private function allowsDeterministicFakes(string $driver): bool
    {
        return (
            $this->application->environment('local')
            || $this->application->environment('testing')
        ) && $driver === 'fake';
    }

    private function allowsLiveAzure(string $driver): bool
    {
        if ($driver !== 'azure-openai-dev') {
            return false;
        }

        foreach (self::LIVE_AZURE_ENVIRONMENTS as $environment) {
            if ($this->application->environment($environment)) {
                return true;
            }
        }

        return false;
    }
}
