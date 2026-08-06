<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Orchestration\ConversationOutputValidator;
use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ProviderUsage;
use PHPUnit\Framework\TestCase;

final class ConversationOutputValidatorTest extends TestCase
{
    public function test_accepts_safe_structured_output(): void
    {
        $validator = new ConversationOutputValidator;
        $result = new ConversationModelResult(
            understoodIntent: 'prototype_utterance_received',
            assistantReply: 'Thank you for sharing that.',
            providerUsage: $this->usage(),
            priorityCorrection: new PriorityCorrection(
                errorCategory: 'grammar.article',
                correctionEn: 'Say a clearer sentence.',
                explanationKn: 'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳಿ.',
                retryRecommended: false,
            ),
            safetyState: 'ok',
        );

        $this->assertTrue($validator->validate($result));
    }

    public function test_rejects_markup_and_invalid_tokens(): void
    {
        $validator = new ConversationOutputValidator;
        $unsafe = new ConversationModelResult(
            understoodIntent: 'unsafe',
            assistantReply: '<script>bad</script>',
            providerUsage: $this->usage(),
        );
        $badCategory = new ConversationModelResult(
            understoodIntent: 'ok',
            assistantReply: 'Safe reply',
            providerUsage: $this->usage(),
            priorityCorrection: new PriorityCorrection(
                errorCategory: 'Bad Category!',
                correctionEn: 'Safe correction',
                explanationKn: 'ಸುರಕ್ಷಿತ',
                retryRecommended: false,
            ),
        );

        $this->assertFalse($validator->validate($unsafe));
        $this->assertFalse($validator->validate($badCategory));
    }

    private function usage(): ProviderUsage
    {
        return new ProviderUsage(
            providerKey: 'deterministic-fake',
            modelVersion: 'fake-conversation-v1',
            latencyMilliseconds: 1,
            inputUnits: 1,
            outputUnits: 1,
            estimatedCostMicrounits: 0,
        );
    }
}
