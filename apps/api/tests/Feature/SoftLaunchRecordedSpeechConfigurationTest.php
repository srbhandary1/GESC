<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\SoftLaunch\SoftLaunchRecordedSpeechConfiguration;
use Tests\TestCase;

final class SoftLaunchRecordedSpeechConfigurationTest extends TestCase
{
    public function test_azure_stt_preflight_allows_indic_driver_for_failover(): void
    {
        config()->set('voice.upload_enabled', true);
        config()->set('voice.provider_driver', 'indic');

        $errors = $this->app->make(SoftLaunchRecordedSpeechConfiguration::class)
            ->preflightErrors();

        $this->assertNotContains('provider_driver_invalid', $errors);
    }

    public function test_azure_stt_preflight_rejects_a_disabled_driver(): void
    {
        config()->set('voice.upload_enabled', true);
        config()->set('voice.provider_driver', 'disabled');

        $errors = $this->app->make(SoftLaunchRecordedSpeechConfiguration::class)
            ->preflightErrors();

        $this->assertContains('provider_driver_invalid', $errors);
    }
}
