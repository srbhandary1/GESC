<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\ConversationEnvironmentGate;
use App\Conversation\Providers\ConversationProviderException;
use Tests\TestCase;

final class ConversationEnvironmentGateTest extends TestCase
{
    public function test_local_fake_driver_is_authorized(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');
        config()->set('conversation.provider_driver', 'fake');

        $this->app->make(ConversationEnvironmentGate::class)
            ->requireAuthorizedConversationProvider();

        $this->addToAssertionCount(1);
    }

    public function test_soft_launch_azure_driver_is_authorized(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'azure-openai-dev');

        $this->app->make(ConversationEnvironmentGate::class)
            ->requireAuthorizedConversationProvider();

        $this->addToAssertionCount(1);
    }

    public function test_soft_launch_fake_driver_is_rejected(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'fake');

        $this->expectException(ConversationProviderException::class);
        $this->app->make(ConversationEnvironmentGate::class)
            ->requireAuthorizedConversationProvider();
    }

    public function test_production_azure_driver_is_rejected(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('conversation.provider_driver', 'azure-openai-dev');

        $this->expectException(ConversationProviderException::class);
        $this->app->make(ConversationEnvironmentGate::class)
            ->requireAuthorizedConversationProvider();
    }
}
