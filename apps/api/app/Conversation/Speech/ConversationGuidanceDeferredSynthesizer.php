<?php

declare(strict_types=1);

namespace App\Conversation\Speech;

use App\Conversation\ConversationProviderUsagePolicy;
use App\Conversation\Observability\ProviderAttemptObservationRecorder;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Support\GuidanceTtsPolicy;
use App\Conversation\Support\SpokenGrammarNotation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class ConversationGuidanceDeferredSynthesizer
{
    public function __construct(
        private TextToSpeechProvider $textToSpeech,
        private ConversationSpeechArtifactStore $artifacts,
        private ConversationProviderUsagePolicy $usagePolicy,
        private ProviderAttemptObservationRecorder $observations,
    ) {}

    public function synthesize(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $guidanceText,
        string $uiLocale,
    ): void {
        if (trim($guidanceText) === '') {
            return;
        }

        if ($this->artifacts->find(
            $organizationId,
            $membershipId,
            $turnId,
            ConversationSpeechArtifactStore::KIND_GUIDANCE,
        ) !== null) {
            return;
        }

        try {
            $result = $this->textToSpeech->synthesize(new TextToSpeechRequest(
                interactionId: $turnId,
                text: SpokenGrammarNotation::forSpeech($guidanceText, false),
                voice: GuidanceTtsPolicy::voiceFor(
                    GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1,
                    $uiLocale,
                ),
                purpose: GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1,
                uiLocale: $uiLocale,
            ));
            $this->usagePolicy->assertAllowed('tts', $result->usage());
            $usage = $result->usage();
            $ledgerId = (string) Str::ulid();
            $recordedAt = Date::now()->toImmutable();
            $attempt = ((int) DB::table('ai_cost_ledger')
                ->where('organization_id', $organizationId)
                ->where('conversation_turn_id', $turnId)
                ->where('provider_stage', 'tts')
                ->max('provider_attempt')) + 1;
            DB::table('ai_cost_ledger')->insert([
                'id' => $ledgerId,
                'organization_id' => $organizationId,
                'conversation_turn_id' => $turnId,
                'conversation_session_id' => $sessionId,
                'learner_membership_id' => $membershipId,
                'provider_stage' => 'tts',
                'provider_key' => $usage->providerKey,
                'model_version' => $usage->modelVersion,
                'provider_attempt' => $attempt,
                'latency_ms' => $usage->latencyMilliseconds,
                'input_units' => $usage->inputUnits,
                'output_units' => $usage->outputUnits,
                'estimated_cost_microunits' => $usage->estimatedCostMicrounits,
                'result_status' => 'succeeded',
                'recorded_at' => $recordedAt,
            ]);
            $this->observations->recordAiLedger(
                organizationId: $organizationId,
                ledgerId: $ledgerId,
                sessionId: $sessionId,
                stage: 'tts',
                semanticOperation: 'conversation.synthesize',
                routeRole: 'primary',
                resultStatus: 'succeeded',
                safeFailureCode: null,
                usage: $usage,
                recordedAt: $recordedAt,
            );
            $this->artifacts->persist(
                $organizationId,
                $membershipId,
                $turnId,
                ConversationSpeechArtifactStore::KIND_GUIDANCE,
                $result,
            );
        } catch (Throwable) {
            return;
        }
    }
}
