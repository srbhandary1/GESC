<?php

declare(strict_types=1);

namespace App\Conversation\Orchestration;

use App\Conversation\Memory\LearnerMemoryService;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ProviderUsage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ConversationOrchestrator
{
    public function __construct(
        private ConversationModelProvider $conversationModel,
        private ConversationPromptCatalog $prompts,
        private ConversationContextAllowlist $allowlist,
        private ConversationOutputValidator $validator,
        private LearnerMemoryService $memory,
    ) {}

    public function respond(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $turnId,
        string $transcript,
        string $personaStyleCode,
        string $scenarioResponseCode,
        string $coachingMode,
        bool $persistAdvisory = true,
    ): ConversationModelResult {
        $prompt = $this->prompts->active($coachingMode);
        $memoryFacts = $this->memory->activeFactTexts(
            organizationId: $organizationId,
            membershipId: $membershipId,
        );
        $context = $this->allowlist->build(
            transcript: $transcript,
            personaStyleCode: $personaStyleCode,
            scenarioResponseCode: $scenarioResponseCode,
            coachingMode: $coachingMode,
            promptKey: $prompt['prompt_key'],
            promptVersion: $prompt['version'],
            promptBodySha256: $prompt['body_sha256'],
            memoryFacts: $memoryFacts,
        );

        $result = $this->conversationModel->respond(
            new ConversationModelRequest(
                interactionId: $turnId,
                transcript: $context['transcript'],
                personaStyleCode: $context['persona_style_code'],
                scenarioResponseCode: $context['scenario_response_code'],
                coachingMode: $context['coaching_mode'],
                promptVersionId: $prompt['id'],
                promptBodySha256: $prompt['body_sha256'],
                memoryFacts: $context['memory_facts'],
            ),
        );

        if (! $this->validator->validate($result)) {
            $result = $this->fallback($result->usage());
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

    private function fallback(ProviderUsage $usage): ConversationModelResult
    {
        return new ConversationModelResult(
            understoodIntent: 'safe_fallback',
            assistantReply: 'Thank you. Let us continue with a simple next sentence.',
            providerUsage: $usage,
            priorityCorrection: new PriorityCorrection(
                errorCategory: 'safety.fallback',
                correctionEn: 'Please try one short, clear English sentence.',
                explanationKn: 'ದಯವಿಟ್ಟು ಒಂದು ಸಣ್ಣ, ಸ್ಪಷ್ಟ ಇಂಗ್ಲಿಷ್ ವಾಕ್ಯ ಹೇಳಿ.',
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

        if ($correction === null) {
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
        DB::table('correction_suggestions')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'conversation_turn_id' => $turnId,
            'conversation_session_id' => $sessionId,
            'learner_membership_id' => $membershipId,
            'coaching_mode' => $coachingMode,
            'error_category' => $correction->errorCategory,
            'correction_digest' => hash('sha256', $correction->correctionEn),
            'explanation_kn_digest' => hash(
                'sha256',
                $correction->explanationKn,
            ),
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
