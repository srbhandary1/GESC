<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

use App\Conversation\Dialogue\DialogueModelPolicy;
use App\Conversation\Dialogue\LocalDialogueGreeting;
use App\Conversation\Memory\LearnerMemoryService;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Support\ConversationUiLocales;
use App\Conversation\Support\LessonDrillStrictTurnResponder;
use App\Conversation\Support\SyntheticL1Explanations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class ConversationOrchestrator
{
    public function __construct(
        private ConversationModelProvider $conversationModel,
        private ConversationPromptCatalog $prompts,
        private ConversationContextAllowlist $allowlist,
        private ConversationOutputValidator $validator,
        private LearnerMemoryService $memory,
        private LessonDrillStrictTurnResponder $lessonDrillStrictTurns,
    ) {}

    /**
     * @param  array<string, mixed>  $lessonDrill
     * @param  array<string, mixed>  $dialogue
     */
    public function respond(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $transcript,
        string $personaStyleCode,
        string $scenarioResponseCode,
        string $coachingMode,
        string $uiLocale,
        bool $persistAdvisory = true,
        array $lessonDrill = [],
        array $dialogue = [],
    ): ConversationModelResult {
        $locale = ConversationUiLocales::isSupported($uiLocale)
            ? $uiLocale
            : ConversationUiLocales::default();
        $greeting = LocalDialogueGreeting::reply($transcript, $dialogue);
        if ($greeting !== null) {
            return new ConversationModelResult(
                understoodIntent: 'help_greeting', assistantReply: $greeting,
                providerUsage: new ProviderUsage('local-dialogue-control', 'dialogue-control-v1', 0, 0, 0, 0, dispatchKind: 'local_execution'),
                dialogue: ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []],
            );
        }
        if ($dialogue !== [] && in_array($dialogue['action'] ?? '', ['confused', 'decline_explanation'], true)) {
            return new ConversationModelResult(
                understoodIntent: $dialogue['action'] === 'confused' ? 'help_confused' : 'help_declined',
                assistantReply: $dialogue['action'] === 'confused'
                    ? 'Would you like me to explain that in your selected language?'
                    : 'Of course. We will keep using English. You can ask another question.',
                providerUsage: new ProviderUsage('local-dialogue-control', 'dialogue-control-v1', 0, 0, 0, 0, dispatchKind: 'local_execution'),
                dialogue: ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []],
            );
        }

        if ($this->lessonDrillStrictTurns->applies($lessonDrill)) {
            $result = $this->lessonDrillStrictTurns->respond(
                transcript: $transcript,
                lessonDrill: $lessonDrill,
                uiLocale: $locale,
            );

            if ($persistAdvisory) {
                $prompt = $this->prompts->active($coachingMode);
                $this->persistAdvisory(
                    organizationId: $organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    turnId: $turnId,
                    coachingMode: $coachingMode,
                    promptVersionId: $prompt['id'],
                    result: $result,
                );
            }

            return $result;
        }

        $prompt = $this->prompts->active($coachingMode);
        $memoryFacts = ($dialogue === [] || ($dialogue['flow'] ?? null) === 'free_speech') ? $this->memory->activeFactTexts(
            organizationId: $organizationId,
            membershipId: $membershipId,
        ) : [];
        $context = $this->allowlist->build(
            transcript: $transcript,
            personaStyleCode: $personaStyleCode,
            scenarioResponseCode: $scenarioResponseCode,
            coachingMode: $coachingMode,
            uiLocale: $locale,
            promptKey: $prompt['prompt_key'],
            promptVersion: $prompt['version'],
            promptBodySha256: $prompt['body_sha256'],
            memoryFacts: $memoryFacts,
            lessonDrill: $lessonDrill,
        );

        $result = $this->conversationModel->respond(
            new ConversationModelRequest(
                interactionId: $turnId,
                transcript: $context['transcript'],
                personaStyleCode: $context['persona_style_code'],
                scenarioResponseCode: $context['scenario_response_code'],
                coachingMode: $context['coaching_mode'],
                uiLocale: $context['ui_locale'],
                promptVersionId: $prompt['id'],
                promptBodySha256: $prompt['body_sha256'],
                promptBody: $prompt['body'],
                memoryFacts: $context['memory_facts'],
                lessonDrill: $context['lesson_drill'],
                dialogue: $dialogue,
            ),
        );

        if ($dialogue !== []) {
            $result = (new DialogueModelPolicy)->separateFreeSpeechCorrection($result, $dialogue);
            $result = (new DialogueModelPolicy)->discardStaleFreeSpeechCorrection($result, $dialogue, $transcript);
            $result = (new DialogueModelPolicy)->canonicalizeRetry($result, $dialogue);
        }
        if ($dialogue !== [] && ! (new DialogueModelPolicy)->valid($result, $dialogue)) {
            // Content-free diagnostics: distinguish a length rejection from a
            // language/schema/safety contract failure without retaining text.
            Log::notice('conversation.output_rejected', [
                'flow' => in_array($dialogue['flow'] ?? '', ['question_help', 'free_speech', 'roleplay_dialogue'], true) ? $dialogue['flow'] : 'unknown',
                'reason' => (new DialogueModelPolicy)->isBrief($result->assistantReply) ? 'dialogue_contract' : 'reply_length',
                'policy' => DialogueModelPolicy::VERSION,
            ]);
            $result = new ConversationModelResult(
                understoodIntent: 'safe_fallback',
                assistantReply: 'I could not check that safely. Please try the same turn again.',
                providerUsage: $result->usage(), safetyState: 'fallback', usedFallback: true,
                dialogue: ['assessment' => 'unassessed', 'complete' => false, 'objective_evidence' => []],
            );
        } elseif (! $this->validator->validate($result)) {
            $result = $this->fallback($result->usage(), $locale);
        }

        if ($persistAdvisory) {
            $this->persistAdvisory(
                organizationId: $organizationId,
                membershipId: $membershipId,
                sessionId: $sessionId,
                turnId: $turnId,
                coachingMode: $coachingMode,
                promptVersionId: $prompt['id'],
                result: $result,
            );
        }

        return $result;
    }

    private function fallback(
        ProviderUsage $usage,
        string $uiLocale,
    ): ConversationModelResult {
        return new ConversationModelResult(
            understoodIntent: 'safe_fallback',
            assistantReply: 'Thank you. Let us continue with a simple next sentence.',
            providerUsage: $usage,
            priorityCorrection: new PriorityCorrection(
                errorCategory: 'safety.fallback',
                correctionEn: 'Please try one short, clear English sentence.',
                explanationL1: SyntheticL1Explanations::for(
                    SyntheticL1Explanations::safetyFallback(),
                    $uiLocale,
                ),
                explanationLocale: $uiLocale,
                retryRecommended: true,
            ),
            safetyState: 'fallback',
            usedFallback: true,
        );
    }

    private function persistAdvisory(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $coachingMode,
        string $promptVersionId,
        ConversationModelResult $result,
    ): void {
        $correction = $result->priorityCorrection;

        if ($correction === null || $correction->needed === false) {
            return;
        }

        $exists = DB::table('correction_suggestions')
            ->where('organization_id', $organizationId)
            ->where('conversation_turn_id', $turnId)
            ->exists();

        if ($exists) {
            return;
        }

        $now = Date::now()->toImmutable();
        $explanationDigest = hash('sha256', $correction->explanationL1);
        DB::table('correction_suggestions')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'conversation_turn_id' => $turnId,
            'conversation_session_id' => $sessionId,
            'learner_membership_id' => $membershipId,
            'coaching_mode' => $coachingMode,
            'error_category' => $correction->errorCategory,
            'correction_digest' => hash('sha256', $correction->correctionEn),
            'explanation_kn_digest' => $explanationDigest,
            'explanation_l1_digest' => $explanationDigest,
            'explanation_locale' => $correction->explanationLocale,
            'retry_recommended' => $correction->retryRecommended,
            'safety_state' => $result->safetyState,
            'prompt_version_id' => $promptVersionId,
            'recorded_at' => $now,
        ]);

        $pattern = DB::table('learner_error_patterns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('error_category', $correction->errorCategory)
            ->lockForUpdate()
            ->first(['id', 'observation_count']);

        if ($pattern === null) {
            DB::table('learner_error_patterns')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'learner_membership_id' => $membershipId,
                'error_category' => $correction->errorCategory,
                'observation_count' => 1,
                'first_observed_at' => $now,
                'last_observed_at' => $now,
            ]);

            return;
        }

        DB::table('learner_error_patterns')
            ->where('organization_id', $organizationId)
            ->where('id', $pattern->id)
            ->update([
                'observation_count' => ((int) $pattern->observation_count) + 1,
                'last_observed_at' => $now,
            ]);
    }
}
