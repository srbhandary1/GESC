<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Voice\Providers\Azure\AzureTextToSpeechProvider;
use App\Providers\AppServiceProvider;
use Tests\TestCase;

final class ConversationProviderBindingTest extends TestCase
{
    public function test_non_production_conversation_environments_include_staging(): void
    {
        $environments = AppServiceProvider::conversationNonProductionEnvironments();

        $this->assertContains('staging', $environments);
        $this->assertContains('conversation-model-dev', $environments);
        $this->assertNotContains('production', $environments);
    }

    public function test_azure_speech_tts_implements_conversation_text_to_speech_port(): void
    {
        $this->assertContains(
            TextToSpeechProvider::class,
            class_implements(AzureTextToSpeechProvider::class) ?: [],
        );
    }
}
