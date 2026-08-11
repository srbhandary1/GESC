<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Providers\ConfirmedTextSpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\TextFirstTextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Providers\AppServiceProvider;
use Tests\TestCase;

final class ConversationProviderBindingTest extends TestCase
{
    public function test_non_production_conversation_environments_include_staging(): void
    {
        $environments = AppServiceProvider::conversationNonProductionEnvironments();

        $this->assertContains('staging', $environments);
        $this->assertContains('conversation-model-dev', $environments);
        $this->assertContains('soft-launch', $environments);
        $this->assertNotContains('production', $environments);
    }

    public function test_soft_launch_fallbacks_implement_project_owned_ports(): void
    {
        $this->assertContains(
            SpeechToTextProvider::class,
            class_implements(ConfirmedTextSpeechToTextProvider::class) ?: [],
        );
        $this->assertContains(
            TextToSpeechProvider::class,
            class_implements(TextFirstTextToSpeechProvider::class) ?: [],
        );
    }

    public function test_soft_launch_container_composes_live_model_with_safe_input_and_playback_fallbacks(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'azure-openai-dev');

        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(
            ConfirmedTextSpeechToTextProvider::class,
            $this->app->make(SpeechToTextProvider::class),
        );
        $this->assertInstanceOf(
            TextFirstTextToSpeechProvider::class,
            $this->app->make(TextToSpeechProvider::class),
        );
    }
}
