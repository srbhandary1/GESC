<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\ProviderUsage;
use Illuminate\Contracts\Foundation\Application;

final readonly class ConversationProviderUsagePolicy
{
    public function __construct(
        private Application $application,
    ) {}

    public function assertAllowed(string $stage, ProviderUsage $usage): void
    {
        if (
            ! in_array($stage, ['stt', 'model', 'tts'], true)
            || $usage->latencyMilliseconds < 0
            || $usage->inputUnits < 0
            || $usage->outputUnits < 0
            || $usage->estimatedCostMicrounits < 0
            || trim($usage->providerKey) === ''
            || trim($usage->modelVersion) === ''
        ) {
            throw new ConversationOperationRejected;
        }

        $driver = (string) config('conversation.provider_driver');

        if (
            ($this->application->environment('local')
                || $this->application->environment('testing'))
            && $driver === 'fake'
            && $usage->providerKey === 'deterministic-fake'
            && str_starts_with($usage->modelVersion, 'fake-')
            && $usage->estimatedCostMicrounits === 0
        ) {
            return;
        }

        if (
            $driver === 'azure-openai-dev'
            && $this->application->environment(
                'conversation-model-dev',
                'staging',
                'soft-launch',
            )
            && $this->isAllowedAzureStage($stage, $usage)
        ) {
            return;
        }

        throw new ConversationOperationRejected;
    }

    private function isAllowedAzureStage(
        string $stage,
        ProviderUsage $usage,
    ): bool {
        return match ($stage) {
            'stt' => $usage->providerKey === 'project-confirmed-text'
                && $usage->modelVersion === 'confirmed-text-v1'
                && $usage->estimatedCostMicrounits === 0,
            'model' => $usage->providerKey === 'azure-openai'
                && $usage->estimatedCostMicrounits >= 0,
            'tts' => (
                $usage->providerKey === 'project-text-first'
                && $usage->modelVersion === 'text-first-v1'
                && $usage->estimatedCostMicrounits === 0
            ) || (
                $usage->providerKey === 'azure-speech-guidance'
                && str_starts_with($usage->modelVersion, 'soft-launch-guidance-tts-v1:')
                && $usage->estimatedCostMicrounits >= 0
            ),
            default => false,
        };
    }
}
