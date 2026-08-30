<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Providers\ConfirmedTextSpeechToTextProvider;
use App\Conversation\Providers\SoftLaunch\SoftLaunchGuidanceAzureTextToSpeechProvider;
use App\Conversation\Providers\SoftLaunch\SoftLaunchRecordedAzureSpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\TextFirstTextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Voice\Providers\DisabledRecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\Indic\IndicHttpSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
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
        config()->set('conversation.guidance_tts.live_requests_enabled', false);

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

    public function test_soft_launch_binds_guidance_azure_tts_when_authorized(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'azure-openai-dev');
        config()->set('conversation.guidance_tts.live_requests_enabled', true);

        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(
            SoftLaunchGuidanceAzureTextToSpeechProvider::class,
            $this->app->make(TextToSpeechProvider::class),
        );
    }

    public function test_soft_launch_binds_recorded_azure_stt_when_authorized(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'azure-openai-dev');
        config()->set('conversation.guidance_tts.live_requests_enabled', true);
        config()->set('voice.upload_enabled', true);
        config()->set('voice.provider_driver', 'azure');

        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(
            SoftLaunchRecordedAzureSpeechToTextProvider::class,
            $this->app->make(RecordedSpeechToTextProvider::class),
        );
    }

    public function test_soft_launch_keeps_recorded_speech_disabled_without_upload_flag(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('conversation.provider_driver', 'azure-openai-dev');
        config()->set('voice.upload_enabled', false);
        config()->set('voice.provider_driver', 'disabled');

        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(
            DisabledRecordedSpeechToTextProvider::class,
            $this->app->make(RecordedSpeechToTextProvider::class),
        );
    }

    public function test_testing_binds_indic_recorded_stt_when_authorized(): void
    {
        config()->set('voice.upload_enabled', true);
        config()->set('voice.provider_driver', 'indic');

        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(
            IndicHttpSpeechToTextProvider::class,
            $this->app->make(RecordedSpeechToTextProvider::class),
        );
    }
}
