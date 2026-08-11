<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\ConversationProviderUsagePolicy;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\ProviderUsage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ConversationProviderUsagePolicyTest extends TestCase
{
    #[DataProvider('softLaunchUsageProvider')]
    public function test_soft_launch_accepts_only_the_provider_for_each_stage(
        string $stage,
        string $provider,
        string $model,
        int $cost,
    ): void {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'azure-openai-dev');

        $this->app->make(ConversationProviderUsagePolicy::class)->assertAllowed(
            $stage,
            $this->usage($provider, $model, $cost),
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function softLaunchUsageProvider(): iterable
    {
        yield 'confirmed text' => [
            'stt',
            'project-confirmed-text',
            'confirmed-text-v1',
            0,
        ];
        yield 'live model' => [
            'model',
            'azure-openai',
            'confidence-model-v1',
            42,
        ];
        yield 'text-first speech' => [
            'tts',
            'project-text-first',
            'text-first-v1',
            0,
        ];
    }

    public function test_soft_launch_rejects_fake_or_cross_stage_usage(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'azure-openai-dev');
        $policy = $this->app->make(ConversationProviderUsagePolicy::class);

        foreach ([
            ['model', 'deterministic-fake', 'fake-model-v1'],
            ['stt', 'azure-openai', 'confidence-model-v1'],
            ['tts', 'azure-speech', 'realtime-en-IN-neural'],
        ] as [$stage, $provider, $model]) {
            try {
                $policy->assertAllowed(
                    $stage,
                    $this->usage($provider, $model, 0),
                );
                self::fail('Expected unauthorized provider usage to fail.');
            } catch (ConversationOperationRejected) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_testing_fake_policy_remains_locked(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'testing');
        config()->set('conversation.provider_driver', 'fake');

        $this->app->make(ConversationProviderUsagePolicy::class)->assertAllowed(
            'model',
            $this->usage('deterministic-fake', 'fake-model-v1', 0),
        );

        $this->addToAssertionCount(1);
    }

    private function usage(
        string $provider,
        string $model,
        int $cost,
    ): ProviderUsage {
        return new ProviderUsage(
            providerKey: $provider,
            modelVersion: $model,
            latencyMilliseconds: 1,
            inputUnits: 2,
            outputUnits: 3,
            estimatedCostMicrounits: $cost,
        );
    }
}
