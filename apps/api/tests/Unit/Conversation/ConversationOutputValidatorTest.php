<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Orchestration\ConversationOutputValidator;
use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\Fake\DeterministicFakeConversationModelProvider;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Support\ConversationUiLocales;
use App\Conversation\Support\SyntheticL1Explanations;
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
                explanationL1: 'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳಿ.',
                explanationLocale: 'kn-IN',
                retryRecommended: false,
            ),
            safetyState: 'ok',
        );

        $this->assertTrue($validator->validate($result));
    }

    public function test_rejects_markup_invalid_tokens_and_shame_language(): void
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
                explanationL1: 'ಸುರಕ್ಷಿತ',
                explanationLocale: 'kn-IN',
                retryRecommended: false,
            ),
        );
        $emptyL1 = new ConversationModelResult(
            understoodIntent: 'ok',
            assistantReply: 'Safe reply',
            providerUsage: $this->usage(),
            priorityCorrection: new PriorityCorrection(
                errorCategory: 'grammar.article',
                correctionEn: 'Safe correction',
                explanationL1: '   ',
                explanationLocale: 'kn-IN',
                retryRecommended: false,
                needed: true,
            ),
        );
        $shame = new ConversationModelResult(
            understoodIntent: 'ok',
            assistantReply: 'That was stupid.',
            providerUsage: $this->usage(),
        );

        $this->assertFalse($validator->validate($unsafe));
        $this->assertFalse($validator->validate($badCategory));
        $this->assertFalse($validator->validate($emptyL1));
        $this->assertFalse($validator->validate($shame));
    }

    public function test_fake_provider_covers_all_ui_locales(): void
    {
        $provider = new DeterministicFakeConversationModelProvider;
        $promptHash = hash('sha256', 'friend.turn.v1');

        foreach (ConversationUiLocales::all() as $locale) {
            $result = $provider->respond(new ConversationModelRequest(
                interactionId: '01HTESTTURN000000000000001',
                transcript: 'I enjoy learning English after work.',
                personaStyleCode: 'patient-friend-v1',
                scenarioResponseCode: 'friend.everyday.follow-up.v1',
                coachingMode: 'gentle',
                uiLocale: $locale,
                promptVersionId: '01HTESTPROMPT0000000000001',
                promptBodySha256: $promptHash,
            ));

            $this->assertNotNull($result->priorityCorrection);
            $this->assertTrue($result->priorityCorrection->needed);
            $this->assertSame(
                $locale,
                $result->priorityCorrection->explanationLocale,
            );
            $this->assertSame(
                SyntheticL1Explanations::for(
                    SyntheticL1Explanations::gentleClearerSentence(),
                    $locale,
                ),
                $result->priorityCorrection->explanationL1,
            );
            $payload = $result->priorityCorrection->toArray();
            $this->assertSame(
                $result->priorityCorrection->explanationL1,
                $payload['explanation_l1'],
            );
            if ($locale === 'kn-IN') {
                $this->assertSame(
                    $payload['explanation_l1'],
                    $payload['explanation_kn'] ?? null,
                );
            } else {
                $this->assertArrayNotHasKey('explanation_kn', $payload);
            }
        }
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
